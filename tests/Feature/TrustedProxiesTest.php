<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * X-Forwarded-For solo vale si viene de un proxy de TRUSTED_PROXIES
 * (config/trustedproxy.php). La IP decide el límite de intentos del login y la
 * IP de la auditoría: un cliente no puede inventarla.
 */
class TrustedProxiesTest extends TestCase
{
    use InteractsWithAuth;

    public function test_no_proxy_is_trusted_by_default(): void
    {
        $this->assertSame([], config('trustedproxy.proxies'));
    }

    public function test_without_trusted_proxies_x_forwarded_for_is_ignored(): void
    {
        $this->failedLoginFrom('10.0.0.1', ['X-Forwarded-For' => '1.2.3.4']);

        $this->assertSame('10.0.0.1', $this->lastFailedLoginIp());
    }

    /**
     * Con la lista vacía (y no null), Laravel no confía en todos los proxies cuando
     * el Host termina en .on-forge.com, un encabezado que manda el cliente.
     */
    public function test_a_forge_host_header_does_not_make_every_proxy_trusted(): void
    {
        $this->failedLoginFrom('10.0.0.1', ['X-Forwarded-For' => '1.2.3.4'], 'http://syncvet.on-forge.com');

        $this->assertSame('10.0.0.1', $this->lastFailedLoginIp());
    }

    public function test_a_trusted_proxy_passes_the_client_ip(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.1']]);

        $this->failedLoginFrom('10.0.0.1', ['X-Forwarded-For' => '1.2.3.4']);
        $this->assertSame('1.2.3.4', $this->lastFailedLoginIp());

        // Otro origen que no es el proxy: su encabezado no cuenta.
        $this->failedLoginFrom('10.0.0.9', ['X-Forwarded-For' => '5.6.7.8']);
        $this->assertSame('10.0.0.9', $this->lastFailedLoginIp());
    }

    /**
     * @param string $baseUrl El Host del pedido sale de la URL (en los tests, un encabezado Host no lo cambia).
     */
    private function failedLoginFrom(string $remoteAddr, array $headers, string $baseUrl = ''): void
    {
        $this->forgetAuthenticatedUser();

        $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
            ->withHeaders($headers)
            ->postJson($baseUrl . '/api/login', ['email' => 'nadie@example.com', 'password' => 'otra-clave'])
            ->assertStatus(422);
    }

    private function lastFailedLoginIp(): ?string
    {
        return DB::table('audit_logs')->where('event', AuditEvent::LOGIN_FAILED->value)->orderByDesc('id')->value('ip_address');
    }
}
