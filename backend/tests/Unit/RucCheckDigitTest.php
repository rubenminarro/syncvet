<?php

namespace Tests\Unit;

use App\Services\ClinicService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dígito verificador del RUC (módulo 11 de la SET). Es el único test con RUC reales
 * (R-010): los demás usan RUC ficticios.
 */
class RucCheckDigitTest extends TestCase
{
    /**
     * RUC publicados por la DNIT (ranking de contribuyentes IRACIS e IMAGRO de 2010).
     * El algoritmo coincide con los 815 RUC de los rankings de 2010 y 2011.
     */
    #[DataProvider('publishedRucs')]
    public function test_it_matches_published_rucs(string $ruc, int $dv): void
    {
        $this->assertSame($dv, ClinicService::rucCheckDigit($ruc));
    }

    public static function publishedRucs(): array
    {
        return [
            'Telecel' => ['80000519', 8],
            'Petropar' => ['80002675', 6],
            'Banco Itaú' => ['80002201', 7],
            // Resto 0 y resto 1: los dos dan 0.
            'Cervecería Paraguaya (resto 0)' => ['80003457', 0],
            'DHL Paraguay (resto 0)' => ['80004866', 0],
            'BBVA Paraguay (resto 1)' => ['80022955', 0],
            'Agrotec (resto 1)' => ['80020753', 0],
        ];
    }

    /**
     * Con 11 dígitos o más el factor vuelve a 2 después de 11. No hay RUC reales tan
     * largos (los publicados tienen hasta 8): el caso se calcula a mano.
     *
     * 12345678901, de derecha a izquierda con factores 2 a 11 y otra vez 2:
     * 1·2 + 0·3 + 9·4 + 8·5 + 7·6 + 6·7 + 5·8 + 4·9 + 3·10 + 2·11 + 1·2 = 292.
     * 292 mod 11 = 6, dígito 11 − 6 = 5. Sin volver a 2, el último factor sería 12:
     * 302 mod 11 = 5, dígito 6.
     */
    public function test_the_factor_goes_back_to_2_after_11(): void
    {
        $this->assertSame(5, ClinicService::rucCheckDigit('12345678901'));
    }

    /**
     * 9 y 10 dígitos: llegan a los factores 10 y 11 sin volver a 2.
     * 123456789: 9·2 + 8·3 + 7·4 + 6·5 + 5·6 + 4·7 + 3·8 + 2·9 + 1·10 = 210; 210 mod 11 = 1 → 0.
     * 1234567890: 0·2 + 9·3 + 8·4 + 7·5 + 6·6 + 5·7 + 4·8 + 3·9 + 2·10 + 1·11 = 255; 255 mod 11 = 2 → 9.
     */
    public function test_nine_and_ten_digits(): void
    {
        $this->assertSame(0, ClinicService::rucCheckDigit('123456789'));
        $this->assertSame(9, ClinicService::rucCheckDigit('1234567890'));
    }
}
