<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\PwnedPasswordsVerifier;
use App\Support\UserPassword;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * uncompromised() con el verificador propio (R-010): 3 segundos de espera y, si Have
 * I Been Pwned no responde, la contraseña se acepta con un aviso en el log, sin traza
 * y sin la contraseña. El aviso lleva el código de cURL o el estado HTTP, y la consola
 * avisa que la contraseña no se verificó (R-011).
 */
class PwnedPasswordsVerifierTest extends TestCase
{
    private const OPTIONS = [
        '--name' => 'Veterinaria Central',
        '--legal-name' => 'Veterinaria Central S.A.',
        '--ruc' => '99999910',
        '--ruc-dv' => '9',
        '--admin-email' => 'admin@central.com.py',
        '--admin-first-name' => 'María',
        '--admin-last-name' => 'Benítez',
    ];

    /**
     * Comienzo de UserPassword::LEAK_CHECK_SKIPPED_WARNING: la consola puede partir el
     * mensaje completo en varias líneas.
     */
    private const CONSOLE_WARNING = 'No se pudo verificar si la contraseña aparece en filtraciones';

    /** @var list<MessageLogged> */
    private array $logged = [];

    private string|false $ignoreArgs;

    protected function setUp(): void
    {
        parent::setUp();

        // Sin la otra protección (argumentos ocultos en las trazas): este test prueba
        // que el verificador, por sí solo, no deja la contraseña en el log.
        $this->ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        Event::listen(MessageLogged::class, fn (MessageLogged $event) => $this->logged[] = $event);
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', (string) $this->ignoreArgs);
        parent::tearDown();
    }

    public function test_the_application_uses_its_own_verifier(): void
    {
        // El ValidationServiceProvider es diferido: se carga al pedir el validador.
        app('validator');

        $this->assertInstanceOf(PwnedPasswordsVerifier::class, app(UncompromisedVerifier::class));
    }

    public function test_it_waits_at_most_three_seconds_and_sends_only_the_hash_prefix(): void
    {
        $timeouts = [];
        Http::fake(['api.pwnedpasswords.com/*' => function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'] ?? null;

            return null; // Responde el simulado de TestCase.
        }]);

        $this->assertNull(UserPassword::error('Valida#Clave2026'));

