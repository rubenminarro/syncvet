<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;

/**
 * Sucursales y depósitos (FASE 2, entrega 2.1). Los tests usan un usuario con los
 * permisos de sucursales y depósitos, no el administrador, que pasa todo por
 * Gate::before.
 */
trait InteractsWithBranches
{
    use InteractsWithClinics;

    protected const BRANCH_PERMISSIONS = [
        'branches.index', 'branches.store', 'branches.show', 'branches.update',
        'warehouses.index', 'warehouses.store', 'warehouses.update',
    ];

    protected function actAsBranchManager(Clinic $clinic): User
    {
        return $this->actingIn($clinic, self::BRANCH_PERMISSIONS);
    }

    /**
     * La sucursal SUC-01 y el depósito DEP-01 por defecto, como los crea clinic:create
     * (ClinicFactory crea solo la clínica).
     */
    protected function defaultBranchIn(Clinic $clinic): Branch
    {
        return $this->branchIn($clinic, ['code' => 'SUC-01', 'name' => 'Casa central', 'establishment' => '001', 'is_default' => true], 'DEP-01');
    }

    /**
     * Sucursal con un depósito por defecto, creada sin la API.
     */
    protected function branchIn(Clinic $clinic, array $attributes = [], ?string $warehouseCode = null): Branch
    {
        static $sequence = 0;
        $sequence++;

        return CurrentClinic::run($clinic, function () use ($attributes, $warehouseCode, $sequence) {
            $branch = Branch::create([
                'code' => "SUC-T{$sequence}",
                'name' => "Sucursal de prueba {$sequence}",
                'establishment' => str_pad((string) (100 + $sequence % 800), 3, '0', STR_PAD_LEFT),
                ...$attributes,
            ]);

            Warehouse::create([
                'branch_id' => $branch->id,
                'code' => $warehouseCode ?? "DEP-T{$sequence}",
                'name' => 'Principal',
                'is_default' => true,
            ]);

            return $branch;
        });
    }

    protected function warehouseIn(Branch $branch, array $attributes = []): Warehouse
    {
        static $sequence = 0;
        $sequence++;

        return CurrentClinic::run($branch->clinic, fn () => Warehouse::create([
            'branch_id' => $branch->id,
            'code' => "DEP-W{$sequence}",
            'name' => "Depósito {$sequence}",
            ...$attributes,
        ]));
    }

    /**
     * Datos válidos para el alta de una sucursal por la API.
     */
    protected function branchPayload(array $overrides = []): array
    {
        return [
            'code' => 'SUC-02',
            'name' => 'Sucursal San Lorenzo',
            'establishment' => '002',
            'address' => 'Avda. Mcal. López 1234',
            'phone' => '+595981200300',
            'warehouse_code' => 'DEP-02',
            ...$overrides,
        ];
    }

    /**
     * Factura mínima en la sucursal (la API todavía no las crea; P9): solo lo que exigen
     * sus claves foráneas.
     */
    protected function invoiceInBranch(Branch $branch): int
    {
        $clinic = $branch->clinic;
        $client = $this->clientIn($clinic);

        return DB::table('invoices')->insertGetId([
            'clinic_id' => $clinic->id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'customer_name' => $client->display_name,
            'created_by' => User::factory()->for($clinic)->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function branchRow(int $id): object
    {
        return DB::table('branches')->where('id', $id)->first();
    }

    protected function warehouseRow(int $id): object
    {
        return DB::table('warehouses')->where('id', $id)->first();
    }
}
