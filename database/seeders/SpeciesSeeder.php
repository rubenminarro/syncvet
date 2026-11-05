<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Especies y razas frecuentes del catálogo global (clinic_id nulo). Idempotente.
 *
 * Usa el query builder y no los modelos, a propósito: Species y Breed tienen el
 * scope de catálogo compartido, que exige una clínica en contexto, y el alta por
 * modelo siempre toma la clínica actual. La siembra no tiene clínica (R-004).
 */
class SpeciesSeeder extends Seeder
{
    public const CATALOG = [
        'Canino' => ['Mestizo', 'Labrador Retriever', 'Golden Retriever', 'Pastor Alemán', 'Caniche', 'Bulldog Francés', 'Chihuahua', 'Pitbull', 'Rottweiler', 'Shih Tzu', 'Yorkshire Terrier', 'Dachshund', 'Beagle', 'Pug'],
        'Felino' => ['Mestizo', 'Siamés', 'Persa', 'Maine Coon', 'Angora', 'Bengalí'],
        'Equino' => ['Criollo', 'Cuarto de Milla', 'Pura Sangre', 'Árabe'],
        'Bovino' => [],
        'Ave' => [],
        'Roedor' => [],
        'Lagomorfo' => [],
        'Reptil' => [],
        'Otro' => [],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $speciesName => $breeds) {
            $speciesId = $this->globalId('species', ['name' => $speciesName]);

            foreach ($breeds as $breedName) {
                $this->globalId('breeds', ['species_id' => $speciesId, 'name' => $breedName]);
            }
        }
    }

    /**
     * Id del registro global con esos valores; si no existe, lo crea.
     */
    private function globalId(string $table, array $values): int
    {
        $id = DB::table($table)->whereNull('clinic_id')->where($values)->value('id');

        return $id ?? DB::table($table)->insertGetId([
            ...$values,
            'clinic_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
