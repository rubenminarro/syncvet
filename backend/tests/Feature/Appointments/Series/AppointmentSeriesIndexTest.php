<?php

namespace Tests\Feature\Appointments\Series;

use App\Enums\AppointmentStatus;
use App\Http\Requests\IndexAppointmentRequest;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * La serie entera en el listado (entrega front-04, pregunta 16 de la F3 del frontend): con
 * series_id y sin from ni to, GET appointments trae todos los turnos de la serie, sin el tope de
 * 31 días (una serie dura hasta un año). Con un rango, sigue el rango y su tope; sin series_id,
 * nada cambia (sin rango, hoy).
 */
class AppointmentSeriesIndexTest extends TestCase
{
    use InteractsWithAppointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
    }

    private function ids(string $uri): array
    {
        return array_column($this->getJson($uri)->assertOk()->json('data'), 'id');
    }

    public function test_the_whole_series_without_a_range(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        // Cada 4 semanas, 5 turnos: del 16/11/2026 al 08/03/2027, más de 31 días.
        $ids = $this->bookedSeries($schedule, ['every_weeks' => 4, 'count' => 5]);
        $seriesId = $this->appointmentRow($ids[0])->series_id;
        $this->setStatus($ids[1], AppointmentStatus::CANCELLED, ['cancelled_at' => now(), 'cancellation_reason' => 'Viaje']);
        // Otro turno de la misma agenda, fuera de la serie.
        $this->booked($schedule, ['starts_at' => $this->at('15:00')]);

        $this->getJson("/api/appointments?series_id={$seriesId}&per_page=100")
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 5)
            ->assertJsonPath('data.1.status.value', 'cancelled');
        $this->assertSame($ids, $this->ids("/api/appointments?series_id={$seriesId}&per_page=100"));
        // Con los demás filtros, igual sin rango.
        $this->assertSame([$ids[0], $ids[2], $ids[3], $ids[4]], $this->ids("/api/appointments?series_id={$seriesId}&status[]=pending_confirmation"));
    }

    public function test_with_a_range_the_series_keeps_the_range_and_its_limit(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $ids = $this->bookedSeries($schedule, ['every_weeks' => 4, 'count' => 5]);
        $seriesId = $this->appointmentRow($ids[0])->series_id;

        $this->assertSame([$ids[0]], $this->ids("/api/appointments?series_id={$seriesId}&from=" . self::MONDAY));
        $this->assertSame([$ids[1]], $this->ids("/api/appointments?series_id={$seriesId}&to=2026-12-14"));
        $this->assertSame([$ids[0], $ids[1]], $this->ids("/api/appointments?series_id={$seriesId}&from=2026-11-16&to=2026-12-16"));
        $this->getJson("/api/appointments?series_id={$seriesId}&from=2026-11-16&to=2026-12-18")
            ->assertUnprocessable()->assertJsonPath('errors.to.0', IndexAppointmentRequest::RANGE_TOO_LONG);
    }

    public function test_without_series_nothing_changes_and_nothing_of_another_clinic(): void
    {
        $other = $this->createClinic();
        $this->actAsReception($other);
        $foreign = $this->bookedSeries($this->openSchedule($this->defaultBranchIn($other)), ['count' => 3]);
        $foreignSeries = $this->appointmentRow($foreign[0])->series_id;

        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        $this->bookedSeries($schedule, ['count' => 3]);
        $today = $this->booked($schedule, ['starts_at' => $this->at('09:00', self::TODAY)]);

        // Sin series_id y sin rango, solo hoy (los de la serie son desde el lunes).
        $this->assertSame([$today], $this->ids('/api/appointments'));
        // La serie de otra clínica no aparece: sin rango, con rango ni con otros filtros.
        $this->assertSame([], $this->ids("/api/appointments?series_id={$foreignSeries}"));
        $this->getJson("/api/appointments?series_id={$foreignSeries}&per_page=100")
            ->assertOk()->assertJsonPath('meta.pagination.total', 0);
        $this->assertSame([], $this->ids("/api/appointments?series_id={$foreignSeries}&from=2026-11-16&to=2026-12-16"));
        $this->assertSame([], $this->ids("/api/appointments?series_id={$foreignSeries}&status[]=pending_confirmation"));

        // La otra clínica sí ve la suya entera (el filtro no es lo que la oculta).
        $this->actAsReception($other);
        $this->assertSame($foreign, $this->ids("/api/appointments?series_id={$foreignSeries}"));
    }

    public function test_a_series_of_another_branch_of_the_clinic_is_listed(): void
    {
        // Los turnos no se limitan a las sucursales del usuario (H11: el alta da turnos en cualquier
        // agenda de la clínica); solo la clínica limita.
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $schedule = $this->openSchedule($this->branchIn($clinic));
        $this->actAsReception($clinic);
        $ids = $this->bookedSeries($schedule, ['count' => 3]);

        $this->assertSame($ids, $this->ids('/api/appointments?series_id=' . $this->appointmentRow($ids[0])->series_id));
    }

    public function test_the_longest_series_fits_in_one_request(): void
    {
        $clinic = $this->createClinic();
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));
        $this->actAsReception($clinic);
        // 52 turnos semanales: un año, el tope de la serie (AppointmentRecurrence).
        $ids = $this->bookedSeries($schedule, ['count' => 52]);
        $seriesId = $this->appointmentRow($ids[0])->series_id;

        $this->getJson("/api/appointments?series_id={$seriesId}&per_page=100")
            ->assertOk()
            ->assertJsonCount(52, 'data')
            ->assertJsonPath('meta.pagination.total', 52)
            ->assertJsonPath('meta.pagination.last_page', 1);
        // Sin per_page, la página por defecto (10), con el total de la serie.
        $this->getJson("/api/appointments?series_id={$seriesId}")
            ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.pagination.total', 52);
        // Con un rango, el tope de 31 días sigue (desde el comienzo de from): hasta el 16/12, sí; el 17/12, no.
        $this->getJson("/api/appointments?series_id={$seriesId}&from=2026-11-16&to=2026-12-16")
            ->assertOk()->assertJsonPath('meta.pagination.total', 5);
        $this->getJson("/api/appointments?series_id={$seriesId}&from=2026-11-16&to=2026-12-17")
            ->assertUnprocessable()->assertJsonPath('errors.to.0', IndexAppointmentRequest::RANGE_TOO_LONG);
    }
}
