<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Deuda 16 (FASE 3, entrega 3.0; R-047 y R-049): las horas de negocio las pone PHP (now()
 * de Laravel o el valor del pedido), nunca el reloj de MySQL. Si Paraguay cambiara otra vez
 * de desplazamiento, la zona fija de la sesión (-03:00) correría lo que pone MySQL.
 *
 * Se revisa el código con el tokenizer de PHP, sin los comentarios:
 *
 * - useCurrent() o useCurrentOnUpdate() (una llamada);
 * - una función de reloj de MySQL dentro de un texto (SQL crudo, DB::raw, una consulta), en
 *   cualquier combinación de mayúsculas: NOW(), CURDATE(), CURTIME(), SYSDATE(),
 *   CURRENT_TIMESTAMP, CURRENT_DATE, CURRENT_TIME, LOCALTIME, LOCALTIMESTAMP, UTC_TIMESTAMP,
 *   UTC_DATE, UTC_TIME y UNIX_TIMESTAMP() sin argumentos. El helper now() de Laravel es PHP
 *   y se permite.
 *
 * Las dos columnas de hoy quedan en ALLOWED, por archivo y columna.
 */
class NoDatabaseNowTest extends TestCase
{
    /**
     * Columnas con useCurrent() permitidas, por archivo de migración.
     */
    private const ALLOWED = [
        // failed_jobs.failed_at: tabla del framework, la llena la cola.
        '0001_01_01_000002_create_jobs_table.php' => ['failed_at'],
        // inventory_movements.created_at: el modelo no tiene $timestamps (FASE 5).
        '0001_01_01_000014_create_inventory_movements_table.php' => ['created_at'],
    ];

