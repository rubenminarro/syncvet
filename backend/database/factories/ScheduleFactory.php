<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Agenda de prueba (FASE 3, entrega 3.1). Se crea dentro de la clínica en contexto
 * (CurrentClinic::run): BelongsToClinic fija clinic_id. Sin sucursal, la primera de la
 * clínica (fallo cerrado sin clínica). Sin veterinario ni servicio: pasarlos con el estado
 * o con for().
 *
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => fn () => Branch::query()->orderBy('id')->value('id'),
            'name' => 'Agenda ' . fake()->unique()->numerify('####'),
            'slot_minutes' => 30,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
