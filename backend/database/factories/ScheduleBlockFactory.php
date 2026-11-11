<?php

namespace Database\Factories;

use App\Models\ScheduleBlock;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bloqueo de una agenda (FASE 3, entrega 3.1): mañana de 08:00 a 12:00 de Asunción por
 * defecto. Pasar la agenda con for($schedule), dentro de su clínica (CurrentClinic::run).
 * Sin created_by, el primer usuario de la clínica (User no tiene el scope de clínica).
 *
 * @extends Factory<ScheduleBlock>
 */
class ScheduleBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starts_at' => fn () => now()->addDay()->setTime(8, 0),
            'ends_at' => fn () => now()->addDay()->setTime(12, 0),
            'reason' => 'Capacitación',
            'created_by' => fn () => User::query()->where('clinic_id', CurrentClinic::id())->orderBy('id')->value('id'),
        ];
    }
}
