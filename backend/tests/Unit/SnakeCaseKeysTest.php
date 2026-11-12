<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Las claves de la API van en snake_case (entrega front-00, B3; R-058): datos, meta y
 * errores. Hasta la B3, meta.pagination era la única parte en camelCase.
 *
 * Se revisa el código que arma las respuestas (todo app/, también los Resources, los
 * Controllers, las excepciones y los Form Requests, de donde salen las claves de errors, y
 * bootstrap/app.php, que mapea las excepciones) con el tokenizer de PHP: una clave literal
 * de arreglo ('fooBar' => o "fooBar" =>) con una minúscula o un dígito seguidos de una
 * mayúscula falla. Los comentarios no cuentan.
 */
class SnakeCaseKeysTest extends TestCase
{
    private const CAMEL_CASE = '/[a-z0-9][A-Z]/';

    /**
     * @return array<string, array{string}>
     */
    public static function paths(): array
    {
        return [
            'aplicación' => ['app'],
            'mapeo de excepciones' => ['bootstrap/app.php'],
        ];
    }

    #[DataProvider('paths')]
    public function test_the_array_keys_are_in_snake_case(string $path): void
    {
        $files = $this->files($path);
        $this->assertNotEmpty($files, "No se encontró código en {$path}");

        $found = [];
        foreach ($files as $relative => $absolute) {
            foreach (self::camelCaseKeys((string) file_get_contents($absolute)) as $finding) {
                $found[] = "{$relative}:{$finding}";
            }
        }

        $this->assertSame([], $found, 'Las claves de la API van en snake_case (backend/CLAUDE.md §3).');
    }

    public function test_the_scan_covers_the_code_that_builds_the_responses(): void
    {
        // Si el escaneo dejara afuera una carpeta, una clave en camelCase volvería sin aviso.
        $files = array_keys($this->files('app'));

        foreach ([
            'app/Http/Controllers/Api/UserController.php',
            'app/Http/Resources/MeResource.php',
            'app/Http/Requests/IndexUserRequest.php',
            'app/Traits/ApiResponse.php',
            'app/Exceptions/AppException.php',
            'app/Services/AuthService.php',
            'app/Support/SearchTerm.php',
        ] as $expected) {
            $this->assertContains($expected, $files);
        }
    }

    public function test_the_scanner_rejects_an_example_file_that_breaks_the_rule(): void
    {
        // Sin esto, un tokenizer que no encuentra nada dejaría pasar todo.
        $example = <<<'PHP'
            <?php
            // 'inComment' => 1 no cuenta.
            return [
                'pagination' => [
                    'total' => $p->total(),
                    'perPage' => $p->perPage(),
                    "currentPage" => $p->currentPage(),
                    'last_page' => $p->lastPage(),
                ],
                'error_code' => 'X',
                'errors' => ['branch_id' => ['x'], 'fieldName' => ['y']],
                'v2Key' => 1,
                'Content-Type' => 'a',
                'URL' => 'b',
                'call' => $this->camelCaseMethod('notAKey'),
                ['inList', 'alsoNotAKey'],
            ];
            PHP;

        $file = tempnam(sys_get_temp_dir(), 'snake');
        file_put_contents($file, $example);

        try {
            $this->assertSame(
                ["6: 'perPage'", '7: "currentPage"', "11: 'fieldName'", "12: 'v2Key'"],
                self::camelCaseKeys((string) file_get_contents($file)),
            );
        } finally {
            unlink($file);
        }
    }

    /**
     * @return list<string> "línea: clave" de cada clave literal en camelCase
     */
    private static function camelCaseKeys(string $code): array
    {
        $tokens = token_get_all($code);
        $count = count($tokens);
        $found = [];

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $next = $i + 1;
            while ($next < $count && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $next++;
            }

            if (! is_array($tokens[$next] ?? null) || $tokens[$next][0] !== T_DOUBLE_ARROW) {
                continue;
            }

            if (preg_match(self::CAMEL_CASE, substr($tokens[$i][1], 1, -1)) === 1) {
                $found[] = "{$tokens[$i][2]}: {$tokens[$i][1]}";
            }
        }

        return $found;
    }

    /**
     * @return array<string, string> ruta relativa => ruta absoluta
     */
    private function files(string $path): array
    {
        $absolute = $this->root() . '/' . $path;

        if (is_file($absolute)) {
            return [$path => $absolute];
        }

        $files = [];
        foreach ((new Finder())->files()->in($absolute)->name('*.php') as $file) {
            $files[$path . '/' . str_replace('\\', '/', $file->getRelativePathname())] = $file->getRealPath();
        }

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
