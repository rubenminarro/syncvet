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
 * bootstrap/app.php, que mapea las excepciones) con el tokenizer de PHP, sin los comentarios.
 * Falla una clave literal con una minúscula o un dígito seguidos de una mayúscula:
 *
 * - en un arreglo: ['fooBar' => …] o array("fooBar" => …), también anidado;
 * - asignada por índice: $data['fooBar'] = …, $data['a']['fooBar'] ??= …;
 * - en compact('fooBar') y como clave de Arr::set, Arr::add, data_set o data_fill.
 *
 * No cuentan (revisión, H5): los brazos de un match ('macOS' => …), que no son claves, ni los
 * arreglos que se pasan al log (Log::warning(…, ['clinicId' => …]), logger(…),
 * ->warning(…)), que no salen en una respuesta. Los valores (un nombre, un mensaje) nunca cuentan.
 */
class SnakeCaseKeysTest extends TestCase
{
    private const CAMEL_CASE = '/[a-z0-9][A-Z]/';

    private const IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    private const LOG_METHODS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'];

    private const ASSIGNMENTS = [
        T_COALESCE_EQUAL, T_CONCAT_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL,
        T_MOD_EQUAL, T_POW_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL,
    ];

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
            'app/Http/Resources/QueueAppointmentResource.php',
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
            // 'inComment' => 1 y $data['inComment'] = 1 no cuentan.
            return [
                'pagination' => [
                    'total' => $p->total(),
                    'perPage' => $p->perPage(),
                    "currentPage" => $p->currentPage(),
                    'last_page' => $p->lastPage(),
                ],
                'error_code' => 'X',
                'errors' => ['branch_id' => ['x'], 'fieldName' => ['y'], 'conflicts' => [['startsAt' => 1]]],
                'v2Key' => 1,
                'Content-Type' => 'a',
                'URL' => 'b',
                'call' => $this->camelCaseMethod('notAKey'),
                ['inList', 'alsoNotAKey'],
                'old' => array('oldStyle' => 1),
            ];
            $data['checkedInAt'] = $x;
            $data['patient']['ownerName'] ??= $y;
            $data['checked_in_at'] = $x;
            $read = $data['readOnly'];
            $same = $data['compared'] == 1;
            $packed = compact('fooBar', 'foo_bar');
            Arr::set($data, 'meta.seriesId', 1);
            data_set($data, 'meta.series_id', 1);
            PHP;

        $file = tempnam(sys_get_temp_dir(), 'snake');
        file_put_contents($file, $example);

