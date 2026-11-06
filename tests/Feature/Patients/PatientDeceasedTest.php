<?php

namespace Tests\Feature\Patients;

use App\Models\Clinic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Paciente fallecido (CLAUDE.md §6.2; FASE 1, entrega 1.3), con el rol
 * recepcionista.
 */
class PatientDeceasedTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    public function test_a_deceased_patient_only_accepts_the_date_and_the_notes(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic), ['deceased_at' => now()->subDay()]);

        $response = $this->patchJson("/api/patients/{$patient->id}", ['name' => 'Otro', 'notes' => 'x']);
        $this->assertErrorCode($response, 422, 'PATIENT_DECEASED');
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'name' => 'Paciente', 'notes' => null]);

        $this->patchJson("/api/patients/{$patient->id}", ['notes' => 'Falleció en la clínica.', 'deceased_at' => now()->subDays(2)->toISOString()])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Falleció en la clínica.')
            ->assertJsonPath('data.is_deceased', true);
    }

    public function test_removing_the_date_reactivates_the_patient(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic), ['deceased_at' => now()->subDay()]);

        $this->patchJson("/api/patients/{$patient->id}", ['deceased_at' => null])
            ->assertOk()
            ->assertJsonPath('data.is_deceased', false)
            ->assertJsonPath('data.deceased_at', null);

        $this->patchJson("/api/patients/{$patient->id}", ['name' => 'Bandido'])->assertOk()->assertJsonPath('data.name', 'Bandido');
    }

    public function test_a_future_date_is_rejected(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));

        $this->patchJson("/api/patients/{$patient->id}", ['deceased_at' => now()->addHour()->toISOString()])
            ->assertUnprocessable()
            ->assertJsonPath('errors.deceased_at.0', 'La fecha de fallecimiento no puede ser futura.');
    }

    /**
     * La API recibe la hora en UTC o con su desplazamiento, y la base guarda la de
     * America/Asuncion (UTC-3). Antes se guardaba la hora UTC como local.
     */
    #[DataProvider('deceasedTimes')]
    public function test_the_date_is_stored_in_the_clinic_timezone(string $sent, string $stored, string $answered): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));

        $this->patchJson("/api/patients/{$patient->id}", ['deceased_at' => $sent])
            ->assertOk()
            ->assertJsonPath('data.deceased_at', $answered);

        $this->assertSame($stored, Carbon::parse(DB::table('patients')->where('id', $patient->id)->value('deceased_at'))->format('Y-m-d H:i:s'));
    }

    public static function deceasedTimes(): array
    {
        return [
            'UTC' => ['2025-11-05T13:00:00Z', '2025-11-05 10:00:00', '2025-11-05T13:00:00.000000Z'],
            'UTC con milisegundos' => ['2025-11-05T13:00:00.000Z', '2025-11-05 10:00:00', '2025-11-05T13:00:00.000000Z'],
            'con desplazamiento' => ['2025-11-05T10:00:00-03:00', '2025-11-05 10:00:00', '2025-11-05T13:00:00.000000Z'],
            'sin zona: hora local' => ['2025-11-05 10:00:00', '2025-11-05 10:00:00', '2025-11-05T13:00:00.000000Z'],
        ];
    }

    public function test_the_list_hides_deceased_patients_unless_asked(): void
    {
        $client = $this->clientIn($this->clinic);
        $alive = $this->patientOf($client, ['name' => 'Vivo']);
        $deceased = $this->patientOf($client, ['name' => 'Fallecido', 'deceased_at' => now()->subDay()]);

        $this->getJson('/api/patients')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $alive->id);
        $this->getJson('/api/patients?include_deceased=0')->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/patients?include_deceased=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $deceased->id)
            ->assertJsonPath('data.0.is_deceased', true);
    }

    public function test_an_invalid_include_deceased_is_rejected(): void
    {
        $this->getJson('/api/patients?include_deceased=quizas')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include_deceased']);
    }
}