        $this->assertSame([3], $timeouts);
        Http::assertSent(fn (Request $request) => $request->url() === PwnedPasswordsVerifier::URL . substr(strtoupper(sha1('Valida#Clave2026')), 0, 5)
            && $request->header('Add-Padding') === ['true']);
    }

    public function test_it_detects_a_password_above_the_threshold(): void
    {
        // El simulado de TestCase la informa 42 veces.
        $this->compromisedPasswords = ['Filtrada#Clave2026'];
        $verifier = app(PwnedPasswordsVerifier::class);

        $this->assertFalse($verifier->verify(['value' => 'Filtrada#Clave2026', 'threshold' => 0]));
        $this->assertFalse($verifier->verify(['value' => 'Filtrada#Clave2026', 'threshold' => 41]));
        $this->assertTrue($verifier->verify(['value' => 'Filtrada#Clave2026', 'threshold' => 42]));
        $this->assertTrue($verifier->verify(['value' => 'Otra#Clave2026', 'threshold' => 0]));
        $this->assertFalse($verifier->verify(['value' => '', 'threshold' => 0]));
        $this->assertSame([], $this->logged);
    }

    /**
     * Sin respuesta (ConnectionException) o con un error HTTP: se acepta, con un solo
     * aviso sin excepción ni contraseña.
     */
    #[DataProvider('unavailable')]
    public function test_when_the_service_does_not_answer_the_password_is_accepted_with_a_warning(bool|int $unavailable, array $context): void
    {
        $this->pwnedPasswordsUnavailable = $unavailable;
        $password = $this->password();

        $this->assertNull(UserPassword::error($password));
        $this->assertTrue(UserPassword::leakCheckSkipped());

        $this->assertCount(1, $this->logged);
        $this->assertSame('warning', $this->logged[0]->level);
        $this->assertSame(PwnedPasswordsVerifier::UNAVAILABLE_MESSAGE, $this->logged[0]->message);
        // Solo la clase y el código de cURL, o el estado HTTP (R-011): ni la excepción ni su mensaje.
        $this->assertSame($context, $this->logged[0]->context);
        $this->assertPasswordNotLogged($password);

        // La siguiente verificación, con el servicio de vuelta, ya no cuenta como salteada.
        $this->pwnedPasswordsUnavailable = false;
        $this->assertNull(UserPassword::error($this->password()));
        $this->assertFalse(UserPassword::leakCheckSkipped());
    }

    public static function unavailable(): array
    {
        return [
            'sin respuesta' => [true, ['reason' => ConnectionException::class, 'curl_error' => 28]],
            'error 503' => [503, ['status' => 503]],
            'error 429' => [429, ['status' => 429]],
        ];
    }

    public function test_with_the_service_up_the_check_is_not_skipped(): void
    {
        $this->assertNull(UserPassword::error($this->password()));
        $this->assertFalse(UserPassword::leakCheckSkipped());
    }

    /**
     * Con interacción, la consola avisa que la contraseña no se verificó (R-011); con
     * el servicio funcionando, no.
     */
    #[DataProvider('serviceStates')]
    public function test_clinic_create_with_interaction_warns_when_the_password_was_not_checked(bool $down): void
    {
        $this->pwnedPasswordsUnavailable = $down;
        $password = $this->password();

        $command = $this->artisan('clinic:create', self::OPTIONS)
            ->expectsQuestion('Contraseña del administrador', $password)
            ->expectsQuestion('Repetí la contraseña', $password);

        $down
            ? $command->expectsOutputToContain(self::CONSOLE_WARNING)
            : $command->doesntExpectOutputToContain(self::CONSOLE_WARNING);

        $command->assertExitCode(0);
    }

    /**
     * Sin interacción la contraseña es generada y no se consulta: no hay aviso.
     */
    public function test_clinic_create_without_interaction_does_not_warn(): void
    {
        $this->pwnedPasswordsUnavailable = true;

        $this->artisan('clinic:create', [...self::OPTIONS, '--no-interaction' => true])
            ->doesntExpectOutputToContain(self::CONSOLE_WARNING)
            ->assertExitCode(0);
    }

    #[DataProvider('serviceStates')]
    public function test_the_demo_seeder_warns_when_the_password_was_not_checked(bool $down): void
    {
        $this->pwnedPasswordsUnavailable = $down;
        config(['demo.password' => $this->password()]);

        $command = $this->artisan('db:seed', ['--class' => DemoSeeder::class]);

        $down
            ? $command->expectsOutputToContain(self::CONSOLE_WARNING)
            : $command->doesntExpectOutputToContain(self::CONSOLE_WARNING);

        $command->assertExitCode(0)->run();
        $this->assertSame(6, User::count());
    }

    public static function serviceStates(): array
    {
        return ['servicio caído' => [true], 'servicio funcionando' => [false]];
    }

    public function test_clinic_create_with_interaction_does_not_log_the_password_when_the_service_is_down(): void
    {
        $this->pwnedPasswordsUnavailable = true;
        $password = $this->password();

        $this->artisan('clinic:create', self::OPTIONS)
            ->expectsQuestion('Contraseña del administrador', $password)
            ->expectsQuestion('Repetí la contraseña', $password)
            ->assertExitCode(0);

        $this->assertTrue(Hash::check($password, User::where('email', 'admin@central.com.py')->sole()->password));
        $this->assertOnlyTheWarning();
        $this->assertPasswordNotLogged($password);
    }

    public function test_clinic_create_without_interaction_does_not_log_the_password_when_the_service_is_down(): void
    {
        $this->pwnedPasswordsUnavailable = true;

        $code = Artisan::call('clinic:create', [...self::OPTIONS, '--no-interaction' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $code, $output);
        $this->assertSame(1, preg_match('/guardala ahora\.\s*\n\s*(\S+)/u', $output, $match), $output);
        $this->assertPasswordNotLogged($match[1]);
        $this->assertSame([], array_filter($this->logged, fn (MessageLogged $event) => isset($event->context['exception'])));
    }

    public function test_the_demo_seeder_does_not_log_the_password_when_the_service_is_down(): void
    {
        $this->pwnedPasswordsUnavailable = true;
        $password = $this->password();
        config(['demo.password' => $password]);

        $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertSame(6, User::count());
        $this->assertOnlyTheWarning();
        $this->assertPasswordNotLogged($password);
    }

    /**
     * De 12 a 15 caracteres: la traza de PHP corta los textos a 15, así que una
     * contraseña de este largo quedaría entera.
     */
    private function password(): string
    {
        return 'Clave#' . random_int(100000, 999999);
    }

    private function assertOnlyTheWarning(): void
    {
        $this->assertNotSame([], $this->logged);

        foreach ($this->logged as $event) {
            $this->assertSame(PwnedPasswordsVerifier::UNAVAILABLE_MESSAGE, $event->message);
            $this->assertArrayNotHasKey('exception', $event->context);
        }
    }

    private function assertPasswordNotLogged(string $password): void
    {
        $text = '';

        foreach ($this->logged as $event) {
            $text .= $event->message . ' ' . json_encode($event->context) . ' ' . ($event->context['exception'] ?? '') . "\n";
        }

        $this->assertStringNotContainsString($password, $text);
        $this->assertStringNotContainsString(substr($password, 0, 12), $text);
        $this->assertStringNotContainsString(strtoupper(sha1($password)), $text);
        // Ni la URL ni el prefijo del hash, que trae el mensaje de la ConnectionException (R-011).
        $this->assertStringNotContainsString('pwnedpasswords', $text);
        $this->assertStringNotContainsString(substr(strtoupper(sha1($password)), 0, 5), $text);
    }
}
