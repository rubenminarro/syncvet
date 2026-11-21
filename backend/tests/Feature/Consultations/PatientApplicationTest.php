<?php

namespace Tests\Feature\Consultations;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Las aplicaciones (FASE 4, entrega 4.4; pregunta 12): cargar con y sin consulta (abierta, del mismo
 * paciente, de la ficha o del administrador), la próxima dosis, el paciente fallecido, el listado (filtros,
 * anuladas marcadas, sin N+1) y anular (ya anulada, consulta cerrada, sin consulta: el administrador o
 * quien la cargó el mismo día; anuladas con la consulta).
 */
class PatientApplicationTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithConsultations;

    private int $patient;

    private int $consultation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
        $appointment = $this->checkedIn();
        $this->patient = $this->patientIdOf($appointment);
        $this->actAsVet();
        $this->consultation = $this->opened(['appointment_id' => $appointment, 'diagnosis' => 'Control']);
    }

    private function load(array $body = [])
    {
        return $this->postJson("/api/patients/{$this->patient}/applications", [
            'type' => 'vaccine', 'name' => 'Antirrábica', 'applied_at' => '2026-11-10T08:50:00', ...$body,
        ]);
    }

    private function cancel(int $id, array $body = ['cancellation_reason' => 'Cargada por error'])
    {
        return $this->postJson("/api/patient-applications/{$id}/cancel", $body);
    }

    public function test_with_a_consultation(): void
    {
        $user = $this->actAsVet();

        $this->load([
            'consultation_id' => $this->consultation, 'lot_number' => 'L-2026-11', 'lot_expires_at' => '2027-06-30', 'dose' => '1 ml',
            'route' => 'Subcutánea', 'dose_number' => 2, 'next_dose_due_at' => '2027-11-10', 'notes' => 'Sin reacción',
        ])->assertCreated()
            ->assertJsonPath('data.type', ['value' => 'vaccine', 'label' => 'Vacuna'])
            ->assertJsonPath('data.applied_at', '2026-11-10T11:50:00.000000Z')
            ->assertJsonPath('data.consultation.id', $this->consultation)
            ->assertJsonPath('data.veterinarian.id', $this->vet->id)
            ->assertJsonPath('data.lot_expires_at', '2027-06-30')
            ->assertJsonPath('data.next_dose_due_at', '2027-11-10')
            ->assertJsonPath('data.dose_number', 2)
            ->assertJsonPath('data.created_by.id', $user->id)
            ->assertJsonPath('data.cancelled', false);

        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'patient_application')->count());
    }

    public function test_without_a_consultation_by_any_veterinarian_or_the_administrator(): void
    {
        $other = $this->veterinarianIn($this->branch);
        $this->actAsVet($other);
        $this->load(['type' => 'internal_deworming', 'name' => 'Comprimido'])->assertCreated()
            ->assertJsonPath('data.consultation', null)
            ->assertJsonPath('data.veterinarian.id', $other->id);

        $this->actAsAdmin();
        $this->load()->assertCreated()->assertJsonPath('data.veterinarian', null);
    }

    public function test_the_consultation_rules(): void
    {
        // Otro paciente.
        $other = $this->patientOf($this->clientIn($this->clinic));
        $this->postJson("/api/patients/{$other->id}/applications", [
            'type' => 'vaccine', 'name' => 'Antirrábica', 'applied_at' => '2026-11-10T08:50:00', 'consultation_id' => $this->consultation,
        ])->assertUnprocessable()->assertJsonPath('errors.consultation_id.0', 'La consulta no es de este paciente.');

        // Otra ficha.
        $this->actAsVet($this->veterinarianIn($this->branch));
        $this->assertErrorCode($this->load(['consultation_id' => $this->consultation]), 403, 'CONSULTATION_NOT_YOURS');

        // Cerrada o anulada.
        $this->actAsVet();
        foreach (['closed', 'cancelled'] as $status) {
            DB::table('consultations')->where('id', $this->consultation)->update(['status' => $status]);
            $this->assertErrorCode($this->load(['consultation_id' => $this->consultation]), 422, 'CONSULTATION_NOT_EDITABLE');
        }

        $this->assertSame(0, DB::table('patient_applications')->count());
    }

    public function test_a_deceased_patient_gets_no_application(): void
    {
        DB::table('patients')->where('id', $this->patient)->update(['deceased_at' => now()]);

        $this->assertErrorCode($this->load(), 422, 'PATIENT_DECEASED');
    }

    public static function invalid(): array
    {
        return [
            'sin tipo' => [['type' => null], 'type'],
            'tipo que no existe' => [['type' => 'surgery'], 'type'],
            'sin nombre' => [['name' => null], 'name'],
            'nombre de 201' => [['name' => str_repeat('a', 201)], 'name'],
            'sin fecha' => [['applied_at' => null], 'applied_at'],
            'fecha futura' => [['applied_at' => '2026-11-10T09:01:00'], 'applied_at'],
            'fecha mal formada' => [['applied_at' => '10/11/2026'], 'applied_at'],
            'próxima dosis el mismo día' => [['next_dose_due_at' => '2026-11-10'], 'next_dose_due_at'],
            'próxima dosis antes' => [['next_dose_due_at' => '2026-11-09'], 'next_dose_due_at'],
            'próxima dosis mal formada' => [['next_dose_due_at' => '10/11/2027'], 'next_dose_due_at'],
            'vencimiento mal formado' => [['lot_expires_at' => '2027-13-01'], 'lot_expires_at'],
            'número de dosis 0' => [['dose_number' => 0], 'dose_number'],
            'lote de 51' => [['lot_number' => str_repeat('a', 51)], 'lot_number'],
            'consulta que no existe' => [['consultation_id' => 999999], 'consultation_id'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_validation(array $body, string $field): void
    {
        $this->load($body)->assertUnprocessable()->assertJsonValidationErrors([$field]);
    }

    public function test_the_reception_lists_but_does_not_load(): void
    {
        $this->load()->assertCreated();
        $this->actAsReceptionist();

        $this->assertErrorCode($this->load(), 403, 'FORBIDDEN');
        $this->getJson("/api/patients/{$this->patient}/applications")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_list_filters_and_marks_the_cancelled_ones(): void
    {
        $vaccine = $this->load()->json('data.id');
        $this->load(['type' => 'external_deworming', 'name' => 'Pipeta', 'applied_at' => '2026-11-01T10:00:00'])->assertCreated();
        $this->actAsAdmin();
        $this->cancel($vaccine)->assertOk();

        $all = $this->getJson("/api/patients/{$this->patient}/applications")->assertOk()->json('data');
        $this->assertSame([$vaccine, true], [$all[0]['id'], $all[0]['cancelled']]);
        $this->assertSame([false, 'Cargada por error'], [$all[1]['cancelled'], $all[0]['cancellation_reason']]);

        $this->getJson("/api/patients/{$this->patient}/applications?type=external_deworming")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/patients/{$this->patient}/applications?from=2026-11-01&to=2026-11-01")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/patients/{$this->patient}/applications?type=surgery")->assertUnprocessable();
    }

    public function test_the_list_has_no_n_plus_1(): void
    {
        $this->load(['consultation_id' => $this->consultation])->assertCreated();
        $this->actAsAdmin();
        $one = $this->countQueries(fn () => $this->getJson("/api/patients/{$this->patient}/applications")->assertOk());

        $this->actAsVet();
        foreach (range(1, 4) as $n) {
            $this->load(['consultation_id' => $this->consultation, 'name' => "Vacuna {$n}"])->assertCreated();
        }

        $this->actAsAdmin();
        $this->assertSame($one, $this->countQueries(fn () => $this->getJson("/api/patients/{$this->patient}/applications")->assertOk()->assertJsonCount(5, 'data')));
    }

    public function test_cancel_with_an_open_consultation_by_its_veterinarian_or_the_administrator(): void
    {
        $first = $this->load(['consultation_id' => $this->consultation])->json('data.id');
        $second = $this->load(['consultation_id' => $this->consultation])->json('data.id');

        $this->actAsVet($this->veterinarianIn($this->branch));
        $this->assertErrorCode($this->cancel($first), 403, 'CONSULTATION_NOT_YOURS');

        $user = $this->actAsVet();
        $this->cancel($first)->assertOk()->assertJsonPath('data.cancelled', true)->assertJsonPath('data.cancelled_by.id', $user->id);
        $this->assertErrorCode($this->cancel($first), 422, 'PATIENT_APPLICATION_NOT_CANCELLABLE');

        $this->actAsAdmin();
        $this->cancel($second)->assertOk();
    }

    public function test_an_application_of_a_closed_consultation_is_cancelled_only_with_it(): void
    {
        $id = $this->load(['consultation_id' => $this->consultation])->json('data.id');
        $this->postJson("/api/consultations/{$this->consultation}/close")->assertOk();
        $this->actAsAdmin();

        $this->assertErrorCode($this->cancel($id), 422, 'CONSULTATION_NOT_EDITABLE');
        $this->assertNull(DB::table('patient_applications')->where('id', $id)->value('cancelled_at'));
    }

    /**
     * Sin consulta (decisión del usuario en la 4.4): el administrador siempre; el veterinario, solo la que
     * cargó él el mismo día en Asunción; si no, 403 PATIENT_APPLICATION_NOT_YOURS.
     */
    public function test_cancel_without_a_consultation(): void
    {
        $mine = $this->load()->json('data.id');
        $mineYesterday = $this->load()->json('data.id');
        DB::table('patient_applications')->where('id', $mineYesterday)->update(['created_at' => '2026-11-09 23:59:59']);
        $this->actAsVet($this->veterinarianIn($this->branch));
        $theirs = $this->load()->json('data.id');

        $this->actAsVet();
        $this->assertErrorCode($this->cancel($theirs), 403, 'PATIENT_APPLICATION_NOT_YOURS');
        $this->assertErrorCode($this->cancel($mineYesterday), 403, 'PATIENT_APPLICATION_NOT_YOURS');
        $this->cancel($mine)->assertOk();

        $this->actAsAdmin();
        $this->cancel($theirs)->assertOk();
        $this->cancel($mineYesterday)->assertOk();
    }

    /**
     * El borde de la medianoche (revisión de la 4.4): "el mismo día" es el día de created_at en Asunción,
     * con created_by igual al usuario. Con el reloj a las 00:00:10, una cargada a las 23:59:59 de ayer ya
     * no es suya para anular; una de las 00:00:00 de hoy, sí. La de otro usuario a las 00:00:00, tampoco.
     */
    public function test_the_same_day_rule_at_midnight(): void
    {
        $user = $this->actAsVet();
        $yesterday = $this->load()->json('data.id');
        $today = $this->load()->json('data.id');
        $someoneElse = $this->load()->json('data.id');
        DB::table('patient_applications')->where('id', $yesterday)->update(['created_at' => '2026-11-10 23:59:59']);
        DB::table('patient_applications')->where('id', $today)->update(['created_at' => '2026-11-11 00:00:00']);
        DB::table('patient_applications')->where('id', $someoneElse)->update([
            'created_at' => '2026-11-11 00:00:00', 'created_by' => $this->userWithRoles($this->clinic, ['veterinario'])->id,
        ]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-11 00:00:10', 'America/Asuncion'));
        $this->actingAs($user, 'sanctum');

        $this->assertErrorCode($this->cancel($yesterday), 403, 'PATIENT_APPLICATION_NOT_YOURS');
        $this->assertErrorCode($this->cancel($someoneElse), 403, 'PATIENT_APPLICATION_NOT_YOURS');
        $this->cancel($today)->assertOk();
    }

    /**
     * El detalle de la consulta lista sus aplicaciones (el detalle del diseño; revisión de la 4.4), también
     * para la recepción, con las anuladas marcadas.
     */
    public function test_the_consultation_detail_lists_its_applications(): void
    {
        $first = $this->load(['consultation_id' => $this->consultation, 'applied_at' => '2026-11-10T08:45:00'])->json('data.id');
        $second = $this->load(['consultation_id' => $this->consultation, 'type' => 'medication', 'name' => 'Analgésico'])->json('data.id');
        $this->load()->assertCreated();
        $this->cancel($second)->assertOk();
        $this->actAsReceptionist();

        $applications = $this->getJson("/api/consultations/{$this->consultation}")->assertOk()->json('data.applications');

        $this->assertSame([$first, $second], array_column($applications, 'id'));
        $this->assertSame([false, true], array_column($applications, 'cancelled'));
        $this->assertSame('medication', $applications[1]['type']['value']);
    }

    public static function invalidReason(): array
    {
        return ['sin motivo' => [null], 'de 2' => ['ab'], 'de 501' => [str_repeat('a', 501)]];
    }

    #[DataProvider('invalidReason')]
    public function test_the_reason_is_required(?string $reason): void
    {
        $id = $this->load()->json('data.id');
        $this->actAsAdmin();

        $this->cancel($id, ['cancellation_reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors(['cancellation_reason']);
    }

    public function test_the_reception_cannot_cancel(): void
    {
        $id = $this->load()->json('data.id');
        $this->actAsReceptionist();

        $this->assertErrorCode($this->cancel($id), 403, 'FORBIDDEN');
    }

    /**
     * Pendiente de la 4.2: al anular la consulta, sus aplicaciones se anulan con ella, con el mismo motivo;
     * las de otra consulta o sin consulta, no; una ya anulada conserva su anulación.
     */
    public function test_cancelling_the_consultation_cancels_its_applications(): void
    {
        $first = $this->load(['consultation_id' => $this->consultation])->json('data.id');
        $already = $this->load(['consultation_id' => $this->consultation])->json('data.id');
        $this->cancel($already, ['cancellation_reason' => 'Antes'])->assertOk();
        $without = $this->load()->json('data.id');
        $admin = $this->actAsAdmin();

        $this->postJson("/api/consultations/{$this->consultation}/cancel", ['cancellation_reason' => 'Paciente equivocado'])->assertOk();

        $rows = DB::table('patient_applications')->whereIn('id', [$first, $already, $without])->get()->keyBy('id');
        $this->assertSame([(int) $admin->id, 'Paciente equivocado'], [(int) $rows[$first]->cancelled_by, $rows[$first]->cancellation_reason]);
        $this->assertNotNull($rows[$first]->cancelled_at);
        $this->assertSame('Antes', $rows[$already]->cancellation_reason);
        $this->assertNull($rows[$without]->cancelled_at);
    }
}
