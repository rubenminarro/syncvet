<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DocumentSeries;
use App\Exceptions\ClinicContextMissingException;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Patient;
use App\Models\Species;
use App\Models\User;
use App\Services\DocumentSequenceService;
use App\Support\CurrentClinic;
use App\Support\ExecutionOrigin;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InteractsWithClinics;
use Tests\Feature\Tenancy\Fixtures\CountClientsJob;
use Tests\TestCase;

/**
 * Fallo cerrado: sin clínica en contexto, nada devuelve datos de todas las
 * clínicas. Responde el 500 genérico y queda en el log con su origen (R-005).
 */
class FailClosedTest extends TestCase
{
    use InteractsWithClinics;

    public static function scopedQueries(): array
    {
        return [
            'Client (ClinicScope)' => [fn () => Client::query()->get()],
            'Patient (ClinicScope)' => [fn () => Patient::count()],
            'Species (SharedCatalogScope)' => [fn () => Species::count()],
            'AuditLog (ClinicScope)' => [fn () => AuditLog::count()],
            'User::inClinic()' => [fn () => User::inClinic()->count()],
            'DocumentSequenceService' => [fn () => app(DocumentSequenceService::class)->next(DocumentSeries::PATIENT)],
        ];
    }

    #[DataProvider('scopedQueries')]
    public function test_query_without_clinic_throws(\Closure $query): void
    {
        $this->createClinic();
        $this->assertFalse(CurrentClinic::has());

        $this->expectException(ClinicContextMissingException::class);

        $query();
    }

    public function test_job_without_run_fails_and_reports_the_job(): void
    {
        $this->clientIn($this->createClinic());
        CountClientsJob::$result = null;

        try {
            Bus::dispatchSync(new CountClientsJob());
            $this->fail('Un job sin CurrentClinic::run consultó sin clínica.');
        } catch (ClinicContextMissingException $e) {
            $this->assertNull(CountClientsJob::$result);
            $this->assertSame(['origin' => 'job', 'job' => CountClientsJob::class], $e->context());
        }
    }

    public function test_job_with_run_sees_only_its_clinic(): void
    {
        $clinic = $this->createClinic();
        $this->clientIn($clinic);
        $this->clientIn($this->createClinic());

        Bus::dispatchSync(new CountClientsJob($clinic));

        $this->assertSame(1, CountClientsJob::$result);
        $this->assertFalse(CurrentClinic::has());
    }

    public function test_run_restores_the_previous_clinic(): void
    {
        $first = $this->createClinic();
        $second = $this->createClinic();

        CurrentClinic::run($first, function () use ($first, $second) {
            CurrentClinic::run($second, fn () => $this->assertSame($second->id, CurrentClinic::id()));
            $this->assertSame($first->id, CurrentClinic::id());

            try {
                CurrentClinic::run($second, fn () => throw new RuntimeException('falla'));
            } catch (RuntimeException) {
            }

            $this->assertSame($first->id, CurrentClinic::id());
        });

        $this->assertFalse(CurrentClinic::has());
    }

    /**
     * Con Artisan::call, argv es el del proceso que llama: el origen sale del evento
     * CommandStarting, y deja de informarse cuando el comando termina (aunque falle).
     */
    public function test_command_without_run_fails_and_reports_the_command(): void
    {
        $this->clientIn($this->createClinic());
        Artisan::command('testing:count-clients', fn () => Client::count());

        // Fuera de los tests, Laravel convierte los eventos de Symfony en
        // CommandStarting/CommandFinished; en los tests no. Se activa como en
        // producción y se descarta el Artisan que ya creó la siembra, sin eventos.
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        $kernel->setArtisan(null);

        try {
            Artisan::call('testing:count-clients');
            $this->fail('Un comando sin CurrentClinic::run consultó sin clínica.');
        } catch (ClinicContextMissingException $e) {
            $this->assertSame(['origin' => 'console', 'command' => 'testing:count-clients'], $e->context());
        }

        $this->assertNotSame('testing:count-clients', ExecutionOrigin::describe()['command'] ?? null);
    }

    /**
     * SetCurrentClinic fija la clínica solo durante el pedido: al terminar, bien o
     * con error, el contexto vuelve al anterior (nada queda fijado para el código
     * que corre después, como un job sync o el pedido siguiente del mismo proceso).
     */
    public function test_request_restores_the_clinic_context_when_it_ends(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'clinic'])->group(function () {
            Route::get('/api/testing/clinic-ok', fn () => response()->json(['clinic_id' => CurrentClinic::id()]));
            Route::get('/api/testing/clinic-error', fn () => throw new RuntimeException('falla'));
        });
        $clinic = $this->createClinic();
        $this->actingIn($clinic);

        $this->getJson('/api/testing/clinic-ok')->assertOk()->assertJson(['clinic_id' => $clinic->id]);
        $this->assertFalse(CurrentClinic::has(), 'El pedido dejó la clínica fijada.');

        $this->assertErrorCode($this->getJson('/api/testing/clinic-error'), 500, 'INTERNAL_SERVER_ERROR');
        $this->assertFalse(CurrentClinic::has(), 'El pedido con error dejó la clínica fijada.');

        $previous = $this->createClinic();
        CurrentClinic::run($previous, function () use ($previous) {
            $this->getJson('/api/testing/clinic-ok')->assertOk();
            $this->assertSame($previous->id, CurrentClinic::id(), 'El pedido no restauró la clínica anterior.');
        });
    }

    public function test_request_without_clinic_responds_500_and_logs_the_route(): void
    {
        Route::middleware('api')->get('/api/testing/fail-closed', fn () => response()->json(['count' => Client::count()]));
        Log::spy();

        $response = $this->getJson('/api/testing/fail-closed');

        $this->assertErrorCode($response, 500, 'INTERNAL_SERVER_ERROR');
        $response->assertJsonMissingPath('trace')->assertJsonMissingPath('exception');

        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) =>
            ($context['exception'] ?? null) instanceof ClinicContextMissingException
            && ($context['origin'] ?? null) === 'http'
            && ($context['method'] ?? null) === 'GET'
            && ($context['path'] ?? null) === '/api/testing/fail-closed'
        )->once();
    }
}
