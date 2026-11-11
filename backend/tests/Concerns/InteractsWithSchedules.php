<?php

namespace Tests\Concerns;

use App\Enums\AppointmentStatus;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\ScheduleHour;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Agendas, horarios y bloqueos (FASE 3, entrega 3.1). Los tests usan un usuario con los
 * permisos de agendas y bloqueos, no el administrador, que pasa todo por Gate::before.
 * Los turnos se insertan sin la API (llega en la 3.2a), con las claves foráneas que exigen.
 */
trait InteractsWithSchedules
{
    // Trae InteractsWithVeterinarians, InteractsWithUsers e InteractsWithBranches.
    use InteractsWithServices;

    protected const SCHEDULE_PERMISSIONS = [
        'schedules.index', 'schedules.store', 'schedules.show', 'schedules.update', 'schedules.destroy',
        'schedule-blocks.index', 'schedule-blocks.store', 'schedule-blocks.destroy',
    ];

    protected function actAsScheduleManager(Clinic $clinic): User
    {
        return $this->actingIn($clinic, self::SCHEDULE_PERMISSIONS);
    }

    /**
     * Un veterinario activo cuyo usuario trabaja en las sucursales.
     */
    protected function veterinarianIn(Branch ...$branches): Veterinarian
    {
        $clinic = $branches[0]->clinic;

        return $this->veterinarianOf($this->veterinarianUser($clinic, $branches));
    }

    /**
     * Agenda creada sin la API (ScheduleFactory), en la sucursal.
     */
    protected function scheduleIn(Branch $branch, array $attributes = []): Schedule
    {
        return CurrentClinic::run($branch->clinic, fn () => Schedule::factory()->create([
            'branch_id' => $branch->id,
            ...$attributes,
        ]));
    }

    /**
     * @param list<array{0: int, 1: string, 2: string}> $hours [weekday, starts_at, ends_at]
     */
    protected function hoursFor(Schedule $schedule, array $hours): void
    {
        CurrentClinic::run($schedule->clinic, function () use ($schedule, $hours) {
            foreach ($hours as [$weekday, $startsAt, $endsAt]) {
                ScheduleHour::factory()->for($schedule)->create(['weekday' => $weekday, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
            }
        });
    }

    protected function blockFor(Schedule $schedule, array $attributes = []): ScheduleBlock
    {
        return CurrentClinic::run($schedule->clinic, fn () => ScheduleBlock::factory()->for($schedule)->create([
            'created_by' => User::factory()->for($schedule->clinic)->create()->id,
            ...$attributes,
        ]));
    }

    /**
     * Turno de la agenda, sin la API: copia la sucursal y el veterinario de la agenda.
     */
    protected function appointmentIn(
        Schedule $schedule,
        ?CarbonInterface $startsAt = null,
        AppointmentStatus $status = AppointmentStatus::CONFIRMED,
        int $minutes = 30,
    ): int {
        $clinic = $schedule->clinic;
        $startsAt ??= now()->addDay()->setTime(10, 0);
        $patient = $this->patientOf($this->clientIn($clinic));

        return DB::table('appointments')->insertGetId([
            'clinic_id' => $clinic->id,
            'branch_id' => $schedule->branch_id,
            'schedule_id' => $schedule->id,
            'veterinarian_id' => $schedule->veterinarian_id,
            'service_id' => $schedule->service_id,
            'client_id' => $patient->client_id,
            'patient_id' => $patient->id,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $startsAt->copy()->addMinutes($minutes)->format('Y-m-d H:i:s'),
            'status' => $status->value,
            'created_by' => User::factory()->for($clinic)->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Datos válidos para el alta de una agenda por la API.
     */
    protected function schedulePayload(Branch $branch, array $overrides = []): array
    {
        return [
            'branch_id' => $branch->id,
            'name' => 'Consultorio 1',
            ...$overrides,
        ];
    }

    /**
     * La sucursal de la agenda, leída dentro de su clínica.
     */
    protected function branchOf(Schedule $schedule): Branch
    {
        return CurrentClinic::run($schedule->clinic, fn () => Branch::findOrFail($schedule->branch_id));
    }

    protected function scheduleRow(int $id): ?object
    {
        return DB::table('schedules')->where('id', $id)->first();
    }
}
