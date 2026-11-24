<?php

namespace Tests\Feature\Consultations;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * El historial del paciente (FASE 4, entrega 4.6; pregunta 18): GET patients/{patient}/history, sus
 * consultas y sus aplicaciones juntas, de la más reciente a la más vieja, paginadas de a 20 (hasta 100);
 * las anuladas, marcadas; también de un fallecido. Con consultations.show-clinical, el diagnóstico y el
 * peso de cada consulta; la recepción, el resumen administrativo. Exige consultations.index y
 * patient-applications.index, los dos.
 *
 * El reloj, el martes 10/11/2026 a las 09:00 de Asunción (setUpClinic).
 */
class PatientHistoryTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithConsultations;

    private int $patient;

    /** La consulta desde el turno de hoy, a las 09:00. */
    private int $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
        $appointment = $this->checkedIn();
        $this->patient = $this->patientIdOf($appointment);
        $this->actAsVet();
        $this->today = $this->opened(['appointment_id' => $appointment, 'diagnosis' => 'Otitis', 'reason' => 'Se rasca la oreja']);
        $this->at($this->today, '2026-11-10 09:00:00');
    }

    /**
     * Consultas y aplicaciones juntas, por la fecha descendente (consulted_at o applied_at), cada una con
     * su tipo, su fecha en UTC y su contenido; las de otro paciente no están.
     */
    public function test_consultations_and_applications_together_newest_first(): void
    {
        $withConsultation = $this->applied('2026-11-10T08:50:00', ['consultation_id' => $this->today]);
        $older = $this->opened(['patient_id' => $this->patient, 'reason' => 'Vómitos']);
        $this->at($older, '2026-11-05 10:00:00');
        $oldest = $this->applied('2026-11-01T08:00:00', ['type' => 'internal_deworming', 'name' => 'Praziquantel']);
        // Otro paciente de la clínica: no aparece.
        $this->opened(['appointment_id' => $this->checkedIn(startsAt: $this->today('07:30'))]);

        $response = $this->history()->assertOk()->assertJsonCount(4, 'data');

        $this->assertSame(
            [['consultation', $this->today], ['application', $withConsultation], ['consultation', $older], ['application', $oldest]],
            $this->order($response->json('data')),
        );
        $response->assertJsonPath('data.0.occurred_at', '2026-11-10T12:00:00.000000Z')
            ->assertJsonPath('data.0.cancelled', false)
            ->assertJsonPath('data.0.application', null)
            ->assertJsonPath('data.0.consultation.reason', 'Se rasca la oreja')
            ->assertJsonPath('data.0.consultation.veterinarian.id', $this->vet->id)
            ->assertJsonPath('data.0.consultation.status.value', 'open')
            ->assertJsonPath('data.1.occurred_at', '2026-11-10T11:50:00.000000Z')
            ->assertJsonPath('data.1.consultation', null)
            ->assertJsonPath('data.1.application.consultation.id', $this->today)
            ->assertJsonPath('data.3.application.type.value', 'internal_deworming')
            ->assertJsonPath('meta.pagination.total', 4);
    }

    /**
     * En la misma fecha, la consulta antes que la aplicación, y entre dos del mismo tipo, la de id mayor.
     */
    public function test_the_same_instant_puts_the_consultation_first_and_then_the_newest_id(): void
    {
        $first = $this->applied('2026-11-10T09:00:00');
        $second = $this->applied('2026-11-10T09:00:00');

        $this->assertSame(
            [['consultation', $this->today], ['application', $second], ['application', $first]],
            $this->order($this->history()->assertOk()->json('data')),
        );
    }

    public static function pages(): array
    {
        return [
            'de a 20 por defecto' => [[], 20, 2],
            'per_page 10, página 3' => [['per_page' => 10, 'page' => 3], 5, 3],
            'hasta 100' => [['per_page' => 100], 25, 1],
        ];
    }

    /**
     * Paginado de a 20 (hasta 100), con meta.pagination; 25 elementos: la consulta y 24 aplicaciones.
     */
    #[DataProvider('pages')]
    public function test_pagination(array $query, int $count, int $lastPage): void
    {
        for ($day = 1; $day <= 24; $day++) {
            $this->applied(sprintf('2026-10-%02dT08:00:00', $day));
        }

        $this->history($query)->assertOk()
            ->assertJsonCount($count, 'data')
            ->assertJsonPath('meta.pagination.total', 25)
            ->assertJsonPath('meta.pagination.per_page', $query['per_page'] ?? 20)
            ->assertJsonPath('meta.pagination.last_page', $lastPage);
    }

    public static function invalidPages(): array
    {
        return [
            'per_page 0' => [['per_page' => 0], 'per_page'],
            'per_page 101' => [['per_page' => 101], 'per_page'],
            'per_page texto' => [['per_page' => 'diez'], 'per_page'],
            'page 0' => [['page' => 0], 'page'],
        ];
    }

    #[DataProvider('invalidPages')]
    public function test_an_invalid_page_is_422(array $query, string $field): void
    {
        $response = $this->history($query);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey($field, $response->json('errors'));
    }

    /**
     * La consulta y la aplicación anuladas siguen en el historial, marcadas.
     */
    public function test_cancelled_ones_stay_marked(): void
    {
        $application = $this->applied('2026-11-10T08:50:00');
        $this->postJson("/api/patient-applications/{$application}/cancel", ['cancellation_reason' => 'Cargada por error'])->assertOk();
        $this->actAsAdmin();
        $this->postJson("/api/consultations/{$this->today}/cancel", ['cancellation_reason' => 'Paciente equivocado'])->assertOk();

        $this->history()->assertOk()
            ->assertJsonPath('data.0.cancelled', true)
            ->assertJsonPath('data.0.consultation.status.value', 'cancelled')
            ->assertJsonPath('data.1.cancelled', true)
            ->assertJsonPath('data.1.application.cancelled', true)
            ->assertJsonPath('data.1.application.cancellation_reason', 'Cargada por error');
    }

    /**
     * El diagnóstico y el peso de cada consulta (la evolución del peso), con consultations.show-clinical: el
     * veterinario y el administrador. La recepción ve el resumen administrativo y las aplicaciones.
     */
    public function test_the_diagnosis_and_the_weight_only_with_the_clinical_permission(): void
    {
        DB::table('consultations')->where('id', $this->today)->update(['weight_kg' => '12.40']);
        $older = $this->opened(['patient_id' => $this->patient, 'diagnosis' => 'Gastritis']);
        DB::table('consultations')->where('id', $older)->update(['weight_kg' => '11.90']);
        $this->at($older, '2026-11-05 10:00:00');
        $this->applied('2026-11-01T08:00:00');

        foreach (['vet' => fn () => $this->actAsVet(), 'admin' => fn () => $this->actAsAdmin()] as $who => $act) {
            $act();
            $data = $this->history()->assertOk()->json('data');
            $this->assertSame(['Otitis', 'Gastritis'], [$data[0]['consultation']['diagnosis'], $data[1]['consultation']['diagnosis']], $who);
            $this->assertSame(['12.40', '11.90'], [$data[0]['consultation']['weight_kg'], $data[1]['consultation']['weight_kg']], $who);
        }

        $this->actAsReceptionist();
        $data = $this->history()->assertOk()->assertJsonCount(3, 'data')->json('data');

        foreach ([0, 1] as $i) {
            $this->assertArrayNotHasKey('diagnosis', $data[$i]['consultation']);
            $this->assertArrayNotHasKey('weight_kg', $data[$i]['consultation']);
        }

        $this->assertSame('Se rasca la oreja', $data[0]['consultation']['reason']);
        $this->assertSame('Antirrábica', $data[2]['application']['name']);
        $this->assertStringNotContainsString('Otitis', json_encode($data));
    }

    /**
     * El historial de un paciente fallecido sigue visible (pregunta 11).
     */
    public function test_a_deceased_patient_keeps_its_history(): void
    {
        $this->applied('2026-11-10T08:50:00');
        DB::table('patients')->where('id', $this->patient)->update(['deceased_at' => now()]);

        $this->history()->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_patient_without_records_has_an_empty_history(): void
    {
        $other = $this->patientIdOf($this->checkedIn(startsAt: $this->today('10:00')));

        $this->getJson("/api/patients/{$other}/history")->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public static function partialPermissions(): array
    {
        return [
            'solo consultations.index' => [['consultations.index'], 403],
            'solo patient-applications.index' => [['patient-applications.index'], 403],
            'los dos' => [['consultations.index', 'patient-applications.index'], 200],
        ];
    }

    /**
     * Exige los dos permisos a la vez: con uno solo, 403 FORBIDDEN.
     */
    #[DataProvider('partialPermissions')]
    public function test_it_requires_both_permissions(array $permissions, int $status): void
    {
        $role = Role::create(['name' => 'historial-prueba', 'guard_name' => 'api']);
        $role->permissions()->attach(Permission::where('guard_name', 'api')->whereIn('name', $permissions)->pluck('id'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->for($this->clinic)->create();
        $user->roles()->attach($role->id);
        Sanctum::actingAs($user);

        $response = $this->history();

        $status === 200 ? $response->assertOk() : $this->assertErrorCode($response, 403, 'FORBIDDEN');
    }

    /**
     * Sin N+1: la misma cantidad de consultas con 2 elementos que con 8 (consultas y aplicaciones).
     */
    public function test_without_n_plus_one(): void
    {
        $this->applied('2026-11-10T08:50:00', ['consultation_id' => $this->today]);
        $one = $this->countQueries(fn () => $this->history()->assertOk()->assertJsonCount(2, 'data'));

        for ($day = 1; $day <= 3; $day++) {
            $this->applied(sprintf('2026-11-%02dT08:00:00', $day), ['consultation_id' => null]);
            $id = $this->opened(['patient_id' => $this->patient]);
            $this->at($id, sprintf('2026-11-0%d 10:00:00', $day + 4));
        }

        $this->assertSame($one, $this->countQueries(fn () => $this->history()->assertOk()->assertJsonCount(8, 'data')));
    }

    private function history(array $query = [])
    {
        return $this->getJson("/api/patients/{$this->patient}/history" . ($query === [] ? '' : '?' . http_build_query($query)));
    }

    /**
     * Carga una aplicación del paciente por la API (con el usuario actual) y devuelve su id.
     */
    private function applied(string $appliedAt, array $body = []): int
    {
        return $this->postJson("/api/patients/{$this->patient}/applications", [
            'type' => 'vaccine', 'name' => 'Antirrábica', 'applied_at' => $appliedAt, ...$body,
        ])->assertCreated()->json('data.id');
    }

    /**
     * La fecha de la consulta, directo en la base (hora de Asunción).
     */
    private function at(int $consultation, string $consultedAt): void
    {
        DB::table('consultations')->where('id', $consultation)->update(['consulted_at' => $consultedAt]);
    }

    /**
     * @return list<array{0: string, 1: int}> el tipo y el id de cada elemento
     */
    private function order(array $data): array
    {
        return array_map(fn (array $item) => [$item['type'], $item[$item['type']]['id']], $data);
    }
}
