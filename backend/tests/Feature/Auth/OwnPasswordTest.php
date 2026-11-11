<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * PATCH me/password (FASE 2, entrega 2.2b; pregunta 20): la contraseña actual
 * correcta y una nueva con la regla del proyecto, distinta de la actual. Conserva el
 * token del pedido y revoca los demás del usuario. Límite: 5 intentos por minuto por
 * usuario; el sexto, 429.
 */
class OwnPasswordTest extends TestCase
{
    use InteractsWithUsers;

    private const NEW_PASSWORD = 'Otra-Clave-2026!';

    public function test_a_correct_change_keeps_the_current_token_and_revokes_the_others(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['veterinario']);
        $token = $user->createToken('api')->plainTextToken;
        $other = $user->createToken('api')->plainTextToken;
        $colleague = $this->userWithRoles($clinic, ['recepcionista']);
        $colleague->createToken('api');

        $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'password', 'password' => self::NEW_PASSWORD])
            ->assertOk()
            ->assertJsonPath('message', 'Contraseña actualizada.')
            ->assertJsonPath('data', null);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(1, $colleague->tokens()->count());
        $this->withRealToken($token)->getJson('/api/me')->assertOk();
        $this->assertErrorCode($this->withRealToken($other)->getJson('/api/me'), 401, 'UNAUTHENTICATED');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => self::NEW_PASSWORD])->assertOk();

        $logs = DB::table('audit_logs')->where('auditable_type', 'user')->where('auditable_id', $user->id)->get()->toJson();
        $this->assertStringNotContainsString(self::NEW_PASSWORD, $logs);
        $this->assertStringNotContainsString('$2y$', $logs);
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = $this->userWithRoles($this->createClinic(), ['recepcionista']);
        $token = $user->createToken('api')->plainTextToken;
        $hash = $user->password;

        $response = $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'incorrecta', 'password' => self::NEW_PASSWORD]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors', ['current_password' => ['La contraseña actual no es correcta.']]);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_the_new_password_follows_the_rule_and_differs_from_the_current_one(): void
    {
        $user = $this->userWithRoles($this->createClinic(), ['recepcionista'], [], ['password' => self::VALID_PASSWORD]);
        $token = $user->createToken('api')->plainTextToken;
        $this->compromisedPasswords = ['Filtrada-2026!x'];

        foreach (['corta1!A', 'Filtrada-2026!x', '', self::VALID_PASSWORD] as $password) {
            $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => self::VALID_PASSWORD, 'password' => $password])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => self::VALID_PASSWORD, 'password' => self::VALID_PASSWORD])
            ->assertJsonPath('errors.password', ['La contraseña nueva tiene que ser distinta de la actual.']);
        $this->withRealToken($token)->patchJson('/api/me/password', ['password' => self::NEW_PASSWORD])
            ->assertJsonPath('errors.current_password', ['Ingresá tu contraseña actual.']);
    }

    /**
     * 5 intentos por minuto por usuario, como el login: un current_password incorrecto
     * suma uno; el sexto responde 429 con Retry-After, aunque la actual sea correcta. El
     * contador es del usuario: otro no queda bloqueado.
     */
    public function test_the_sixth_attempt_in_a_minute_is_blocked(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $token = $user->createToken('api')->plainTextToken;
        $colleague = $this->userWithRoles($clinic, ['recepcionista']);
        $colleagueToken = $colleague->createToken('api')->plainTextToken;

        for ($i = 0; $i < 5; $i++) {
            $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'incorrecta', 'password' => self::NEW_PASSWORD])
                ->assertUnprocessable();
        }

        $response = $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'password', 'password' => self::NEW_PASSWORD]);

        $this->assertErrorCode($response, 429, 'AUTH_TOO_MANY_ATTEMPTS');
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $this->withRealToken($colleagueToken)->patchJson('/api/me/password', ['current_password' => 'password', 'password' => self::NEW_PASSWORD])->assertOk();

        // Pasado el minuto, vuelve a entrar.
        $this->travel(61)->seconds();
        $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'password', 'password' => self::NEW_PASSWORD])->assertOk();
    }

    /**
     * Un cambio correcto limpia el contador.
     */
    public function test_a_correct_change_clears_the_counter(): void
    {
        $user = $this->userWithRoles($this->createClinic(), ['recepcionista']);
        $token = $user->createToken('api')->plainTextToken;

        for ($i = 0; $i < 4; $i++) {
            $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'incorrecta', 'password' => self::NEW_PASSWORD])->assertUnprocessable();
        }

        $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'password', 'password' => self::NEW_PASSWORD])->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->withRealToken($token)->patchJson('/api/me/password', ['current_password' => 'incorrecta', 'password' => self::VALID_PASSWORD])->assertUnprocessable();
        }
    }
}
