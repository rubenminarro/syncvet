<?php

namespace Tests\Feature\Consultations;

use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * El listado de consultas (FASE 4, entrega 4.1): de toda la clínica, por consulted_at
 * descendente, con sus filtros (rango de hasta 31 días, por defecto los últimos 30), la búsqueda
 * literal, la vista de la recepción (sin el diagnóstico) y una cantidad fija de consultas (sin N+1).
 */
class ConsultationIndexTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithConsultations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
    }

    /**
     * Una consulta sin turno del paciente, con la fecha y el estado.
     */
    private function consultation(string $consultedAt, string $status = 'open', array $patient = []): int
    {
        $this->actAsVet();
        $id = $this->opened(['patient_id' => $this->patientOf($this->clientIn($this->clinic), $patient)->id, 'diagnosis' => 'Otitis']);
        DB::table('consultations')->where('id', $id)->update(['consulted_at' => $consultedAt, 'status' => $status]);

        return $id;
    }

    private function ids(string $query = ''): array
    {
        return array_column($this->getJson('/api/consultations' . $query)->assertOk()->json('data'), 'id');
    }

    public function test_it_lists_the_last_30_days_by_default_newest_first(): void
    {
        $old = $this->consultation('2026-10-10 10:00:00');
        $inRange = $this->consultation('2026-10-11 00:00:00');
        $today = $this->consultation('2026-11-10 08:00:00');
        $this->actAsAdmin();

        $this->assertSame([$today, $inRange], $this->ids());
        $this->assertNotContains($old, $this->ids());
        $this->assertSame([$old], $this->ids('?from=2026-10-10&to=2026-10-10'));
    }

    public function test_filters(): void
    {
        $a = $this->consultation('2026-11-09 10:00:00', 'open', ['name' => 'Luna']);
        $b = $this->consultation('2026-11-09 11:00:00', 'closed', ['name' => 'Toby']);
        $row = $this->consultationRow($a);
        $this->actAsAdmin();

        $this->assertSame([$a], $this->ids("?patient_id={$row->patient_id}"));
        $this->assertSame([$a], $this->ids("?client_id={$row->client_id}"));
        $this->assertSame([$b, $a], $this->ids("?veterinarian_id={$this->vet->id}&branch_id={$this->branch->id}"));
        $this->assertSame([$b], $this->ids('?status=closed'));
        $this->assertSame([$b, $a], $this->ids('?status[]=closed&status[]=open'));
        $this->assertSame([$a], $this->ids('?search=lun'));
        $this->assertSame([$b], $this->ids('?search=CON-000002'));
        // Literal: "%" no es comodín.
        $this->assertSame([], $this->ids('?search=%25'));
    }

    public function test_filter_by_appointment(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();
        $id = $this->opened(['appointment_id' => $appointment]);
        $this->consultation('2026-11-09 10:00:00');

        $this->assertSame([$id], $this->ids("?appointment_id={$appointment}"));
    }

    public function test_bad_filters_answer_422(): void
    {
        $this->actAsAdmin();

        foreach ([
            '?from=2026-10-01&to=2026-11-10' => 'to',
            '?from=2026-11-10&to=2026-11-01' => 'to',
            '?from=ayer' => 'from',
            '?status=finished' => 'status.0',
            '?patient_id=999999' => 'patient_id',
            '?per_page=101' => 'per_page',
        ] as $query => $field) {
            $this->getJson('/api/consultations' . $query)->assertUnprocessable()->assertJsonValidationErrors([$field]);
        }
    }

    public function test_the_reception_sees_the_administrative_view(): void
    {
        $this->consultation('2026-11-09 10:00:00');
        $this->actAsReceptionist();

        $item = $this->getJson('/api/consultations')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('diagnosis', $item);
        $this->assertSame(
            ['id', 'code', 'consulted_at', 'status', 'branch', 'patient', 'client', 'veterinarian', 'appointment_id', 'reason'],
            array_keys($item),
        );
    }

    public function test_the_administrator_and_the_veterinarian_see_the_diagnosis(): void
    {
        $this->consultation('2026-11-09 10:00:00');

        $this->actAsAdmin();
        $this->getJson('/api/consultations')->assertOk()->assertJsonPath('data.0.diagnosis', 'Otitis');

        $this->actAsVet($this->veterinarianIn($this->branch));
        $this->getJson('/api/consultations')->assertOk()->assertJsonPath('data.0.diagnosis', 'Otitis');
    }

    public function test_another_clinic_is_not_listed(): void
    {
        $this->consultation('2026-11-09 10:00:00');
        $foreign = $this->createClinic();
        $foreignPatient = $this->patientOf($this->clientIn($foreign));
        CurrentClinic::run($foreign, fn () => $this->consultationFor($foreignPatient));
        $this->actAsAdmin();

        $this->assertCount(1, $this->ids());
    }

    public function test_the_number_of_queries_does_not_grow_with_the_consultations(): void
    {
        $this->consultation('2026-11-09 10:00:00');
        $this->actAsAdmin();
        $one = $this->countQueries(fn () => $this->getJson('/api/consultations')->assertOk());

        foreach (range(1, 4) as $hour) {
            $this->consultation("2026-11-09 1{$hour}:00:00");
        }

        $this->actAsAdmin();
        $five = $this->countQueries(fn () => $this->getJson('/api/consultations')->assertOk()->assertJsonCount(5, 'data'));

        $this->assertSame($one, $five);
    }

    public function test_the_appointment_list_has_no_n_plus_1_with_has_consultation(): void
    {
        $this->actAsVet();
        $this->opened(['appointment_id' => $this->checkedIn()]);
        $this->actAsAdmin();
        $one = $this->countQueries(fn () => $this->getJson('/api/appointments')->assertOk());
        $queue = $this->countQueries(fn () => $this->getJson('/api/appointments/queue')->assertOk());

        foreach (['07:00', '07:30', '08:00'] as $time) {
            $this->actAsVet();
            $this->opened(['appointment_id' => $this->checkedIn(startsAt: $this->today($time))]);
        }

        $this->actAsAdmin();
        $this->assertSame($one, $this->countQueries(fn () => $this->getJson('/api/appointments')->assertOk()->assertJsonCount(4, 'data')));
        $this->assertSame($queue, $this->countQueries(fn () => $this->getJson('/api/appointments/queue')->assertOk()->assertJsonCount(4, 'data')));
    }
}
