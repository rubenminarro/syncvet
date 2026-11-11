<?php

namespace Database\Factories;

use App\Models\ScheduleHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tramo del horario semanal (FASE 3, entrega 3.1): lunes de 09:00 a 12:00 por defecto.
 * Pasar la agenda con for($schedule), dentro de su clínica (CurrentClinic::run).
 *
 * @extends Factory<ScheduleHour>
 */
class ScheduleHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ];
    }
}
