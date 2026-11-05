<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicStatus;
use App\Exceptions\AuthException;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
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

    /**
     * Los emails sin cuenta también cuentan: si no, el 429 delataría qué emails
     * existen.
     */
    public function test_failed_attempts_with_an_unknown_email_also_count(): void
    {
        $this->failTimes('nadie@example.com', 5);

        $this->assertErrorCode($this->login('nadie@example.com', 'otra-clave'), 429, 'AUTH_TOO_MANY_ATTEMPTS');
    }

    /**
     * Ráfaga: un pedido que llega mientras otro verifica la contraseña ya ve su
     * intento contado. Se simula con un segundo login dentro de Hash::check del
     * primero: con 4 fallidos previos, el primero es el quinto (se procesa) y el
     * que llega durante la verificación es el sexto (429).
     */
    public function test_an_attempt_counts_before_the_password_is_verified(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $this->failTimes($user->email, 4);

        $concurrent = null;
        $real = Hash::getFacadeRoot();
        $mock = Mockery::mock($real);
        $mock->shouldReceive('check')->andReturnUsing(function (...$args) use ($real, $user, &$concurrent) {
            if ($concurrent === null) {
                $concurrent = 'sin error';
                try {
                    app(AuthService::class)->login($user->email, 'otra-clave', '127.0.0.1');
                } catch (AuthException $e) {
                    $concurrent = $e->getErrorCode();
                }
            }

            return $real->check(...$args);
        });
        Hash::swap($mock);

        $this->assertErrorCode($this->login($user->email, 'otra-clave'), 422, 'INVALID_CREDENTIALS');
        $this->assertSame('AUTH_TOO_MANY_ATTEMPTS', $concurrent);
    }

    /**
     * Variantes Unicode del email bloqueado: nunca entran. Por la API las frena la
     * regla ascii (422); en MySQL, aunque esa regla faltara, la base las iguala al
     * usuario y el contador es el del usuario (429).
     */
    public function test_unicode_variants_of_a_blocked_email_never_log_in(): void
    {
        $this->userWithRole($this->createClinic(), 'recepcionista', ['email' => 'ana@example.com']);
        $this->failTimes('ana@example.com', 5);

        foreach (['ānā@example.com', 'ăna@example.com', 'ａna@example.com'] as $email) {
            $status = $this->login($email)->status();
            $this->assertContains($status, [422, 429], "{$email} respondió {$status}");
        }

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    /**
     * La segunda capa, sin la validación del pedido: en MySQL (utf8mb4_unicode_ci)
     * "ānā@example.com" encuentra a ana@example.com, y el contador por usuario la
     * bloquea aunque la contraseña sea correcta. SQLite compara exacto: no aplica.
     */
    public function test_in_mysql_the_counter_is_per_user_whatever_variant_of_the_email(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('La collation que iguala "ā" y "a" es la de MySQL.');
        }

        $user = $this->userWithRole($this->createClinic(), 'recepcionista', ['email' => 'ana@example.com']);
        $this->failTimes('ana@example.com', 5);

        foreach (['ānā@example.com', 'ăna@example.com', 'ａna@example.com'] as $email) {
            try {
                app(AuthService::class)->login($email, 'password', '127.0.0.1');
                $this->fail("{$email} entró con el contador de ana@example.com bloqueado.");
            } catch (AuthException $e) {
                $this->assertSame('AUTH_TOO_MANY_ATTEMPTS', $e->getErrorCode(), $email);
            }
        }

        $this->assertSame(0, $user->tokens()->count());
    }
}
