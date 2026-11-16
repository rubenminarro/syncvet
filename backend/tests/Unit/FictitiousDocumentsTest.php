<?php

namespace Tests\Unit;

use App\Services\ClinicService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Documentos de ejemplo ficticios (R-011, R-013): ningún número de los tests, las
 * factories, los seeders, los fixtures, la guía de Postman, sus colecciones ni el frontend (src y e2e) puede ser una cédula
 * o un RUC de una persona o empresa real.
 *
 * Se revisa toda secuencia de 7 a 9 dígitos, escrita como sea: con o sin comillas,
 * con puntos o espacios de miles (0.456.789, 0 456 789) y con o sin dígito
 * verificador (80024680-2, 80024680 - 2). No importa en qué clave ni en qué
 * expresión esté: un número suelto, un argumento con nombre o una opción
 * `--ruc=...` se revisan igual. Rangos ficticios:
 *
 * - 9 dígitos: RUC que hoy no se emiten (la ClinicFactory, R-013).
 * - 8 dígitos que empiezan con 99: cédulas 99xxxxxx y RUC 999999xx (R-011).
 * - 7 dígitos: nunca, es el rango de las cédulas reales.
 *
 * Con dígito verificador, además, el dígito tiene que ser el correcto. Un número
 * fuera de rango solo pasa si está en ALLOWED para ese archivo, con su motivo. Los
 * valores armados en el código (`'DOC' . $n`) y los números pegados a letras o de
 * 10 o más dígitos (teléfonos con +595) no son documentos.
 */
class FictitiousDocumentsTest extends TestCase
{
    /**
     * Directorios revisados (con tests/Fixtures) y archivos sueltos.
     */
    private const DIRECTORIES = ['tests', 'database/factories', 'database/seeders'];

    /**
     * Documentación revisada, relativa a la raíz del repositorio (monorepo, R-057): la guía de Postman y sus colecciones.
     */
    private const DOCS = ['docs/05-pruebas/guia-postman.md', 'docs/05-pruebas/postman/*.json'];

    /**
     * El frontend (*.ts y *.tsx), relativo a la raíz del repositorio: el código, los tests de Vitest y los casos de
     * Playwright (revisión de la entrega 1.2 del frontend).
     */
    private const FRONTEND = ['frontend/src', 'frontend/e2e'];

    /**
     * Documentos reales permitidos, por archivo y con su motivo. Se anotan los
     * dígitos, sin separadores ni dígito verificador.
     *
     * @var array<string, array{reason: string, documents: list<string>}>
     */
    private const ALLOWED = [
        'tests/Unit/RucCheckDigitTest.php' => [
            'reason' => 'RUC publicados por la DNIT, para contrastar el algoritmo del dígito verificador (R-010)',
            'documents' => ['80000519', '80002675', '80002201', '80003457', '80004866', '80022955', '80020753'],
        ],
        'docs/05-pruebas/postman/syncvet-fase-01-clientes.postman_collection.json' => [
            'reason' => 'Una base ficticia (99xxxxxx) con el dígito verificador mal a propósito: la colección comprueba que la API rechace un RUC con el dígito incorrecto (el correcto es 9)',
            'documents' => ['99300002'],
        ],
        'frontend/src/features/clients/ruc.test.ts' => [
            'reason' => 'Los mismos RUC de tests/Unit/RucCheckDigitTest.php: públicos, de empresas (la serie de las personas jurídicas, que empieza con 80), publicados por la DNIT; contrastan rucCheckDigit del frontend con el backend (R-010; revisión de la 1.2)',
            'documents' => ['80000519', '80002675', '80002201', '80003457', '80004866', '80022955', '80020753'],
        ],
        'frontend/src/lib/format/money.ts' => [
            'reason' => 'Un importe en guaraníes en el comentario de formatGuaranies (Gs. 1.234.567), no un documento',
            'documents' => ['1234567'],
        ],
        'frontend/src/lib/format/money.test.ts' => [
            'reason' => 'Importes en guaraníes de los tests de formatGuaranies (1234567 → Gs. 1.234.567), no documentos',
            'documents' => ['1234567'],
        ],
        'tests/Unit/FictitiousDocumentsTest.php' => [
            'reason' => 'Ejemplos de documentos reales que este test tiene que rechazar. Además repite, en esta lista, los de los demás archivos',
            'documents' => ['80009735', '0456789', '80024680', '98999999', '0999999'],
        ],
    ];

    /**
     * Una secuencia de 7 a 9 dígitos (o con separadores de miles), con su dígito
     * verificador opcional. No forma parte de un número más largo ni de una palabra.
     */
    private const NUMBER = '/(?<![\p{L}\d_.,])(\d{1,3}(?:[. ]\d{3}){2}|\d{7,9})(?:\s*-\s*(\d))?(?![\p{L}\d_]|[.,]\d)/u';

    public function test_example_documents_are_fictitious(): void
    {
        $failures = [];
        $checked = 0;
        $used = [];

        foreach ($this->files() as $path => $content) {
            foreach ($this->documents($content) as $document) {
                $checked++;

                if ($this->isFictitious($document['digits'], $document['dv'])) {
                    continue;
                }

                if (in_array($document['digits'], $this->allowedIn($path), true)) {
                    $used[$path][$document['digits']] = true;
                    continue;
                }

                $failures[] = "{$path}:{$document['line']}: {$document['text']}";
            }
        }

        $this->assertSame([], $failures, 'Documentos de ejemplo fuera del rango ficticio (9 dígitos, 99xxxxxx o RUC 999999xx con su dígito verificador). Si es a propósito, agregarlo con su motivo en ALLOWED.');

        // La lista permitida no guarda documentos que ya no se usan.
        foreach (self::ALLOWED as $path => $allowed) {
            $this->assertNotSame('', trim($allowed['reason']), "{$path} no tiene motivo en ALLOWED.");

            foreach ($allowed['documents'] as $digits) {
                $this->assertArrayHasKey($digits, $used[$path] ?? [], "{$digits} figura en ALLOWED para {$path} pero el archivo no lo usa.");
            }
        }

        // Que el test no pase en vacío.
        $this->assertGreaterThan(50, $checked, 'No se encontraron documentos de ejemplo.');
    }

