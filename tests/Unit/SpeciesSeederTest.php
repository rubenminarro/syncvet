<?php

namespace Tests\Unit;

use App\Support\CatalogName;
use Database\Seeders\SpeciesSeeder;
use PHPUnit\Framework\TestCase;

/**
 * El catálogo global no repite nombres con la comparación de la regla de
 * unicidad (FASE 1, entrega 1.4): ni especies entre sí, ni razas dentro de su
 * especie. Si los repitiera, una clínica no podría distinguirlos.
 */
class SpeciesSeederTest extends TestCase
{
    public function test_global_species_names_are_not_repeated(): void
    {
        $this->assertNoRepeated(array_keys(SpeciesSeeder::CATALOG), 'especies');
    }

    public function test_global_breed_names_are_not_repeated_within_their_species(): void
    {
        foreach (SpeciesSeeder::CATALOG as $species => $breeds) {
            $this->assertNoRepeated($breeds, "razas de {$species}");
        }
    }

    public function test_the_comparison_ignores_case_accents_and_outer_spaces(): void
    {
        $this->assertSame(CatalogName::normalize('Ángora'), CatalogName::normalize(' angora '));
        $this->assertSame(CatalogName::normalize('PASTOR ALEMÁN'), CatalogName::normalize('pastor aleman'));
        $this->assertNotSame(CatalogName::normalize('Pastor Alemán'), CatalogName::normalize('Pastor Belga'));
        $this->assertTrue(CatalogName::isTaken('siames', ['Persa', 'Siamés']));
        $this->assertFalse(CatalogName::isTaken('Siamés', ['Persa']));
    }

    /**
     * @param list<string> $names
     */
    private function assertNoRepeated(array $names, string $what): void
    {
        $normalized = array_map(CatalogName::normalize(...), $names);

        $this->assertSame(
            [],
            array_values(array_unique(array_diff_assoc($normalized, array_unique($normalized)))),
            "Nombres repetidos en las {$what} del catálogo global."
        );
    }
}
