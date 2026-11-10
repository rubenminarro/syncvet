<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Deuda 16 (FASE 3, entrega 3.0; R-047): las horas de negocio las pone PHP (now() de
 * Laravel o el valor del pedido), nunca el reloj de MySQL. Si Paraguay cambiara otra vez
 * de desplazamiento, la zona fija de la sesión (-03:00) correría lo que pone MySQL.
 *
 * Se revisa el código con el tokenizer de PHP, sin los comentarios:
 *
 * - useCurrent() o useCurrentOnUpdate() (una llamada), en las migraciones y en app/;
 * - NOW() o CURRENT_TIMESTAMP dentro de un texto (SQL crudo, DB::raw, una consulta), en
 *   cualquier combinación de mayúsculas. El helper now() de Laravel es PHP y se permite.
 *
 * Las dos columnas de hoy quedan en ALLOWED.
 */
class NoDatabaseNowTest extends TestCase
{
    /**
     * Usos permitidos, por archivo y con la línea exacta (sin espacios en los extremos).
     */
    private const ALLOWED = [
        // failed_jobs.failed_at: tabla del framework, la llena la cola.
        '0001_01_01_000002_create_jobs_table.php' => ["\$table->timestamp('failed_at')->useCurrent();"],
        // inventory_movements.created_at: el modelo no tiene $timestamps (FASE 5).
        '0001_01_01_000014_create_inventory_movements_table.php' => ["\$table->timestamp('created_at')->useCurrent();"],
    ];

    private const STRING_TOKENS = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE];

    private const SQL_CLOCK = '/\bnow\s*\(|\bcurrent_timestamp\b/i';

    public function test_migrations_do_not_use_the_database_clock(): void
    {
        $this->assertSame(
            [],
            $this->scan('database/migrations'),
            'Las horas de negocio las pone PHP: columnas datetime sin useCurrent() (CLAUDE.md §3).',
        );
    }

    public function test_the_application_does_not_use_the_database_clock_in_raw_sql(): void
    {
        $this->assertSame(
            [],
            $this->scan('app'),
            'Las horas de negocio las pone PHP: now() de Laravel, nunca NOW() de MySQL (CLAUDE.md §3).',
        );
    }

    public function test_the_allowed_uses_still_exist(): void
    {
        // Si una columna permitida cambia o se quita, la lista tiene que achicarse.
        foreach (self::ALLOWED as $file => $lines) {
            $path = $this->root() . '/database/migrations/' . $file;
            $this->assertFileExists($path);

            $contents = array_map('trim', file($path, FILE_IGNORE_NEW_LINES));

            foreach ($lines as $line) {
                $this->assertContains($line, $contents, "{$file} ya no tiene: {$line}");
            }
        }
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
            PHP;

        $this->assertSame(
            [
                '3: useCurrent()',
                '4: useCurrentOnUpdate()',
                "5: 'NOW()'",
                '6: update x set y = current_timestamp where id = ',
            ],
            self::findings($code),
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
            $contents = $file->getContents();
            $lines = explode("\n", $contents);
            $allowed = self::ALLOWED[$file->getFilename()] ?? [];

            foreach (self::findings($contents) as $finding) {
                $number = (int) strstr($finding, ':', true);

                if (! in_array(trim($lines[$number - 1] ?? ''), $allowed, true)) {
                    $found[] = "{$directory}/{$file->getRelativePathname()}:{$finding}";
                }
            }
        }

        return $found;
    }

    /**
     * Cada uso como "línea: texto".
     *
     * @return string[]
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
                $found[] = "{$line}: {$text}()";
            } elseif (in_array($type, self::STRING_TOKENS, true) && preg_match(self::SQL_CLOCK, $text) === 1) {
                $found[] = "{$line}: {$text}";
            }
        }

        return $found;
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

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
