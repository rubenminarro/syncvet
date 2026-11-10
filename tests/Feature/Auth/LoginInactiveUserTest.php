<?php

namespace Tests\Feature\Auth;

use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Login de un usuario inactivo (FASE 2, entrega 2.2b; P1): con la contraseña
 * correcta, 403 USER_INACTIVE sin token y con LOGIN_FAILED; con una incorrecta, el
 * INVALID_CREDENTIALS de siempre (no revela que la cuenta existe). No cuenta como
 * intento fallido.
 */
class LoginInactiveUserTest extends TestCase
{
    use InteractsWithAuth;

    public function test_an_inactive_user_with_the_right_password_gets_403_without_a_token(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista', ['is_active' => false]);

        $response = $this->login($user->email);

        $this->assertErrorCode($response, 403, 'USER_INACTIVE');
        $response->assertJsonPath('message', 'Tu usuario está inactivo: consultá con el administrador de la clínica.')
            ->assertJsonPath('data', null);
        $this->assertSame(0, $user->tokens()->count());

        $log = DB::table('audit_logs')->where('event', 'login_failed')->where('user_id', $user->id)->sole();
        $this->assertSame(['email' => $user->email, 'reason' => AuthService::REASON_USER_INACTIVE], json_decode($log->new_values, true));
    }

    public function test_an_inactive_user_with_a_wrong_password_gets_the_usual_error(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista', ['is_active' => false]);

        $response = $this->login($user->email, 'incorrecta');

        $this->assertErrorCode($response, 422, 'INVALID_CREDENTIALS');
        $response->assertJsonPath('message', 'El email o la contraseña no son correctos.');
    }

    /**
     * Con la contraseña incorrecta, un inactivo es indistinguible de un activo: la misma
     * respuesta completa (salvo timestamp) en cada intento, el intento cuenta para el
     * límite (el sexto, 429 en los dos) y la auditoría no guarda el motivo.
     */
    public function test_with_a_wrong_password_an_inactive_user_looks_like_an_active_one(): void
    {
        $clinic = $this->createClinic();
        $active = $this->userWithRole($clinic, 'recepcionista', ['email' => 'activa@example.com']);
        $inactive = $this->userWithRole($clinic, 'recepcionista', ['email' => 'inactiva@example.com', 'is_active' => false]);

        $attempts = function (string $email): array {
            $responses = [];

            for ($i = 0; $i < 6; $i++) {
                $response = $this->login($email, 'incorrecta');
                $responses[] = [
                    'status' => $response->getStatusCode(),
                    'body' => collect($response->json())->except('timestamp')->all(),
                    'retry_after' => $response->headers->has('Retry-After'),
                ];
            }

            return $responses;
        };

        $fromActive = $attempts($active->email);
        $fromInactive = $attempts($inactive->email);

        // El sexto trae los segundos que faltan en el mensaje: pueden diferir en uno.
        $withoutSeconds = fn (array $responses) => array_map(function (array $response) {
            $response['body']['message'] = preg_replace('/\d+/', 'N', $response['body']['message']);

            return $response;
        }, $responses);

        $this->assertSame($withoutSeconds($fromActive), $withoutSeconds($fromInactive));
        $this->assertSame([422, 422, 422, 422, 422, 429], array_column($fromInactive, 'status'));
        $this->assertSame('INVALID_CREDENTIALS', $fromInactive[0]['body']['error_code']);
        $this->assertSame('AUTH_TOO_MANY_ATTEMPTS', $fromInactive[5]['body']['error_code']);
        $this->assertTrue($fromInactive[5]['retry_after']);

        $logs = DB::table('audit_logs')->where('event', 'login_failed')->where('user_id', $inactive->id)->pluck('new_values');
        $this->assertCount(5, $logs);

        foreach ($logs as $values) {
            $this->assertSame(['email' => $inactive->email], json_decode($values, true));
        }
    }

    /**
     * Credenciales correctas: no cuenta para el límite de 5 intentos. Reactivado, entra.
     */
    public function test_it_does_not_count_as_a_failed_attempt(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista', ['is_active' => false]);

        for ($i = 0; $i < 7; $i++) {
            $this->assertErrorCode($this->login($user->email), 403, 'USER_INACTIVE');
        }

        $user->forceFill(['is_active' => true])->save();

        $this->login($user->email)->assertOk();
    }

    /**
     * La clínica suspendida se revisa antes que el usuario inactivo.
     */
    public function test_a_suspended_clinic_is_checked_first(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRole($clinic, 'recepcionista', ['is_active' => false]);
        DB::table('clinics')->where('id', $clinic->id)->update(['status' => 'suspended']);

        $this->assertErrorCode($this->login($user->email), 403, 'CLINIC_NOT_AVAILABLE');
    }
}
