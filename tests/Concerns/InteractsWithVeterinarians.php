<?php

namespace Tests\Concerns;

use App\Enums\ConsultationItemType;
use App\Enums\DocumentSeries;
use App\Enums\DocumentTemplateType;
use App\Enums\PatientApplicationType;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Veterinarian;
use App\Services\DocumentSequenceService;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Veterinarios (FASE 2, entrega 2.3). Los tests usan un usuario con los permisos de
 * veterinarios, no el administrador, que pasa todo por Gate::before.
 */
trait InteractsWithVeterinarians
{
    use InteractsWithUsers;

    protected const VETERINARIAN_PERMISSIONS = [
        'veterinarians.index', 'veterinarians.store', 'veterinarians.show', 'veterinarians.update', 'veterinarians.destroy',
    ];

    protected function actAsVeterinarianManager(Clinic $clinic): User
    {
        return $this->actingIn($clinic, self::VETERINARIAN_PERMISSIONS);
    }

    /**
     * Un usuario activo de la clínica con el rol veterinario.
     *
     * @param list<Branch> $branches
     */
    protected function veterinarianUser(Clinic $clinic, array $branches = [], array $attributes = []): User
    {
        return $this->userWithRoles($clinic, ['veterinario'], $branches, $attributes);
    }

    /**
     * Ficha de veterinario con el código de la serie, sin pasar por la API.
     */
    protected function veterinarianOf(User $user, array $attributes = []): Veterinarian
    {
        return CurrentClinic::run($user->clinic, fn () => DB::transaction(
            fn () => app(DocumentSequenceService::class)->create(
                DocumentSeries::VETERINARIAN,
                fn (string $code) => Veterinarian::create(['user_id' => $user->id, 'code' => $code, ...$attributes])
            )
        ));
    }

    /**
     * Una fila de cada tabla de uso que apunta al veterinario (VeterinarianService::USAGE),
     * con las claves foráneas que exige cada una, sin los Services de las fases 3 y 4.
     */
    protected function usageRowFor(Veterinarian $veterinarian, string $table): void
    {
        $clinic = Clinic::findOrFail($veterinarian->clinic_id);
        $branchId = Branch::withoutGlobalScopes()->where('clinic_id', $clinic->id)->value('id') ?? $this->defaultBranchIn($clinic)->id;
        $userId = $veterinarian->user_id;
        $base = ['clinic_id' => $clinic->id, 'created_at' => now(), 'updated_at' => now()];

        $patient = $this->patientOf($this->clientIn($clinic));

        $consultation = fn (?int $veterinarianId) => DB::table('consultations')->insertGetId([
            ...$base,
            'code' => 'CON-' . str_pad((string) (DB::table('consultations')->count() + 1), 6, '0', STR_PAD_LEFT),
            'branch_id' => $branchId,
            'patient_id' => $patient->id,
            'client_id' => $patient->client_id,
            'veterinarian_id' => $veterinarianId ?? $this->otherVeterinarianId($clinic),
            'consulted_at' => now(),
            'created_by' => $userId,
        ]);

        $scheduleId = fn (?int $veterinarianId) => DB::table('schedules')->insertGetId([
            ...$base, 'branch_id' => $branchId, 'name' => 'Agenda de prueba', 'veterinarian_id' => $veterinarianId,
        ]);

        match ($table) {
            'schedules' => $scheduleId($veterinarian->id),
            'appointments' => DB::table('appointments')->insert([
                ...$base,
                'branch_id' => $branchId,
                'schedule_id' => $scheduleId(null),
                'client_id' => $patient->client_id,
                'patient_id' => $patient->id,
                'veterinarian_id' => $veterinarian->id,
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addMinutes(30),
                'created_by' => $userId,
            ]),
            'consultations' => $consultation($veterinarian->id),
            'consultation_items' => DB::table('consultation_items')->insert([
                ...$base,
                'consultation_id' => $consultation(null),
                'type' => ConsultationItemType::SERVICE->value,
                'veterinarian_id' => $veterinarian->id,
                'description' => 'Consulta general',
                'unit_price' => '100000.00',
                'total' => '100000.00',
            ]),
            'patient_applications' => DB::table('patient_applications')->insert([
                ...$base,
                'patient_id' => $patient->id,
                'veterinarian_id' => $veterinarian->id,
                'type' => PatientApplicationType::VACCINE->value,
                'name' => 'Antirrábica',
                'applied_at' => now(),
                'created_by' => $userId,
            ]),
            'patient_documents' => DB::table('patient_documents')->insert([
                ...$base,
                'branch_id' => $branchId,
                'patient_id' => $patient->id,
                'type' => DocumentTemplateType::PRESCRIPTION->value,
                'title' => 'Receta',
                'content' => '<p>Receta</p>',
                'verification_code' => (string) Str::uuid(),
                'veterinarian_id' => $veterinarian->id,
                'created_by' => $userId,
                'issued_at' => now(),
            ]),
        };
    }

    /**
     * Otra ficha de la clínica, para las filas que exigen un veterinario y no son el uso
     * que se prueba (la consulta de un ítem).
     */
    private function otherVeterinarianId(Clinic $clinic): int
    {
        return $this->veterinarianOf($this->veterinarianUser($clinic))->id;
    }
}
