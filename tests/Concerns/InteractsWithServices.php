<?php

namespace Tests\Concerns;

use App\Enums\ConsultationItemType;
use App\Enums\DocumentSeries;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use App\Services\DocumentSequenceService;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;

/**
 * Servicios (FASE 2, entrega 2.4). Los tests usan un usuario con los permisos de
 * servicios, no el administrador, que pasa todo por Gate::before.
 */
trait InteractsWithServices
{
    use InteractsWithVeterinarians;

    protected const SERVICE_PERMISSIONS = [
        'services.index', 'services.store', 'services.show', 'services.update', 'services.destroy',
    ];

    protected function actAsServiceManager(Clinic $clinic): User
    {
        return $this->actingIn($clinic, self::SERVICE_PERMISSIONS);
    }

    /**
     * Servicio con el código de la serie, sin pasar por la API.
     */
    protected function serviceIn(Clinic $clinic, array $attributes = []): Service
    {
        return CurrentClinic::run($clinic, fn () => DB::transaction(
            fn () => app(DocumentSequenceService::class)->create(
                DocumentSeries::SERVICE,
                fn (string $code) => Service::create(['name' => 'Consulta general', 'standard_price' => '150000', ...$attributes, 'code' => $code])
            )
        ));
    }

    /**
     * Una fila de cada tabla de uso que apunta al servicio (ServiceCatalogService::USAGE),
     * con las claves foráneas que exige cada una, sin los Services de las fases 3 y 4.
     */
    protected function serviceUsageRowFor(Service $service, string $table): void
    {
        $clinic = Clinic::findOrFail($service->clinic_id);
        $branchId = Branch::withoutGlobalScopes()->where('clinic_id', $clinic->id)->value('id') ?? $this->defaultBranchIn($clinic)->id;
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic));
        $base = ['clinic_id' => $clinic->id, 'created_at' => now(), 'updated_at' => now()];
        $patient = $this->patientOf($this->clientIn($clinic));

        $schedule = fn (?int $serviceId) => DB::table('schedules')->insertGetId([
            ...$base, 'branch_id' => $branchId, 'name' => 'Agenda de prueba', 'service_id' => $serviceId,
        ]);

        match ($table) {
            'schedules' => $schedule($service->id),
            'appointments' => DB::table('appointments')->insert([
                ...$base,
                'branch_id' => $branchId,
                'schedule_id' => $schedule(null),
                'client_id' => $patient->client_id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addMinutes(30),
                'created_by' => $veterinarian->user_id,
            ]),
            'consultation_items' => DB::table('consultation_items')->insert([
                ...$base,
                'consultation_id' => DB::table('consultations')->insertGetId([
                    ...$base,
                    'code' => 'CON-' . str_pad((string) (DB::table('consultations')->count() + 1), 6, '0', STR_PAD_LEFT),
                    'branch_id' => $branchId,
                    'patient_id' => $patient->id,
                    'client_id' => $patient->client_id,
                    'veterinarian_id' => $veterinarian->id,
                    'consulted_at' => now(),
                    'created_by' => $veterinarian->user_id,
                ]),
                'type' => ConsultationItemType::SERVICE->value,
                'service_id' => $service->id,
                'description' => $service->name,
                'unit_price' => '150000.00',
                'total' => '150000.00',
            ]),
        };
    }
}
