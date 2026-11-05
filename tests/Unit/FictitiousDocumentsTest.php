<?php

namespace Tests\Unit;

use App\Services\ClinicService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Documentos de ejemplo ficticios (R-011, deuda 7): los números que aparecen como
 * literales en los tests, las factories y los seeders no pueden ser de empresas ni
 * de personas reales.
 *
 * - RUC (`ruc`, `--ruc`, `rucCheckDigit(...)`): 999999xx.
 * - Documento de un cliente (`document_number`): cédula 99xxxxxx, o RUC de empresa
 *   999999xx-d con su dígito verificador.
 *
 * Solo se revisan literales numéricos: un valor armado en el código ('DOC' . $n) o
 * con letras a propósito ('9999991A', un RUC inválido) no es un documento real. Los
 * RUC reales solo están en RucCheckDigitTest, que contrasta el algoritmo con los de
 * la DNIT.
 */
class FictitiousDocumentsTest extends TestCase
{
    private const EXCLUDED = ['RucCheckDigitTest.php', 'FictitiousDocumentsTest.php'];

    private const PATTERNS = [
        'ruc' => "/['\"]-{0,2}ruc['\"]\s*(?:=>|:)\s*['\"]([0-9-]+)['\"]/",
        'rucCheckDigit' => "/rucCheckDigit\(\s*['\"]([0-9-]+)['\"]\s*\)/",
        'document_number' => "/['\"]document_number['\"]\s*(?:=>|:)\s*['\"]([0-9-]+)['\"]/",
    ];

    public function test_example_documents_in_tests_factories_and_seeders_are_fictitious(): void
    {
        $failures = [];
        $checked = ['ruc' => 0, 'document_number' => 0];

        foreach ($this->files() as $path => $content) {
            foreach (self::PATTERNS as $kind => $pattern) {
                preg_match_all($pattern, $content, $matches);

                foreach ($matches[1] as $value) {
                    $type = $kind === 'document_number' ? 'document_number' : 'ruc';
                    $checked[$type]++;

                    if (! $this->isFictitious($type, $value)) {
                        $failures[] = "{$path}: {$kind} {$value}";
                    }
                }
            }
        }

        $this->assertSame([], $failures, 'Documentos de ejemplo fuera del rango ficticio (RUC 999999xx, cédulas 99xxxxxx).');

        // Que el test no pase en vacío si cambia el formato de los archivos.
        $this->assertGreaterThan(5, $checked['ruc'], 'No se encontraron RUC de ejemplo.');
        $this->assertGreaterThan(5, $checked['document_number'], 'No se encontraron documentos de ejemplo.');
    }

    /**
     * El propio test reconoce un documento real como no ficticio.
     */
    public function test_it_rejects_real_looking_documents(): void
    {
        $this->assertFalse($this->isFictitious('ruc', '80009735'));
        $this->assertFalse($this->isFictitious('document_number', '4567890'));
        $this->assertFalse($this->isFictitious('document_number', '80024680-2'));
        $this->assertFalse($this->isFictitious('document_number', '99999903-5'), 'Dígito verificador incorrecto');
        $this->assertTrue($this->isFictitious('document_number', '99999903-6'));
        $this->assertTrue($this->isFictitious('document_number', '99400001'));
        $this->assertTrue($this->isFictitious('ruc', '99999910'));
    }

    private function isFictitious(string $type, string $value): bool
    {
        if ($type === 'ruc') {
            return preg_match('/^999999\d{2}$/', $value) === 1;
        }

        if (preg_match('/^(999999\d{2})-(\d)$/', $value, $match) === 1) {
            return ClinicService::rucCheckDigit($match[1]) === (int) $match[2];
        }

        return preg_match('/^99\d{6}$/', $value) === 1;
    }

    /**
     * @return array<string, string> ruta relativa => contenido
     */
    private function files(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        $finder = Finder::create()->files()->name('*.php')
            ->in(["{$root}/tests", "{$root}/database/factories", "{$root}/database/seeders"]);

        foreach ($finder as $file) {
            if (in_array($file->getFilename(), self::EXCLUDED, true)) {
                continue;
            }

            $files[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = $file->getContents();
        }

        return $files;
    }
}
