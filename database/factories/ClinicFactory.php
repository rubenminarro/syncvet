<?php

namespace Database\Factories;

use App\Enums\ClinicStatus;
use App\Models\Clinic;
use App\Services\ClinicService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Clínica con un RUC válido (dígito verificador módulo 11). Solo la clínica: la
 * clínica operativa completa (sucursal, depósito, administrador) la crea
 * ClinicService::create.
 *
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ruc = (string) fake()->unique()->numberBetween(1000000, 99999999);
        $name = 'Veterinaria ' . fake()->lastName();

        return [
            'name' => $name,
            'legal_name' => "{$name} S.A.",
            'ruc' => $ruc,
            'ruc_dv' => (string) ClinicService::rucCheckDigit($ruc),
            'timezone' => 'America/Asuncion',
            'status' => ClinicStatus::ACTIVE,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => ClinicStatus::SUSPENDED]);
    }
}
