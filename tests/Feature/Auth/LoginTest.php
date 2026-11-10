<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * POST /api/login: token de 12 horas, varias sesiones por usuario, el mismo
 * error para cualquier credencial incorrecta y clínica suspendida sin token.
 */
class LoginTest extends TestCase
{
    use InteractsWithAuth;

    public static function roles(): array
    {
        return [
            'administrador' => ['administrador'],
            'recepcionista' => ['recepcionista'],
            'veterinario' => ['veterinario'],
        ];
    }

    #[DataProvider('roles')]
    public function test_login_with_each_role_returns_a_token_and_the_profile(string $role): void
    {
        $this->freezeSecond();
        $clinic = $this->createClinic(['name' => 'Veterinaria Norte']);
        $user = $this->userWithRole($clinic, $role);

        $response = $this->login($user->email);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', now()->addHours(12)->toISOString())
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.clinic', ['id' => $clinic->id, 'name' => 'Veterinaria Norte', 'has_logo' => false])
            ->assertJsonPath('data.user.roles', [$role])
            ->assertJsonStructure(['data' => [
                'token', 'token_type', 'expires_at',
                'user' => ['id', 'first_name', 'last_name', 'name', 'email', 'clinic', 'current_branch', 'roles', 'permissions', 'veterinarian'],
            ]]);
        $this->assertStringEndsWith('Z', $response->json('data.expires_at'));

        $this->getWithToken($response->json('data.token'), '/api/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_token_expires_twelve_hours_after_login(): void
    {
        $this->freezeSecond();
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $this->login($user->email)->assertOk();

        $this->assertEquals(now()->addHours(12), $user->tokens()->sole()->expires_at);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_error(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $wrongPassword = $this->login($user->email, 'otra-clave');
        $unknownEmail = $this->login('nadie@example.com');

        foreach ([$wrongPassword, $unknownEmail] as $response) {
            $this->assertErrorCode($response, 422, 'INVALID_CREDENTIALS');
            $response->assertJsonPath('message', 'El email o la contraseña no son correctos.');
        }
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_deleted_user_cannot_log_in_and_gets_the_same_error(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $user->delete();

        $response = $this->login($user->email);

        $this->assertErrorCode($response, 422, 'INVALID_CREDENTIALS');
        $response->assertJsonPath('message', 'El email o la contraseña no son correctos.');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_suspended_clinic_cannot_log_in_and_no_token_is_created(): void
    {
        $user = $this->userWithRole($this->createClinic(['status' => ClinicStatus::SUSPENDED]), 'recepcionista');

        $response = $this->login($user->email);

        $this->assertErrorCode($response, 403, 'CLINIC_NOT_AVAILABLE');
        $response->assertJsonPath('message', 'La clínica no está disponible.');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_each_login_creates_a_new_token_and_the_previous_ones_stay_valid(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $first = $this->tokenFor($user);
        $second = $this->tokenFor($user);

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $user->tokens()->count());
        $this->getWithToken($first, '/api/me')->assertOk();
        $this->getWithToken($second, '/api/me')->assertOk();
    }

    public function test_login_validates_email_and_password_with_voseo_messages(): void
    {
        $response = $this->postJson('/api/login', ['email' => 'no-es-un-email']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.email.0', 'Ingresá un email válido.')
            ->assertJsonPath('errors.password.0', 'Ingresá tu contraseña.');

        $this->postJson('/api/login', [])->assertJsonPath('errors.email.0', 'Ingresá tu email.');
    }

    /**
     * Con la clínica suspendida y la contraseña incorrecta, el mismo error que
     * cualquier credencial incorrecta: la suspensión solo se informa a quien ya
     * probó tener la contraseña.
     */
    public function test_wrong_password_in_a_suspended_clinic_does_not_reveal_the_suspension(): void
    {
        $user = $this->userWithRole($this->createClinic(['status' => ClinicStatus::SUSPENDED]), 'recepcionista');

        $response = $this->login($user->email, 'otra-clave');

        $this->assertErrorCode($response, 422, 'INVALID_CREDENTIALS');
        $response->assertJsonPath('message', 'El email o la contraseña no son correctos.');
        $this->assertStringNotContainsString('clínica', $response->getContent());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_password_that_is_not_a_string_is_a_validation_error(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => ['password']]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.password.0', 'Ingresá una contraseña válida.');
    }

    public function test_an_email_longer_than_255_characters_is_a_validation_error(): void
    {
        $email = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 59) . '.com';
        $this->assertSame(256, strlen($email));

        $response = $this->postJson('/api/login', ['email' => $email, 'password' => 'password']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        // La regla email también lo rechaza (RFC: 254); max:255 da el mensaje claro.
        $this->assertContains('El email no puede tener más de 255 caracteres.', $response->json('errors.email'));
    }

    /**
     * Solo ASCII: la collation de MySQL iguala "ā" y "a", así que con acentos un email
     * distinto sería el mismo login.
     */
    public function test_an_email_with_non_ascii_characters_is_a_validation_error(): void
    {
        $this->userWithRole($this->createClinic(), 'recepcionista', ['email' => 'ana@example.com']);

        foreach (['ānā@example.com', 'ăna@example.com', 'ａna@example.com', 'ana@exämple.com'] as $email) {
            $response = $this->login($email);

            $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
            $response->assertJsonPath('errors.email.0', 'Ingresá un email sin acentos ni caracteres especiales.');
        }

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }
}