    private const STRING_TOKENS = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE];

    private const SQL_CLOCK = '/\b(?:now|curdate|curtime|sysdate)\s*\('
        . '|\b(?:current_timestamp|current_date|current_time|localtimestamp|localtime|utc_timestamp|utc_date|utc_time)\b'
        . '|\bunix_timestamp\s*\(\s*\)/i';

    /**
     * Fin de una sentencia: desde ahí se busca la columna de un useCurrent().
     */
    private const STATEMENT_BOUNDARIES = [';', '{', '}'];

    /**
     * @return array<string, array{string}>
     */
    public static function directories(): array
    {
        return [
            'migraciones' => ['database/migrations'],
            'aplicación' => ['app'],
            'seeders' => ['database/seeders'],
            'factories' => ['database/factories'],
            'rutas' => ['routes'],
        ];
    }

    #[DataProvider('directories')]
    public function test_the_code_does_not_use_the_database_clock(string $directory): void
    {
        $this->assertSame(
            [],
            $this->scan($directory),
            'Las horas de negocio las pone PHP: now() de Laravel, nunca el reloj de MySQL ni useCurrent() (CLAUDE.md §3).',
        );
    }

    public function test_the_allowed_uses_still_exist(): void
    {
        // Si una columna permitida cambia o se quita, la lista tiene que achicarse.
        foreach (self::ALLOWED as $file => $columns) {
            $path = $this->root() . '/database/migrations/' . $file;
            $this->assertFileExists($path);

            $found = array_column(
                array_filter(self::findings((string) file_get_contents($path)), fn (array $f): bool => $f['column'] !== null),
                'column',
            );

            foreach ($columns as $column) {
                $this->assertContains($column, $found, "{$file} ya no tiene useCurrent() en {$column}");
            }
        }
    }

    public function test_the_allowed_column_is_identified_by_its_name_not_by_the_line(): void
    {
        // La misma línea con otra columna, en el mismo archivo permitido, no pasa.
        $code = <<<'PHP'
            <?php
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('confirmed_at')->useCurrent();
            $table
                ->timestamp('created_at')
                ->useCurrent();
            PHP;

        $this->assertSame(
            ['3: useCurrent() (confirmed_at)'],
            self::rejected($code, '0001_01_01_000014_create_inventory_movements_table.php'),
        );
        $this->assertCount(3, self::rejected($code, '2026_11_10_000000_create_x_table.php'));
    }

    public function test_the_scanner_finds_each_form(): void
    {
        // Sin esto, un tokenizer que no encuentra nada dejaría pasar todo.
        $code = <<<'PHP'
            <?php
            // NOW() en un comentario no cuenta.
            $table->timestamp('a')->useCurrent();
            $table->timestamp('b')->useCurrentOnUpdate();
            DB::raw('NOW()');
            DB::statement("update x set y = current_timestamp where id = {$id}");
            $at = now();
            DB::raw("now()");
            $table->timestamp('c')->default(DB::raw('CURRENT_TIMESTAMP(6)'));
            $query->selectRaw('Now () as n');
            $query->whereRaw('DATE(starts_at) = CURDATE()');
            $query->whereRaw('starts_at = current_date');
            $query->whereRaw('TIME(starts_at) < CurTime()');
            $query->whereRaw('TIME(starts_at) < CURRENT_TIME');
            $query->whereRaw('starts_at < SYSDATE()');
            $query->whereRaw('starts_at < localtime');
            $query->whereRaw('starts_at < LOCALTIMESTAMP()');
            $query->whereRaw('starts_at < UTC_TIMESTAMP()');
            $query->whereRaw('starts_at = utc_date()');
            $query->whereRaw('TIME(starts_at) < UTC_TIME');
            $query->whereRaw('UNIX_TIMESTAMP( ) > 0');
            $query->selectRaw('UNIX_TIMESTAMP(starts_at) as epoch');
            $query->whereRaw('current_timezone = 1 and nowhere = 2 and localtime_x = 3');
            PHP;

        $this->assertSame(
            [
                '3: useCurrent() (a)',
                '4: useCurrentOnUpdate() (b)',
                "5: 'NOW()'",
                '6: update x set y = current_timestamp where id = ',
                '8: "now()"',
                "9: 'CURRENT_TIMESTAMP(6)'",
                "10: 'Now () as n'",
                "11: 'DATE(starts_at) = CURDATE()'",
                "12: 'starts_at = current_date'",
                "13: 'TIME(starts_at) < CurTime()'",
                "14: 'TIME(starts_at) < CURRENT_TIME'",
                "15: 'starts_at < SYSDATE()'",
                "16: 'starts_at < localtime'",
                "17: 'starts_at < LOCALTIMESTAMP()'",
                "18: 'starts_at < UTC_TIMESTAMP()'",
                "19: 'starts_at = utc_date()'",
                "20: 'TIME(starts_at) < UTC_TIME'",
                "21: 'UNIX_TIMESTAMP( ) > 0'",
            ],
            array_map(self::describe(...), self::findings($code)),
        );
    }

    /**
     * @return string[]
     */
    private function scan(string $directory): array
    {
        $found = [];
        $files = Finder::create()->files()->in($this->root() . '/' . $directory)->name('*.php');

        foreach ($files as $file) {
            foreach (self::rejected($file->getContents(), $file->getFilename()) as $finding) {
                $found[] = "{$directory}/{$file->getRelativePathname()}:{$finding}";
            }
        }

        return $found;
    }

    /**
     * Los usos de un archivo que no están en ALLOWED.
     *
     * @return string[]
     */
    private static function rejected(string $code, string $filename): array
    {
        $allowed = self::ALLOWED[$filename] ?? [];

        return array_values(array_map(
            self::describe(...),
            array_filter(
                self::findings($code),
                fn (array $finding): bool => $finding['column'] === null || ! in_array($finding['column'], $allowed, true),
            ),
        ));
    }

    /**
     * Cada uso, con su línea, su texto y, en un useCurrent(), su columna.
     *
     * @return list<array{line: int, text: string, column: ?string, call: bool}>
     */
    private static function findings(string $code): array
    {
        $found = [];
        $tokens = token_get_all($code);

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            [$type, $text, $line] = $token;

            if ($type === T_STRING && preg_match('/^useCurrent(OnUpdate)?$/i', $text) === 1 && self::isCall($tokens, $index)) {
                $found[] = ['line' => $line, 'text' => $text, 'column' => self::columnOf($tokens, $index), 'call' => true];
            } elseif (in_array($type, self::STRING_TOKENS, true) && preg_match(self::SQL_CLOCK, $text) === 1) {
                $found[] = ['line' => $line, 'text' => $text, 'column' => null, 'call' => false];
            }
        }

        return $found;
    }

    /**
     * @param array{line: int, text: string, column: ?string, call: bool} $finding
     */
    private static function describe(array $finding): string
    {
        if (! $finding['call']) {
            return "{$finding['line']}: {$finding['text']}";
        }

        return "{$finding['line']}: {$finding['text']}() (" . ($finding['column'] ?? 'sin columna') . ')';
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private static function isCall(array $tokens, int $index): bool
    {
        for ($next = $index + 1; isset($tokens[$next]); $next++) {
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                continue;
            }

            return $tokens[$next] === '(';
        }

        return false;
    }

    /**
     * La columna de un useCurrent(): el primer texto de su sentencia, el nombre que recibe
     * timestamp('...') o dateTime('...'). Sin texto, null (nunca permitido).
     *
     * @param array<int, mixed> $tokens
     */
    private static function columnOf(array $tokens, int $index): ?string
    {
        $start = $index;

        while ($start > 0 && ! in_array($tokens[$start - 1], self::STATEMENT_BOUNDARIES, true)) {
            $start--;
        }

        for ($current = $start; $current < $index; $current++) {
            if (is_array($tokens[$current]) && $tokens[$current][0] === T_CONSTANT_ENCAPSED_STRING) {
                return substr($tokens[$current][1], 1, -1);
            }
        }

        return null;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
