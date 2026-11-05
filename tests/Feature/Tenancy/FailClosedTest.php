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