    /**
     * Las formas de escribir un documento real que la versión anterior del test no
     * veía (revisión de la entrega 0.5), más la de control: todas se detectan.
     */
    public function test_it_finds_a_real_document_written_in_any_form(): void
    {
        $forms = [
            "\$a = ['ruc' => '80009735'];",
            "\$b = ['document_number' => 0456789];",
            "\$client->document_number = '0456789';",
            "\$c = new ClinicData(ruc: '80009735');",
            "\$d = ['--ruc=80009735'];",
            "\$e = ['document_number' => '0.456.789'];",
            "\$f = ['document_number' => '80024680 - 2'];",
            "\$g = sprintf('%s', '0456789');",
            '{"document_number": "0 456 789"}',
            '| `POST /api/clients` | `{"document_number":"80024680-2"}` |',
        ];

        foreach ($forms as $form) {
            $documents = $this->documents($form);

            $this->assertCount(1, $documents, "No se encontró el documento en: {$form}");
            $this->assertFalse($this->isFictitious($documents[0]['digits'], $documents[0]['dv']), "Se aceptó como ficticio: {$form}");
        }
    }

    /**
     * Lo que no es un documento no se revisa, y los rangos ficticios se aceptan.
     */
    public function test_it_tells_fictitious_documents_and_other_numbers_apart(): void
    {
        foreach (['+595981100001', '2026-11-05', 'DOC000001', 'TST-000001', 'VET-000001', '1234567890', 'a1234567'] as $other) {
            $this->assertSame([], $this->documents("'{$other}'"), "{$other} no es un documento.");
        }

        $this->assertTrue($this->isFictitious('99400001', null));
        $this->assertTrue($this->isFictitious('99999910', null));
        $this->assertTrue($this->isFictitious('99999903', 6));
        $this->assertTrue($this->isFictitious('123456789', 0));
        $this->assertTrue($this->isFictitious('981123456', null));
        $this->assertFalse($this->isFictitious('99999903', 5), 'Dígito verificador incorrecto');
        $this->assertFalse($this->isFictitious('98999999', null), 'Fuera de 99xxxxxx');
        $this->assertFalse($this->isFictitious('0999999', null), 'Siete dígitos: rango de cédulas reales');

        // Un documento ficticio con un dígito verificador incorrecto se detecta como tal.
        $wrong = $this->documents("'99999903" . "-5'");
        $this->assertSame([['digits' => '99999903', 'dv' => 5]], array_map(fn ($d) => ['digits' => $d['digits'], 'dv' => $d['dv']], $wrong));
    }

    /**
     * Los documentos permitidos en un archivo. Este test, además, nombra en ALLOWED
     * los de los demás.
     *
     * @return list<string>
     */
    private function allowedIn(string $path): array
    {
        $allowed = self::ALLOWED[$path]['documents'] ?? [];

        if ($path === 'tests/Unit/FictitiousDocumentsTest.php') {
            $allowed = array_merge($allowed, ...array_column(self::ALLOWED, 'documents'));
        }

        return $allowed;
    }

    private function isFictitious(string $digits, ?int $dv): bool
    {
        if ($dv !== null && ClinicService::rucCheckDigit($digits) !== $dv) {
            return false;
        }

        return match (strlen($digits)) {
            9 => true,
            8 => str_starts_with($digits, '99'),
            default => false,
        };
    }

    /**
     * @return list<array{text: string, digits: string, dv: ?int, line: int}>
     */
    private function documents(string $content): array
    {
        preg_match_all(self::NUMBER, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        return array_map(fn (array $match) => [
            'text' => $match[0][0],
            'digits' => preg_replace('/\D/', '', $match[1][0]),
            'dv' => $match[2][0] === null ? null : (int) $match[2][0],
            'line' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
        ], $matches);
    }

    /**
     * @return array<string, string> ruta relativa => contenido
     */
    private function files(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        $finder = Finder::create()->files()->name(['*.php', '*.json', '*.md'])
            ->in(array_map(fn (string $directory) => "{$root}/{$directory}", self::DIRECTORIES));

        foreach ($finder as $file) {
            $files[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = $file->getContents();
        }

        // La documentación vive fuera de backend/: se nombra desde la raíz del repositorio.
        $repository = dirname($root);

        foreach (self::DOCS as $pattern) {
            $found = glob("{$repository}/{$pattern}");
            $this->assertNotEmpty($found, "No se encontró {$pattern} en la raíz del repositorio.");

            foreach ($found as $path) {
                $files[substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $repository)) + 1)] = file_get_contents($path);
            }
        }

        // El frontend también vive fuera de backend/: sus rutas, desde la raíz (frontend/src/…).
        $frontend = Finder::create()->files()->name(['*.ts', '*.tsx'])
            ->in(array_map(fn (string $directory) => "{$repository}/{$directory}", self::FRONTEND));
        $this->assertTrue($frontend->hasResults(), 'No se encontraron archivos del frontend en ' . implode(', ', self::FRONTEND) . '.');

        foreach ($frontend as $file) {
            $files[substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $repository)) + 1)] = $file->getContents();
        }

        return $files;
    }
}
