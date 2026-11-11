<?php

namespace Tests\Unit;

use App\Support\CatalogName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nombre de una especie o una raza (App\Support\CatalogName): cómo se guarda y
 * cómo se compara (FASE 1, entrega 1.4, y su revisión).
 */
class CatalogNameTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cleanNames(): array
    {
        return [
            'sin cambios' => ['Pastor Alemán', 'Pastor Alemán'],
            'extremos' => ['  Hurón ', 'Hurón'],
            'dos espacios' => ['Pastor  Alemán', 'Pastor Alemán'],
            'tab' => ["Pastor\tAlemán", 'Pastor Alemán'],
            'espacio duro' => ["Pastor\u{00A0}\u{00A0}Alemán", 'Pastor Alemán'],
            'varios' => ["  Cuarto \t de   Milla  ", 'Cuarto de Milla'],
        ];
    }

    #[DataProvider('cleanNames')]
    public function test_clean_trims_and_collapses_spaces(string $name, string $expected): void
    {
        $this->assertSame($expected, CatalogName::clean($name));
    }

    public function test_names_with_other_case_accents_or_spaces_are_taken(): void
    {
        $this->assertTrue(CatalogName::isTaken('pastor  aleman', ['Pastor Alemán']));
        $this->assertTrue(CatalogName::isTaken('Pastor Alemán', ["Pastor\t\tAlemán"]));
        $this->assertTrue(CatalogName::isTaken(' ÁNGORA ', ['angora']));
        $this->assertFalse(CatalogName::isTaken('PastorAlemán', ['Pastor Alemán']));
    }
}
