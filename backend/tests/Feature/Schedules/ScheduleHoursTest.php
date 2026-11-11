<?php

namespace Tests\Feature\Schedules;

use App\Enums\AppointmentStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Horario semanal de una agenda (FASE 3, entrega 3.1): tramos de hora de reloj de Asunción
 * en múltiplos de 5, sin superponerse en el mismo día (tocarse sí), hasta 6 por día. Al
 * editar, hours reemplaza el horario entero: borra, crea y conserva, cada tramo con su
 * auditoría; hours: [] lo vacía. Los turnos que ya existen no se tocan.
 */
class ScheduleHoursTest extends TestCase
{
    use InteractsWithSchedules;

    public function test_overlapping_ranges_on_the_same_day_are_rejected(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => [
            ['weekday' => 3, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ['weekday' => 3, 'starts_at' => '11:55', 'ends_at' => '14:00'],
        ]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.hours.0', 'El horario del miércoles tiene tramos que se superponen.');

        $this->assertSame(0, DB::table('schedule_hours')->count());
    }

    /**
     * Hallazgo 3 de la revisión: si la edición falla, por la validación o por el Service, el
     * horario anterior queda como estaba (el reemplazo va en la transacción de la edición).
     */
    public function test_a_failed_update_keeps_the_previous_hours(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $schedule = $this->scheduleIn($branch, ['veterinarian_id' => $this->veterinarianIn($branch, $other)->id]);
        $this->hoursFor($schedule, [[1, '08:00', '12:00'], [2, '14:00', '18:00']]);
        $this->appointmentIn($schedule);
        $before = DB::table('schedule_hours')->orderBy('id')->get();
        $this->actAsScheduleManager($clinic);
        $newHours = [['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '13:00']];

        // Un tramo superpuesto: 422 de la validación.
        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => [
            ['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '13:00'],
            ['weekday' => 3, 'starts_at' => '12:00', 'ends_at' => '15:00'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['hours']);
        $this->assertEquals($before, DB::table('schedule_hours')->orderBy('id')->get());

        // Un horario válido con un cambio de sucursal que la agenda con turnos no admite.
        $this->assertErrorCode(
            $this->patchJson("/api/schedules/{$schedule->id}", ['branch_id' => $other->id, 'hours' => $newHours]),
            422,
            'SCHEDULE_NOT_EDITABLE'
        );
        $this->assertEquals($before, DB::table('schedule_hours')->orderBy('id')->get());
        $this->assertSame(0, DB::table('audit_logs')->where('auditable_type', 'schedule_hour')->where('event', 'deleted')->count());
    }

    public function test_a_range_inside_another_is_an_overlap_and_order_does_not_matter(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => [
            ['weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00'],
            ['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '16:00'],
        ]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.hours.0', 'El horario del lunes tiene tramos que se superponen.');
    }

    public function test_ranges_that_touch_or_are_on_different_days_pass(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => [
            ['weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '16:00'],
            ['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ['weekday' => 2, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ['weekday' => 7, 'starts_at' => '00:00', 'ends_at' => '23:55'],
        ]])
            ->assertOk()
            ->assertJsonCount(4, 'data.hours')
            ->assertJsonPath('data.hours.0.starts_at', '08:00')
            ->assertJsonPath('data.hours.1.starts_at', '12:00')
            ->assertJsonPath('data.hours.3.weekday', 7)
            ->assertJsonPath('data.hours.3.ends_at', '23:55');
    }

    public static function invalidHours(): array
    {
        return [
            'inicio fuera de la grilla' => [['weekday' => 1, 'starts_at' => '08:03', 'ends_at' => '12:00'], 'hours.0.starts_at'],
            'fin fuera de la grilla' => [['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:01'], 'hours.0.ends_at'],
            'con segundos' => [['weekday' => 1, 'starts_at' => '08:00:00', 'ends_at' => '12:00'], 'hours.0.starts_at'],
            'hora 24' => [['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '24:00'], 'hours.0.ends_at'],
            'inicio igual al fin' => [['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '08:00'], 'hours.0.ends_at'],
            'inicio después del fin' => [['weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '08:00'], 'hours.0.ends_at'],
            'día 0' => [['weekday' => 0, 'starts_at' => '08:00', 'ends_at' => '12:00'], 'hours.0.weekday'],
            'día 8' => [['weekday' => 8, 'starts_at' => '08:00', 'ends_at' => '12:00'], 'hours.0.weekday'],
            'sin día' => [['starts_at' => '08:00', 'ends_at' => '12:00'], 'hours.0.weekday'],
            'con un campo de más' => [['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00', 'id' => 5], 'hours.0'],
        ];
    }

    #[DataProvider('invalidHours')]
    public function test_invalid_ranges_are_rejected(array $hour, string $field): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => [$hour]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    public function test_more_than_six_ranges_a_day_are_rejected(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->actAsScheduleManager($clinic);

        $hours = array_map(fn (int $i) => ['weekday' => 5, 'starts_at' => sprintf('%02d:00', 8 + $i), 'ends_at' => sprintf('%02d:30', 8 + $i)], range(0, 6));

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => $hours])
            ->assertUnprocessable()
            ->assertJsonPath('errors.hours.0', 'El horario del viernes tiene más de 6 tramos.');

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => array_slice($hours, 0, 6)])->assertOk();
    }

    public function test_replacing_deletes_creates_and_keeps_each_range_with_its_audit(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->hoursFor($schedule, [[1, '08:00', '12:00'], [2, '08:00', '12:00']]);
        $kept = DB::table('schedule_hours')->where('weekday', 1)->value('id');
        $removed = DB::table('schedule_hours')->where('weekday', 2)->value('id');
        DB::table('audit_logs')->delete();
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => [
            ['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ['weekday' => 4, 'starts_at' => '14:00', 'ends_at' => '18:00'],
        ]])->assertOk()->assertJsonCount(2, 'data.hours');

        $this->assertTrue(DB::table('schedule_hours')->where('id', $kept)->exists(), 'El tramo que no cambia se recreó.');
        $this->assertFalse(DB::table('schedule_hours')->where('id', $removed)->exists());
        $this->assertSame(1, DB::table('schedule_hours')->where('weekday', 4)->count());

        $audit = DB::table('audit_logs')->where('auditable_type', 'schedule_hour')->orderBy('id')->get();
        $this->assertSame(['deleted', 'created'], $audit->pluck('event')->all());
        $this->assertSame($removed, (int) $audit[0]->auditable_id);
        $this->assertSame(0, DB::table('audit_logs')->where('auditable_type', 'schedule_hour')->where('auditable_id', $kept)->count());
    }

    public function test_an_empty_list_clears_the_hours_and_null_is_rejected(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->hoursFor($schedule, [[1, '08:00', '12:00']]);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => null])->assertUnprocessable()->assertJsonValidationErrors(['hours']);
        $this->assertSame(1, DB::table('schedule_hours')->count());

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => []])->assertOk()->assertJsonPath('data.hours', []);
        $this->assertSame(0, DB::table('schedule_hours')->count());
    }

    public function test_without_hours_in_the_request_the_hours_do_not_change(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->hoursFor($schedule, [[1, '08:00', '12:00']]);
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['slot_minutes' => 15])->assertOk()->assertJsonCount(1, 'data.hours');
    }

    public function test_changing_the_hours_does_not_touch_existing_appointments(): void
    {
        [$clinic, $schedule] = $this->scheduleWithService();
        $this->hoursFor($schedule, [[1, '08:00', '12:00']]);
        $appointment = $this->appointmentIn($schedule, status: AppointmentStatus::CONFIRMED);
        $before = DB::table('appointments')->where('id', $appointment)->first();
        $this->actAsScheduleManager($clinic);

        $this->patchJson("/api/schedules/{$schedule->id}", ['hours' => []])->assertOk();

        $this->assertEquals($before, DB::table('appointments')->where('id', $appointment)->first());
    }

    /**
     * @return array{0: \App\Models\Clinic, 1: \App\Models\Schedule}
     */
    private function scheduleWithService(): array
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);

        return [$clinic, $this->scheduleIn($branch, ['service_id' => $this->serviceIn($clinic)->id])];
    }
}