        try {
            $this->assertSame(
                [
                    "6: 'perPage'",
                    '7: "currentPage"',
                    "11: 'fieldName'",
                    "11: 'startsAt'",
                    "12: 'v2Key'",
                    "17: 'oldStyle'",
                    "19: 'checkedInAt'",
                    "20: 'ownerName'",
                    "24: 'fooBar'",
                    "25: 'meta.seriesId'",
                ],
                self::camelCaseKeys((string) file_get_contents($file)),
            );
        } finally {
            unlink($file);
        }
    }

    /**
     * Revisión, H5: sin falsos positivos con valores, brazos de match ni el contexto del log.
     */
    public function test_values_match_arms_and_log_context_do_not_count(): void
    {
        $example = <<<'PHP'
            <?php
            $a = ['name' => 'Casa Central', 'message' => 'La sucursal SUC-01 no existe, McDonald', 'title' => "iPhone de Ana"];
            $b = match ($kind) { 'macOS' => 1, 'iPad', 'iPhone' => 2, default => ['ok_key' => 'fooBar'] };
            Log::warning('Algo pasó', ['clinicId' => 1, 'nested' => ['userId' => 2]]);
            \Illuminate\Support\Facades\Log::error('x', ['branchId' => 1]);
            logger('x', ['scheduleId' => 1]);
            $this->logger->info('x', ['appointmentId' => 1]);
            $c = ['branch_ids.*' => ['integer'], 'errors' => ['conflicts' => [['starts_at' => 1]]]];
            #[SomeAttribute(['attrKey' => 1])]
            $d = "texto {$x['inString']}";
            PHP;

        $this->assertSame([], self::camelCaseKeys($example));
    }

    /**
     * @return list<string> "línea: clave" de cada clave literal en camelCase
     */
    private static function camelCaseKeys(string $code): array
    {
        $tokens = array_values(array_filter(
            token_get_all($code),
            fn ($token) => ! is_array($token) || ! in_array($token[0], self::IGNORED, true),
        ));
        $count = count($tokens);
        $found = [];
        // Contexto de cada [, ( y { abierto: array, match, log u otro.
        $stack = [];
        $matchPending = false;

        $flag = function (array $token) use (&$found): void {
            if (preg_match(self::CAMEL_CASE, substr($token[1], 1, -1)) === 1) {
                $found[] = "{$token[2]}: {$token[1]}";
            }
        };

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;

            if ($id === T_MATCH) {
                $matchPending = true;
            } elseif ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $stack[] = $matchPending && $text === '{' ? 'match' : 'brace';
                $matchPending = false;
            } elseif ($text === '[' || $id === T_ATTRIBUTE) {
                $stack[] = $id === T_ATTRIBUTE ? 'attribute' : 'array';
            } elseif ($text === '(') {
                $stack[] = self::parenContext($tokens, $i, $matchPending);
            } elseif ($text === ']' || $text === ')' || $text === '}') {
                array_pop($stack);
            }

            if ($id !== T_CONSTANT_ENCAPSED_STRING || in_array('log', $stack, true) || in_array('attribute', $stack, true)) {
                continue;
            }

            $next = $tokens[$i + 1] ?? null;
            $previous = $tokens[$i - 1] ?? null;

            // Clave de un arreglo: el contexto más interno es un arreglo (no un match).
            if (is_array($next) && $next[0] === T_DOUBLE_ARROW && end($stack) === 'array') {
                $flag($token);

                continue;
            }

            // Asignación por índice: $x['clave'] = … (también anidada y con ??=, .=, …).
            if ($previous === '[' && $next === ']' && self::isAssignedAfter($tokens, $i + 1)) {
                $flag($token);

                continue;
            }

            // compact('clave') y la clave de Arr::set/Arr::add/data_set/data_fill.
            if (self::isKeyArgument($tokens, $i)) {
                $flag($token);
            }
        }

        return $found;
    }

    /**
     * Contexto de un "(": array( …), una llamada al log (Log::warning, logger, ->warning…), la
     * condición de un match (match (…)) u otro paréntesis.
     *
     * @param list<mixed> $tokens
     */
    private static function parenContext(array $tokens, int $i, bool $matchPending): string
    {
        $callee = $tokens[$i - 1] ?? null;

        if (is_array($callee) && $callee[0] === T_ARRAY) {
            return 'array';
        }

        if (! is_array($callee) || $callee[0] !== T_STRING) {
            return $matchPending ? 'match-subject' : 'paren';
        }

        $name = strtolower($callee[1]);
        $operator = $tokens[$i - 2] ?? null;
        $owner = $tokens[$i - 3] ?? null;

        if ($name === 'logger' && ! (is_array($operator) && in_array($operator[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true))) {
            return 'log';
        }

        if (in_array($name, self::LOG_METHODS, true) && is_array($operator)) {
            if (in_array($operator[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                return 'log';
            }

            if ($operator[0] === T_DOUBLE_COLON && is_array($owner) && preg_match('/(^|\\\\)Log$/', $owner[1]) === 1) {
                return 'log';
            }
        }

        return 'paren';
    }

    /**
     * Después del "]" de $x['clave'], saltea otros índices ([…]) y mira si sigue una asignación.
     *
     * @param list<mixed> $tokens
     */
    private static function isAssignedAfter(array $tokens, int $close): bool
    {
        $j = $close + 1;
        while (($tokens[$j] ?? null) === '[') {
            $depth = 0;
            do {
                $depth += match ($tokens[$j] ?? null) { '[' => 1, ']' => -1, default => 0 };
                $j++;
            } while ($depth > 0 && $j < count($tokens));
        }

        $next = $tokens[$j] ?? null;

        return $next === '=' || (is_array($next) && in_array($next[0], self::ASSIGNMENTS, true));
    }

    /**
     * Un argumento de compact(…) o el segundo de Arr::set, Arr::add, data_set y data_fill.
     *
     * @param list<mixed> $tokens
     */
    private static function isKeyArgument(array $tokens, int $i): bool
    {
        // Retrocede hasta el "(" de la llamada, contando las comas del mismo nivel.
        $commas = 0;
        $depth = 0;
        for ($j = $i - 1; $j >= 0; $j--) {
            $token = $tokens[$j];
            if ($token === ')' || $token === ']') {
                $depth++;
            } elseif ($token === '(' || $token === '[') {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($token === ',' && $depth === 0) {
                $commas++;
            }
        }

        if (($tokens[$j] ?? null) !== '(') {
            return false;
        }

        $callee = $tokens[$j - 1] ?? null;
        if (! is_array($callee) || $callee[0] !== T_STRING) {
            return false;
        }

        $name = strtolower($callee[1]);
        $operator = $tokens[$j - 2] ?? null;
        $owner = $tokens[$j - 3] ?? null;
        $isMethodOrStatic = is_array($operator) && in_array($operator[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);

        if ($name === 'compact' && ! $isMethodOrStatic) {
            return true;
        }

        if (in_array($name, ['data_set', 'data_fill'], true) && ! $isMethodOrStatic) {
            return $commas === 1;
        }

        return in_array($name, ['set', 'add'], true)
            && is_array($operator) && $operator[0] === T_DOUBLE_COLON
            && is_array($owner) && preg_match('/(^|\\\\)Arr$/', $owner[1]) === 1
            && $commas === 1;
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
