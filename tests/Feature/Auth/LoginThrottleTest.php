<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicStatus;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Límite del login: 5 intentos fallidos por minuto por email (en minúsculas) e
 * IP. El sexto responde 429 con Retry-After; un login correcto limpia el contador.
 */
class LoginThrottleTest extends TestCase
{
    use InteractsWithAuth;

    public function test_sixth_failed_attempt_is_blocked_with_retry_after(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $this->failTimes($user->email, 5);

        // Bloqueado aunque ahora la contraseña sea la correcta.
        $response = $this->login($user->email);

        $this->assertErrorCode($response, 429, 'AUTH_TOO_MANY_ATTEMPTS');
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
        $response->assertJsonPath('message', "Demasiados intentos. Probá de nuevo en {$retryAfter} segundos.");
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_counter_is_per_email_another_email_from_the_same_ip_is_not_blocked(): void
    {
        $clinic = $this->createClinic();
        $blocked = $this->userWithRole($clinic, 'recepcionista');
        $other = $this->userWithRole($clinic, 'recepcionista');
        $this->failTimes($blocked->email, 5);

        $this->login($other->email)->assertOk();
    }

    public function test_the_counter_is_per_ip_the_same_email_from_another_ip_is_not_blocked(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $this->failTimes($user->email, 5);

        $this->forgetAuthenticatedUser();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_the_email_counts_in_lowercase(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista', ['email' => 'ana@example.com']);
        $this->failTimes('ANA@example.com', 3);
        $this->failTimes('Ana@Example.com', 2);

        $this->assertErrorCode($this->login('ana@example.com'), 429, 'AUTH_TOO_MANY_ATTEMPTS');
    }

    public function test_a_successful_login_clears_the_counter(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $this->failTimes($user->email, 4);

        $this->login($user->email)->assertOk();

        // Con el contador en cero, entran otros cinco fallidos antes del bloqueo.
        $this->failTimes($user->email, 5);
        $this->assertErrorCode($this->login($user->email, 'otra-clave'), 429, 'AUTH_TOO_MANY_ATTEMPTS');
    }

    public function test_only_failed_attempts_count(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        for ($i = 1; $i <= 6; $i++) {
            $this->login($user->email)->assertOk();
        }
    }

    /**
     * Con la clínica suspendida las credenciales son correctas: queda auditado, pero
     * no es un intento fallido y no bloquea.
     */
    public function test_logins_to_a_suspended_clinic_do_not_count_as_failed(): void
    {
        $user = $this->userWithRole($this->createClinic(['status' => ClinicStatus::SUSPENDED]), 'recepcionista');

        for ($i = 1; $i <= 6; $i++) {
            $this->assertErrorCode($this->login($user->email), 403, 'CLINIC_NOT_AVAILABLE');
        }
    }

    public function test_the_block_ends_after_a_minute(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $this->failTimes($user->email, 5);
        $this->assertErrorCode($this->login($user->email), 429, 'AUTH_TOO_MANY_ATTEMPTS');

        $this->travel(61)->seconds();

        $this->login($user->email)->assertOk();
    }

    private function failTimes(string $email, int $times): void
    {
        for ($i = 1; $i <= $times; $i++) {
            $this->assertErrorCode($this->login($email, 'otra-clave'), 422, 'INVALID_CREDENTIALS');
        }
    }
}
