<?php

namespace Tests\Concerns;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Consultas y facturas mínimas para las reglas de uso de clientes y pacientes
 * (FASE 1). Se insertan con las filas que exigen sus claves foráneas (sucursal,
 * usuario y veterinario), sin los Services de las fases que las crean.
 */
trait InteractsWithClinicalRecords
{
    protected function consultationFor(Patient $patient, ?Client $client = null): int
    {
        $clinic = $patient->clinic;

        return DB::table('consultations')->insertGetId([
            'clinic_id' => $clinic->id,
            'code' => 'CON-' . str_pad((string) (DB::table('consultations')->count() + 1), 6, '0', STR_PAD_LEFT),
            'branch_id' => $this->testBranchId($clinic),
            'patient_id' => $patient->id,
            'client_id' => ($client ?? $patient->client)->id,
            'veterinarian_id' => $this->testVeterinarianId($clinic),
            'consulted_at' => now(),
            'created_by' => $this->testRecordsUser($clinic)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function invoiceFor(Client $client): int
    {
        $clinic = $client->clinic;

        return DB::table('invoices')->insertGetId([
            'clinic_id' => $clinic->id,
            'branch_id' => $this->testBranchId($clinic),
            'client_id' => $client->id,
            'customer_name' => $client->display_name,
            'created_by' => $this->testRecordsUser($clinic)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function testBranchId(Clinic $clinic): int
    {
        return DB::table('branches')->where('clinic_id', $clinic->id)->value('id')
            ?? DB::table('branches')->insertGetId([
                'clinic_id' => $clinic->id,
                'code' => 'SUC-01',
                'name' => 'Casa central',
                'establishment' => '001',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function testVeterinarianId(Clinic $clinic): int
    {
        return DB::table('veterinarians')->where('clinic_id', $clinic->id)->value('id')
            ?? DB::table('veterinarians')->insertGetId([
                'clinic_id' => $clinic->id,
                'user_id' => User::factory()->for($clinic)->create()->id,
                'code' => 'VET-000001',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function testRecordsUser(Clinic $clinic): User
    {
        return User::query()->where('clinic_id', $clinic->id)->first() ?? User::factory()->for($clinic)->create();
    }
}
