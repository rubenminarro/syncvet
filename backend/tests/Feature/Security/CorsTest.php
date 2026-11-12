<?php

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * Revisión, H1: solo orígenes exactos (esquema://host[:puerto]). Todo lo que tenga un "*" se
     * descarta: php-cors toma "*" como "cualquier origen" y un "*" adentro como un patrón.
     *
     * @return array<string, array{0: string}>
     */
    public static function invalidOrigins(): array
    {
        return [
            'el comodín solo' => ['*'],
            'esquema con comodín' => ['https://*'],
            'subdominio con comodín' => ['*.syncvet.test'],
            'esquema y subdominio con comodín' => ['https://*.syncvet.test'],
            'comodín en el medio' => ['https://app*.syncvet.test'],
            'comodín en el puerto' => ['http://localhost:*'],
            'sin esquema' => ['localhost:5173'],
            'otro esquema' => ['ftp://syncvet.test'],
            'con ruta' => ['https://syncvet.test/app'],
            'con consulta' => ['https://syncvet.test?x=1'],
            'con usuario' => ['https://ana@syncvet.test'],
        ];
    }

    #[DataProvider('invalidOrigins')]
    public function test_an_origin_that_is_not_exact_is_discarded(string $origin): void
    {
        $config = $this->configWith("http://a.test, {$origin}");

        $this->assertSame(['http://a.test'], $config['allowed_origins']);
        $this->assertSame([$origin], $config['discarded_origins']);
        $this->assertSame([], $config['allowed_origins_patterns']);
    }

    /**
     * Lo que php-cors haría con cada comodín si llegara: cualquier origen https, o el que encaje
     * con el patrón. Descartado, ninguno pasa.
     */
    public function test_no_wildcard_reaches_the_cors_middleware(): void
    {
        config(['cors.allowed_origins' => $this->originsFrom('*, https://*, *.evil.test, https://*.evil.test, https://a*.evil.test')]);

        foreach (['https://evil.test', 'https://app.evil.test', 'https://abc.evil.test', self::ALLOWED] as $origin) {
            $this->withHeader('Origin', $origin)
                ->getJson('/api/me')
                ->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    /**
     * Revisión, H6: el origen se normaliza como lo manda el navegador (minúsculas, sin la barra
     * final); si no, nunca coincidiría.
     */
    public function test_the_origins_are_normalized(): void
    {
        $config = $this->configWith(' HTTP://LocalHost:5173/ , https://App.SyncVet.test/, http://[::1]:5173, http://localhost:5173');

        $this->assertSame(['http://localhost:5173', 'https://app.syncvet.test', 'http://[::1]:5173'], $config['allowed_origins']);
        $this->assertSame([], $config['discarded_origins']);

        config(['cors.allowed_origins' => $config['allowed_origins']]);
        $this->withHeader('Origin', 'https://app.syncvet.test')
            ->getJson('/api/me')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.syncvet.test');
    }

    /**
     * Revisión, H7: los descartados se avisan en el log como máximo una vez por hora (el arranque
     * corre en cada pedido).
     */
    public function test_the_discarded_origins_are_logged_at_most_once_an_hour(): void
    {
        Log::spy();
        config(['cors.discarded_origins' => ['https://*']]);

        $this->bootProvider();
        $this->bootProvider();
        $this->travel(59)->minutes();
        $this->bootProvider();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context = []) => str_contains($message, 'CORS_ALLOWED_ORIGINS') && $context === ['discarded' => ['https://*']]
        );

        $this->travel(2)->minutes();
        $this->bootProvider();

        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_without_discarded_origins_nothing_is_logged(): void
    {
        Log::spy();
        config(['cors.discarded_origins' => []]);

        $this->bootProvider();

        Log::shouldNotHaveReceived('warning');
    }

    /**
     * Si el caché no responde (sin la tabla cache, durante un migrate), el arranque no falla y avisa.
     */
    public function test_the_warning_does_not_break_the_boot_without_cache(): void
    {
        Log::spy();
        Cache::shouldReceive('add')->andThrow(new \RuntimeException('sin caché'));
        config(['cors.discarded_origins' => ['*']]);

        $this->bootProvider();

        Log::shouldHaveReceived('warning')->once();
    }

    private function bootProvider(): void
    {
        (new AppServiceProvider($this->app))->boot();
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
