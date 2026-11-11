<?php

namespace Tests\Feature\Appointments\Series;

use App\Enums\AppointmentStatus;
use App\Models\Clinic;
use App\Models\Schedule;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Cancelar con alcance (FASE 3, entrega 3.3): scope single (por defecto, solo el turno), following
 * (este y los siguientes de la serie) o series (toda la serie). El turno pedido sigue las reglas
 * de siempre; de los demás se cancelan solo los que están a confirmar o confirmados y todavía no
 * empezaron. El mismo motivo para todos, cada uno con su auditoría; la respuesta trae el turno
 * pedido y meta.cancelled_count. Editar o mover un turno de la serie no toca los demás.
 */
class AppointmentSeriesCancelTest extends TestCase
{
    use InteractsWithAppointments;

    private Clinic $clinic;

    private Schedule $schedule;

    /**
     * @var list<int> cinco lunes a las 10:00, del 16/11 al 14/12
     */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeToday();
        $this->clinic = $this->createClinic();
        $this->schedule = $this->openSchedule($this->defaultBranchIn($this->clinic));
        $this->actingIn($this->clinic, self::TRANSITION_PERMISSIONS);
        $this->ids = $this->bookedSeries($this->schedule, ['count' => 5]);
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        return DB::table('appointments')->whereIn('id', $this->ids)->orderBy('starts_at')->pluck('status')->all();
    }

    private function cancel(int $id, ?string $scope, string $reason = 'El cliente se mudó'): \Illuminate\Testing\TestResponse
    {
        return $this->transition($id, 'cancel', ['cancellation_reason' => $reason, ...($scope === null ? [] : ['scope' => $scope])]);
    }

    public function test_single_by_default_cancels_only_that_appointment(): void
    {
        $this->cancel($this->ids[1], null)
            ->assertOk()
            ->assertJsonPath('message', 'Turno cancelado.')
            ->assertJsonPath('meta.cancelled_count', 1)
            ->assertJsonPath('data.id', $this->ids[1]);

        $this->assertSame(['pending_confirmation', 'cancelled', 'pending_confirmation', 'pending_confirmation', 'pending_confirmation'], $this->statuses());

        $this->cancel($this->ids[2], 'single')->assertOk()->assertJsonPath('meta.cancelled_count', 1);
        $this->assertSame('cancelled', $this->statuses()[2]);
        $this->assertSame('pending_confirmation', $this->statuses()[3]);
    }

    public function test_following_cancels_this_one_and_the_next_ones_but_not_the_previous(): void
    {
        $this->setStatus($this->ids[3], AppointmentStatus::CONFIRMED);

        $this->cancel($this->ids[2], 'following')
            ->assertOk()
            ->assertJsonPath('message', 'Se cancelaron 3 turnos de la serie.')
            ->assertJsonPath('meta.cancelled_count', 3)
            ->assertJsonPath('data.id', $this->ids[2])
            ->assertJsonPath('data.status.value', 'cancelled');

        $this->assertSame(['pending_confirmation', 'pending_confirmation', 'cancelled', 'cancelled', 'cancelled'], $this->statuses());

        // El mismo motivo, quién y cuándo en todos; cada uno con su auditoría.
        $rows = DB::table('appointments')->whereIn('id', array_slice($this->ids, 2))->get();
        $this->assertSame(['El cliente se mudó'], $rows->pluck('cancellation_reason')->unique()->values()->all());
        $this->assertCount(1, $rows->pluck('cancelled_by')->unique());
        $this->assertNotNull($rows->first()->cancelled_at);
        $this->assertSame(3, DB::table('audit_logs')->where('auditable_type', 'appointment')->where('event', 'updated')->count());
    }

    public function test_following_leaves_the_checked_in_and_the_final_ones_as_they_are(): void
    {
        $this->setStatus($this->ids[2], AppointmentStatus::CHECKED_IN);
        $this->setStatus($this->ids[3], AppointmentStatus::NO_SHOW);
        $this->setStatus($this->ids[4], AppointmentStatus::ATTENDED);

        $this->cancel($this->ids[1], 'following')->assertOk()->assertJsonPath('meta.cancelled_count', 1);

        $this->assertSame(['pending_confirmation', 'cancelled', 'checked_in', 'no_show', 'attended'], $this->statuses());
    }

    public function test_series_cancels_the_whole_series_except_what_already_started(): void
    {
        // El lunes 23/11 a las 10:30: los turnos del 16/11 y del 23/11 ya empezaron.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:30', 'America/Asuncion'));
        $this->setStatus($this->ids[0], AppointmentStatus::CONFIRMED);
        $this->setStatus($this->ids[3], AppointmentStatus::CHECKED_IN);

        $this->cancel($this->ids[2], 'series')
            ->assertOk()
            ->assertJsonPath('meta.cancelled_count', 2);

        $this->assertSame(['confirmed', 'pending_confirmation', 'cancelled', 'checked_in', 'cancelled'], $this->statuses());
    }

    public function test_the_requested_appointment_follows_the_rules_of_a_single_cancellation(): void
    {
        // Ya empezó (el 16/11 a las 10:30) y está confirmado: se cancela igual (P3, sin límite).
        $this->travelTo(CarbonImmutable::parse('2026-11-16 10:30', 'America/Asuncion'));
        $this->setStatus($this->ids[0], AppointmentStatus::CONFIRMED);

        $this->cancel($this->ids[0], 'series')->assertOk()->assertJsonPath('meta.cancelled_count', 5);
        $this->assertSame(array_fill(0, 5, 'cancelled'), $this->statuses());
    }

    public function test_a_requested_appointment_that_does_not_admit_the_cancellation_changes_nothing(): void
    {
        $this->setStatus($this->ids[1], AppointmentStatus::ATTENDED);

        $response = $this->cancel($this->ids[1], 'series');

        $this->assertErrorCode($response, 422, 'APPOINTMENT_INVALID_TRANSITION');
        $this->assertSame(['pending_confirmation', 'attended', 'pending_confirmation', 'pending_confirmation', 'pending_confirmation'], $this->statuses());
    }

    public function test_another_series_is_not_touched(): void
    {
        $others = $this->bookedSeries($this->schedule, ['count' => 3], ['starts_at' => $this->at('11:00')]);

        $this->cancel($this->ids[0], 'series')->assertOk()->assertJsonPath('meta.cancelled_count', 5);

        $this->assertSame(
            array_fill(0, 3, 'pending_confirmation'),
            DB::table('appointments')->whereIn('id', $others)->pluck('status')->all()
        );
    }

    public function test_without_a_series_every_scope_cancels_only_that_appointment(): void
    {
        $single = $this->booked($this->schedule, ['starts_at' => $this->at('12:00')]);

        $this->cancel($single, 'following')->assertOk()->assertJsonPath('meta.cancelled_count', 1);
        $this->assertSame(array_fill(0, 5, 'pending_confirmation'), $this->statuses());
    }

    public function test_an_invalid_scope_and_a_missing_reason_are_rejected(): void
    {
        $response = $this->cancel($this->ids[0], 'all')->assertUnprocessable();
        $response->assertJsonPath('errors.scope.0', 'El alcance debe ser single (solo este turno), following (este y los siguientes) o series (toda la serie).');

        $this->transition($this->ids[0], 'cancel', ['scope' => 'series'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.cancellation_reason.0', 'Ingresá el motivo de la cancelación.');

        $this->assertSame(array_fill(0, 5, 'pending_confirmation'), $this->statuses());
    }

    public function test_editing_or_moving_one_appointment_does_not_touch_the_others(): void
    {
        $seriesId = $this->appointmentRow($this->ids[0])->series_id;
        $before = DB::table('appointments')->whereIn('id', $this->ids)->orderBy('id')->get(['id', 'starts_at', 'ends_at', 'notes', 'updated_at']);

        $this->patchJson("/api/appointments/{$this->ids[1]}", ['notes' => 'Trae los estudios'])->assertOk();
        $this->patchJson("/api/appointments/{$this->ids[1]}", ['starts_at' => $this->at('11:00', '2026-11-24')])
            ->assertOk()
            ->assertJsonPath('data.series_id', $seriesId)
            ->assertJsonPath('data.starts_at', '2026-11-24T14:00:00.000000Z');

        $after = DB::table('appointments')->whereIn('id', $this->ids)->orderBy('id')->get(['id', 'starts_at', 'ends_at', 'notes', 'updated_at']);
        $this->assertEquals($before->except(1)->values(), $after->except(1)->values());

        // recurrence no se edita: la serie se arma en el alta.
        $this->patchJson("/api/appointments/{$this->ids[2]}", ['recurrence' => ['count' => 2]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.recurrence.0', 'La serie se arma al dar los turnos: cada turno se modifica por separado.');
    }

    public function test_the_moved_appointment_keeps_its_place_in_the_series_by_date(): void
    {
        // El segundo pasa a después del cuarto: following desde el tercero lo incluye.
        $this->patchJson("/api/appointments/{$this->ids[1]}", ['starts_at' => $this->at('10:00', '2026-12-08')])->assertOk();

        $this->cancel($this->ids[2], 'following')->assertOk()->assertJsonPath('meta.cancelled_count', 4);

        $this->assertSame('pending_confirmation', $this->appointmentRow($this->ids[0])->status);
        $this->assertSame('cancelled', $this->appointmentRow($this->ids[1])->status);
    }

    /**
     * Revisión de la 3.3 (hallazgo 5): un turno movido a una fecha anterior queda antes en la
     * serie: following desde el que estaba antes que él no lo incluye.
     */
    public function test_an_appointment_moved_to_an_earlier_date_is_not_one_of_the_following(): void
    {
        // El cuarto (07/12) pasa al martes 17/11, antes del tercero (30/11).
        $this->patchJson("/api/appointments/{$this->ids[3]}", ['starts_at' => $this->at('10:00', '2026-11-17')])->assertOk();

        $this->cancel($this->ids[2], 'following')->assertOk()->assertJsonPath('meta.cancelled_count', 2);

        $this->assertSame('pending_confirmation', $this->appointmentRow($this->ids[3])->status);
        $this->assertSame('cancelled', $this->appointmentRow($this->ids[2])->status);
        $this->assertSame('cancelled', $this->appointmentRow($this->ids[4])->status);
        $this->assertSame('pending_confirmation', $this->appointmentRow($this->ids[1])->status);
    }

    /**
     * Dos turnos de la serie en el mismo instante (el segundo y el cuarto, movidos al martes
     * 01/12 a las 10:00; sin la API, porque se superpondrían): "los siguientes" se desempatan
     * por id.
     */
    private function moveTwoToTheSameInstant(): void
    {
        DB::table('appointments')->whereIn('id', [$this->ids[1], $this->ids[3]])->update([
            'starts_at' => '2026-12-01 10:00:00',
            'ends_at' => '2026-12-01 10:30:00',
        ]);
    }

    /**
     * Revisión de la 3.3 (hallazgo 5): desde el de id menor, el otro del mismo instante es uno
     * de los siguientes.
     */
    public function test_following_from_the_lower_id_of_a_tie_includes_the_other(): void
    {
        $this->moveTwoToTheSameInstant();

        $this->cancel($this->ids[1], 'following')->assertOk()->assertJsonPath('meta.cancelled_count', 3);

        $this->assertSame('cancelled', $this->appointmentRow($this->ids[3])->status);
        $this->assertSame('cancelled', $this->appointmentRow($this->ids[4])->status);
        $this->assertSame('pending_confirmation', $this->appointmentRow($this->ids[2])->status);
    }

    /**
     * Revisión de la 3.3 (hallazgo 5): desde el de id mayor, el otro del mismo instante no.
     */
    public function test_following_from_the_higher_id_of_a_tie_excludes_the_other(): void
    {
        $this->moveTwoToTheSameInstant();

        $this->cancel($this->ids[3], 'following')->assertOk()->assertJsonPath('meta.cancelled_count', 2);

        $this->assertSame('pending_confirmation', $this->appointmentRow($this->ids[1])->status);
        $this->assertSame('cancelled', $this->appointmentRow($this->ids[4])->status);
    }

    /**
     * Revisión de la 3.3 (hallazgo 6): un turno de una serie de otra clínica, con series o
     * following, responde 404 y no cambia ningún turno de esa serie.
     */
    public function test_a_series_of_another_clinic_answers_404_without_changes(): void
    {
        $other = $this->createClinic();
        $otherSchedule = $this->openSchedule($this->defaultBranchIn($other));
        $this->actingIn($other, self::TRANSITION_PERMISSIONS);
        $otherIds = $this->bookedSeries($otherSchedule, ['count' => 3]);
        $this->actingIn($this->clinic, self::TRANSITION_PERMISSIONS);

        foreach (['series', 'following'] as $scope) {
            $this->assertErrorCode($this->cancel($otherIds[0], $scope), 404, 'RESOURCE_NOT_FOUND');
        }

        $this->assertSame(
            array_fill(0, 3, 'pending_confirmation'),
            DB::table('appointments')->whereIn('id', $otherIds)->pluck('status')->all()
        );
        $this->assertSame(array_fill(0, 5, 'pending_confirmation'), $this->statuses());
    }

    /**
     * Revisión de la 3.3 (hallazgo 8): scope: null es el valor por defecto (single).
     */
    public function test_a_null_scope_is_single(): void
    {
        $this->cancel($this->ids[1], null)->assertOk();
        $this->transition($this->ids[2], 'cancel', ['cancellation_reason' => 'Viaje', 'scope' => null])
            ->assertOk()
            ->assertJsonPath('meta.cancelled_count', 1);

        $this->assertSame(['pending_confirmation', 'cancelled', 'cancelled', 'pending_confirmation', 'pending_confirmation'], $this->statuses());
    }

    /**
     * Revisión de la 3.3 (hallazgo 9): toda la cancelación usa una sola hora. El reloj avanza un
     * segundo en cada now(); los cinco turnos quedan con el mismo cancelled_at.
     */
    public function test_the_whole_cancellation_uses_one_time(): void
    {
        $base = CarbonImmutable::parse(self::TODAY . ' 07:00:00', 'America/Asuncion');
        $tick = 0;
        Carbon::setTestNow(function () use ($base, &$tick) {
            return $base->addSeconds($tick++);
        });

        $this->cancel($this->ids[0], 'series')->assertOk()->assertJsonPath('meta.cancelled_count', 5);

        $times = DB::table('appointments')->whereIn('id', $this->ids)->pluck('cancelled_at')->unique()->values()->all();
        $this->assertCount(1, $times, implode(', ', $times));
    }
}
