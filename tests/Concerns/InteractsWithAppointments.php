<?php

namespace Tests\Concerns;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\User;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Turnos (FASE 3, entrega 3.2a). Los tests usan un usuario con los permisos de turnos (el de
 * la recepción, con o sin appointments.overbook), no el administrador, que pasa todo por
 * Gate::before. El reloj se congela el martes 10/11/2026 a las 07:00 de Asunción
 * (freezeToday), para que los días de la semana y "hoy" no dependan de cuándo corre la suite.
 */
trait InteractsWithAppointments
{
    use InteractsWithSchedules;

    protected const APPOINTMENT_PERMISSIONS = [
        'appointments.index', 'appointments.store', 'appointments.show', 'appointments.update',
    ];

    /**
     * Martes, en hora de Asunción.
     */
    protected const TODAY = '2026-11-10';

    /**
     * El lunes siguiente.
     */
    protected const MONDAY = '2026-11-16';

    protected function freezeToday(string $time = '07:00:00'): void
    {
        $this->travelTo(CarbonImmutable::parse(self::TODAY . ' ' . $time, 'America/Asuncion'));
    }

    protected function actAsReception(Clinic $clinic, bool $overbook = false): User
    {
        return $this->actingIn($clinic, [...self::APPOINTMENT_PERMISSIONS, ...($overbook ? ['appointments.overbook'] : [])]);
    }

    /**
     * Agenda activa con un veterinario de la sucursal (salvo que $attributes diga otra cosa),
     * abierta todos los días de 08:00 a 20:00.
     */
    protected function openSchedule(Branch $branch, array $attributes = []): Schedule
    {
        $schedule = $this->scheduleIn($branch, [
            'veterinarian_id' => $this->veterinarianIn($branch)->id,
            ...$attributes,
        ]);

        $this->hoursFor($schedule, array_map(fn (int $weekday) => [$weekday, '08:00', '20:00'], range(1, 7)));

        return $schedule;
    }

    /**
     * Un instante sin zona (hora de Asunción): "2026-11-16T10:00:00".
     */
    protected function at(string $time, string $date = self::MONDAY): string
    {
        return "{$date}T{$time}:00";
    }

    /**
     * Datos válidos para el alta: el lunes a las 10:00, con un contacto.
     */
    protected function appointmentPayload(Schedule $schedule, array $overrides = []): array
    {
        return [
            'schedule_id' => $schedule->id,
            'starts_at' => $this->at('10:00'),
            'contact_name' => 'Juan, el de la perra Luna',
            'contact_phone' => '981123456',
            ...$overrides,
        ];
    }

    /**
     * Alta por la API.
     */
    protected function book(Schedule $schedule, array $overrides = []): TestResponse
    {
        return $this->postJson('/api/appointments', $this->appointmentPayload($schedule, $overrides));
    }

    /**
     * Alta por la API que tiene que responder 201; devuelve el id.
     */
    protected function booked(Schedule $schedule, array $overrides = []): int
    {
        return $this->book($schedule, $overrides)->assertCreated()->json('data.id');
    }

    protected function appointmentRow(int $id): object
    {
        return DB::table('appointments')->where('id', $id)->first();
    }

    protected function appointment(Clinic $clinic, int $id): Appointment
    {
        return CurrentClinic::run($clinic, fn () => Appointment::findOrFail($id));
    }
}
