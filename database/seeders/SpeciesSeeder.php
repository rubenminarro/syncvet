<?php

namespace Database\Seeders;

use App\Models\Breed;
use App\Models\Species;
use Illuminate\Database\Seeder;

/**
 * Especies y razas frecuentes. Idempotente.
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
            $species = Species::firstOrCreate(['name' => $speciesName]);

            foreach ($breeds as $breedName) {
                Breed::firstOrCreate(['species_id' => $species->id, 'name' => $breedName]);
            }
        }
    }
}
