<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicStatus;
use App\Exceptions\AuthException;
use App\Exceptions\ClinicContextMissingException;
use App\Exceptions\ClinicException;
use App\Services\AuthService;
use App\Services\ClinicService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * La contraseña nunca llega al log: los errores de negocio no se reportan
 * (dontReport de AppException) y, si una traza se escribiera igual, la contraseña
 * va marcada con #[\SensitiveParameter].
 *
 * Las trazas solo muestran los argumentos con zend.exception_ignore_args=Off (el
 * valor de desarrollo): los tests lo fijan así para no depender del php.ini.
 */
class AuthLoggingTest extends TestCase
{
    use InteractsWithAuth;

    private const PASSWORD = 'clave-secreta-del-test';

    private string|false $ignoreArgs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', (string) $this->ignoreArgs);
        parent::tearDown();
    }

    public function test_failed_and_suspended_logins_write_nothing_to_the_log(): void
    {
        $logged = $this->captureLog();
        $active = $this->userWithRole($this->createClinic(), 'recepcionista', ['password' => self::PASSWORD]);
        $suspended = $this->userWithRole($this->createClinic(['status' => ClinicStatus::SUSPENDED]), 'recepcionista', ['password' => self::PASSWORD]);

        $this->assertErrorCode($this->login($active->email, self::PASSWORD . '-mal'), 422, 'INVALID_CREDENTIALS');
        $this->assertErrorCode($this->login('nadie@example.com', self::PASSWORD), 422, 'INVALID_CREDENTIALS');
        $this->assertErrorCode($this->login($suspended->email, self::PASSWORD), 403, 'CLINIC_NOT_AVAILABLE');

        $this->assertSame([], $logged->entries);
    }

    public function test_business_errors_are_not_reported_but_a_missing_clinic_context_is(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $this->assertFalse($handler->shouldReport(new AuthException('x', 422, 'INVALID_CREDENTIALS')));
        $this->assertFalse($handler->shouldReport(new ClinicException('x', 403, 'CLINIC_NOT_AVAILABLE')));
        $this->assertTrue($handler->shouldReport(new ClinicContextMissingException()));
    }

    /**
     * Segunda capa: la traza de la excepción (lo que el log escribiría) no tiene la
     * contraseña, ni con credenciales incorrectas ni con la correcta de una clínica
     * suspendida.
     */
    public function test_the_password_is_not_in_the_exception_trace(): void
    {
        $active = $this->userWithRole($this->createClinic(), 'recepcionista', ['password' => self::PASSWORD]);
        $suspended = $this->userWithRole($this->createClinic(['status' => ClinicStatus::SUSPENDED]), 'recepcionista', ['password' => self::PASSWORD]);

        foreach ([[$active->email, self::PASSWORD . '-mal'], [$suspended->email, self::PASSWORD]] as [$email, $password]) {
            try {
                app(AuthService::class)->login($email, $password, '127.0.0.1');
                $this->fail('El login debía fallar.');
            } catch (AuthException|ClinicException $e) {
                $this->assertStringContainsString('AuthService->login(', (string) $e, 'La traza debe mostrar la llamada.');
                $this->assertStringNotContainsString(substr(self::PASSWORD, 0, 10), (string) $e);
            }
        }
    }

    /**
     * ClinicService::create recibe la contraseña del administrador dentro de un array.
     * getTraceAsString() muestra "Array", pero getTrace() conserva el array completo
     * (lo que usaría un reporte de errores externo): con #[\SensitiveParameter] la
     * traza guarda un SensitiveParameterValue en su lugar.
     */
    public function test_the_admin_password_is_not_in_the_clinic_service_trace(): void
    {
        try {
            app(ClinicService::class)->create(
                ['name' => 'Central', 'legal_name' => 'Central S.A.', 'ruc' => '99999910', 'ruc_dv' => '8'],
                ['email' => 'ana@central.com.py', 'first_name' => 'Ana', 'last_name' => 'Benítez', 'password' => self::PASSWORD]
            );
            $this->fail('El alta debía fallar por el RUC.');
        } catch (ClinicException $e) {
            $this->assertSame('CLINIC_RUC_INVALID', $e->getErrorCode());

            $frame = collect($e->getTrace())->first(
                fn (array $frame) => ($frame['class'] ?? null) === ClinicService::class && $frame['function'] === 'create'
            );
            $this->assertNotNull($frame, 'La traza debe mostrar la llamada.');
            $this->assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][1]);
            $this->assertStringNotContainsString(self::PASSWORD, print_r($frame['args'], true));
        }
    }

    private function captureLog(): object
    {
        $logged = new class
        {
            /** @var list<string> */
            public array $entries = [];
        };

        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
            $exception = $event->context['exception'] ?? null;
            $logged->entries[] = $event->message . ' ' . ($exception instanceof \Throwable ? (string) $exception : json_encode($event->context));
        });

        return $logged;
    }
}
