<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos que necesita cualquier instalación: roles y permisos, y el catálogo
 * global de especies y razas. Los dos seeders son idempotentes.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SpeciesSeeder::class,
        ]);
    }
}
