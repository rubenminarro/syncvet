<?php

namespace Tests\Unit;

use App\Support\DocumentBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Número base del documento de un cliente (R-018).
 */
class DocumentBaseTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function documents(): array
    {
        return [
            'cédula' => ['99999903', '99999903'],
            'cédula con ceros adelante' => ['0099999903', '99999903'],
            'RUC' => ['99999903-6', '99999903'],
            'RUC con un cero adelante' => ['099999903-6', '99999903'],
            'solo ceros' => ['00000', '0'],
            'letras, en mayúsculas' => ['ab-12345', 'AB-12345'],
            'número con dos dígitos después del guion' => ['12345-67', '12345-67'],
            'número con ceros y letras: sin tocar los ceros' => ['00ab1', '00AB1'],
            'dado de baja' => ['099999903-6//deleted_7_1700000000', '99999903//deleted_7_1700000000'],
        ];
    }

    #[DataProvider('documents')]
    public function test_the_base_of_a_document(string $document, string $base): void
    {
        $this->assertSame($base, DocumentBase::of($document));
    }
}
