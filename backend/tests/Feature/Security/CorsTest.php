<?php

namespace Tests\Feature\Security;

use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * CORS (entrega front-00, B2; R-058): solo los orígenes de CORS_ALLOWED_ORIGINS, nunca el
 * comodín; sin la variable o vacía, ninguno. Retry-After expuesto para la cuenta regresiva
 * de un 429 en el navegador.
 */
class CorsTest extends TestCase
{
    use InteractsWithAuth;

    private const ALLOWED = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => $this->originsFrom(self::ALLOWED . ',https://app.syncvet.test')]);
    }

    public function test_an_allowed_origin_is_answered_with_that_origin(): void
    {
        $this->withHeader('Origin', self::ALLOWED)
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);
    }

    public function test_an_origin_outside_the_list_gets_no_allow_origin(): void
    {
        $this->withHeader('Origin', 'https://evil.test')
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_a_preflight_from_an_allowed_origin_answers_204_with_the_methods(): void
    {
        $response = $this->call('OPTIONS', '/api/me/current-branch', server: [
            'HTTP_ORIGIN' => self::ALLOWED,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::ALLOWED)
            ->assertHeader('Access-Control-Allow-Methods', 'PATCH')
            ->assertHeader('Access-Control-Max-Age', '600');
        $this->assertSame('authorization,content-type', $response->headers->get('Access-Control-Allow-Headers'));
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_a_preflight_from_another_origin_is_not_allowed(): void
    {
        $this->call('OPTIONS', '/api/me', server: [
            'HTTP_ORIGIN' => 'https://evil.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_without_the_variable_or_empty_no_origin_is_allowed(): void
    {
        foreach ([null, '', ' , ,'] as $value) {
            $origins = $this->originsFrom($value);
            $this->assertSame([], $origins);
            config(['cors.allowed_origins' => $origins]);

            $this->withHeader('Origin', self::ALLOWED)
                ->getJson('/api/me')
                ->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    public function test_the_list_is_split_by_commas_without_spaces(): void
    {
        $this->assertSame(['http://a.test', 'http://b.test'], $this->originsFrom(' http://a.test, http://b.test ,'));
    }

    public function test_the_published_config_has_no_wildcard_by_default(): void
    {
        $config = $this->configWith(null);

        $this->assertSame([], $config['allowed_origins']);
        $this->assertSame(['api/*'], $config['paths']);
        $this->assertSame([], $config['allowed_origins_patterns']);
        $this->assertSame(['Retry-After'], $config['exposed_headers']);
        $this->assertFalse($config['supports_credentials']);
    }

    public function test_a_429_from_an_allowed_origin_exposes_retry_after(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, 'otra-clave')->assertUnprocessable();
        }

        $this->forgetAuthenticatedUser();
        $response = $this->withHeader('Origin', self::ALLOWED)
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertTooManyRequests()
            ->assertHeader('Access-Control-Allow-Origin', self::ALLOWED)
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    /**
     * allowed_origins de config/cors.php con CORS_ALLOWED_ORIGINS en el valor dado.
     *
     * @return list<string>
     */
    private function originsFrom(?string $value): array
    {
        return $this->configWith($value)['allowed_origins'];
    }

    /**
     * config/cors.php evaluado con CORS_ALLOWED_ORIGINS en el valor dado (null: sin la variable).
     *
     * @return array<string, mixed>
     */
    private function configWith(?string $value): array
    {
        $previous = $_SERVER['CORS_ALLOWED_ORIGINS'] ?? null;
        $previousEnv = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;

        try {
            unset($_SERVER['CORS_ALLOWED_ORIGINS'], $_ENV['CORS_ALLOWED_ORIGINS']);
            putenv('CORS_ALLOWED_ORIGINS');
            if ($value !== null) {
                $_SERVER['CORS_ALLOWED_ORIGINS'] = $_ENV['CORS_ALLOWED_ORIGINS'] = $value;
                putenv("CORS_ALLOWED_ORIGINS={$value}");
            }

            return require base_path('config/cors.php');
        } finally {
            unset($_SERVER['CORS_ALLOWED_ORIGINS'], $_ENV['CORS_ALLOWED_ORIGINS']);
            putenv('CORS_ALLOWED_ORIGINS');
            if ($previous !== null) {
                $_SERVER['CORS_ALLOWED_ORIGINS'] = $previous;
            }
            if ($previousEnv !== null) {
                $_ENV['CORS_ALLOWED_ORIGINS'] = $previousEnv;
            }
        }
    }
}
