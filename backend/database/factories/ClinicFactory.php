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
        // RUC ficticios de 9 dígitos (R-013): hoy no se emiten RUC de 9 dígitos, y no
        // chocan con los de 8 de los tests y el DemoSeeder (999999xx) ni con las cédulas 99xxxxxx.
        $ruc = (string) fake()->unique()->numberBetween(100000000, 999999999);
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
