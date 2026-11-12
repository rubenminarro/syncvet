<?php

/**
 * Pruebas de regresión de check-docs.php. No dependen del estado del repositorio: arman un proyecto
 * de ejemplo en una carpeta temporal y corren el validador real sobre él.
 *   php .claude/skills/validar-docs/scripts/check-docs-test.php
 */

require __DIR__ . '/figures.php';

$failures = 0;
$passed = 0;

function check(string $name, bool $condition, string $detail = ''): void
{
    global $failures, $passed;

    if ($condition) {
        $passed++;
        echo "  ok    $name\n";
    } else {
        $failures++;
        echo "  FALLA $name\n" . ($detail === '' ? '' : "        $detail\n");
    }
}

function typeOf(string $line): array
{
    preg_match('/\b\d+ (tests|rutas|controllers|services|migraciones|permisos)\b/u', $line, $match, PREG_OFFSET_CAPTURE);

    return classifyFigure($line, $match[0][1], strlen($match[0][0]));
}

echo "Clasificación\n";

foreach ([
    ['current', '| `app/Services` | Lógica de negocio (45 services) |'],
    ['current', '| `app/Services` | Reglas de negocio (47 services, incluido `AuditLogger`) |'],
    ['current', '| `app/Http/Controllers/Api` | 58 controllers |'],
    ['current', 'Contado el 2026-11-01: 489 tests en `tests/Feature`.'],
    ['current', '284 rutas API, 489 tests en verde.'],
    ['current', 'Hay 45 services. Antes de empezar, leer el índice.'],
    ['class', 'Además, `DocumentSequence` (6 tests) cubre los códigos internos.'],
    ['historical', 'Antes: 460 tests con M9.'],
    ['historical', 'La línea base eran 374 tests.'],
    ['historical', 'Hasta el 2026-10-29 había 460 tests.'],
    ['frontend', '| Cada flujo | Solo hay 43 tests del frontend. |'],
    ['current', '| Frontend y backend | 45 services |'],
    ['frontend', 'Frontend: 43 tests.'],
    ['current', 'Frontend y backend: 45 services.'],
    ['historical', 'El frontend antes tenía 40 tests.'],
    ['test-file', 'Además, `client.test.ts` (12 tests) cubre el cliente.'],
    ['delta', 'Se agregaron 3 tests nuevos.'],
    ['example', 'Por ejemplo, 45 services.'],
    ['example', 'Se escribe `45 services` en la tabla.'],
    ['example', 'Ejemplo: 45 services.'],
    ['current', 'Esquema completo (27 migraciones) y el módulo de ejemplo de clientes.'],
] as [$expected, $line]) {
    $actual = typeOf($line)['type'];
    check("$expected: $line", $actual === $expected, "obtuvo $actual");
}

check('conserva el nombre de la clase', (typeOf('`Login` (9 tests)')['class'] ?? null) === 'Login');

// Proyecto de ejemplo (monorepo: el backend en backend/) con 47 services, 58 controllers, 3 rutas y 9 tests en LoginTest.
function fixture(array $live): string
{
    $root = sys_get_temp_dir() . '/check-docs-' . uniqid();

    foreach (['backend/app/Services', 'backend/app/Http/Controllers/Api', 'backend/tests/Feature/Auth', 'backend/tests/Unit', 'frontend', 'docs/00-vision', 'docs/01-arquitectura/decisiones', 'docs/02-requisitos', 'docs/03-plan-desarrollo', 'docs/04-desarrollo', 'docs/05-pruebas'] as $dir) {
        mkdir("$root/$dir", 0777, true);
    }

    for ($i = 0; $i < 47; $i++) {
        touch("$root/backend/app/Services/S$i.php");
    }

    for ($i = 0; $i < 58; $i++) {
        touch("$root/backend/app/Http/Controllers/Api/C$i.php");
    }

    file_put_contents("$root/backend/tests/Feature/Auth/LoginTest.php", str_repeat("function test_x() {}\n", 9));
    file_put_contents("$root/backend/artisan", '<?php echo json_encode([1, 2, 3]);');

    $fixed = ['docs/03-plan-desarrollo/handoff.md', 'docs/03-plan-desarrollo/README.md', 'docs/01-arquitectura/decisiones/README.md'];
    $files = ['README.md' => '', 'CLAUDE.md' => ''] + array_fill_keys($fixed, '');
    $index = "[a](03-plan-desarrollo/handoff.md) [b](03-plan-desarrollo/README.md) [c](01-arquitectura/decisiones/README.md)\n";

    foreach ($live + $files as $file => $text) {
        file_put_contents("$root/$file", $text);

        if (str_starts_with($file, 'docs/') && ! in_array($file, $fixed, true)) {
            $index .= '[x](' . substr($file, 5) . ")\n";
        }
    }

    file_put_contents("$root/docs/README.md", $index);

    return $root;
}

function run(string $root, string $flags = ''): array
{
    $previous = getcwd();
    chdir($root);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/check-docs.php') . " $flags", $output, $code);
    chdir($previous);

    return [implode("\n", $output), $code];
}

function remove(string $path): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

echo "\nCaso 1: detecta 45 frente a 47\n";
$root = fixture(['README.md' => "# Título\n| `app/Services` | Lógica de negocio (45 services) |\n", 'CLAUDE.md' => "Reglas (47 services)\n"]);
[$output, $code] = run($root);
check('reporta README.md:2', str_contains($output, 'README.md:2: dice 45 services; el código tiene 47'), $output);
check('no reporta la cifra correcta de CLAUDE.md', ! str_contains($output, 'CLAUDE.md'), $output);
check('sale con 1', $code === 1);
remove($root);

echo "\nCaso 2: cifras por clase\n";
$root = fixture(['docs/03-plan-desarrollo/modulos.md' => "`Login` (9 tests) y `Login` 9\n"]);
[$output, $code] = run($root);
check('sin falso positivo con la clase correcta', str_contains($output, 'Sin hallazgos.') && $code === 0, $output);
remove($root);

$root = fixture(['docs/03-plan-desarrollo/modulos.md' => "`Login` (8 tests)\n", 'docs/04-desarrollo/b.md' => "`Fantasma` (2 tests)\n"]);
[$output] = run($root);
check('detecta una cifra de clase incorrecta', str_contains($output, 'LoginTest tiene 9 tests, no 8'), $output);
check('detecta una clase inexistente', str_contains($output, 'cita FantasmaTest, que no existe'), $output);
remove($root);

echo "\nCaso 3: historia, diferencias y ejemplos\n";
$root = fixture(['docs/04-desarrollo/a.md' => implode("\n", [
    'Antes: 460 tests con M9.',
    'La línea base eran 374 tests.',
    'El frontend antes tenía 40 tests.',
    'Se agregaron 3 tests nuevos.',
    'Por ejemplo, 45 services.',
    '```',
    '999 tests',
    '```',
]) . "\n"]);
[$output, $code] = run($root, '--detalle');
check('no confunde historia con estado actual', str_contains($output, 'Sin hallazgos.') && $code === 0, $output);
check('informa cuántas excluyó', str_contains($output, 'Excluidas por una marca explícita: 6.'), $output);
check('--detalle las lista', str_contains($output, 'excluida: historical: docs/04-desarrollo/a.md:1: 460 tests'), $output);
remove($root);

$root = fixture(['docs/04-desarrollo/a.md' => "Hay 460 tests.\nAntes: 460 tests. Hoy hay 450 tests.\n"]);
[$output] = run($root);
check('sin marca, la misma cifra sí se reporta', str_contains($output, 'a.md:1: dice 460 tests'), $output);
check('la marca solo cubre su cláusula', str_contains($output, 'a.md:2: dice 450 tests') && ! str_contains($output, 'a.md:2: dice 460 tests'), $output);
remove($root);

$root = fixture(['docs/04-desarrollo/a.md' => "| Frontend y backend | 45 services |\n"]);
[$output] = run($root);
check('una marca en otra celda no excluye la cifra', str_contains($output, 'a.md:1: dice 45 services'), $output);
remove($root);

echo "\nCaso 4: migraciones\n";
$root = fixture(['README.md' => "| `database/migrations` | 3 migraciones |\n", 'CLAUDE.md' => "`Seeder` (2 migraciones) y `Login` (9 tests)\n"]);
mkdir("$root/backend/database/migrations", 0777, true);
touch("$root/backend/database/migrations/a.php");
touch("$root/backend/database/migrations/b.php");
[$output, $code] = run($root, '--detalle');
check('detecta 3 frente a 2', str_contains($output, 'README.md:1: dice 3 migraciones; el código tiene 2'), $output);
check('compara una cifra que no es de tests junto a una clase', str_contains($output, 'Cifras comparadas: 3.') && ! str_contains($output, 'excluida'), $output);
remove($root);

echo "\nCaso 5: rutas citadas\n";
$root = fixture(['docs/04-desarrollo/a.md' => "`database/testing.sqlite_test_N` y `database/fantasma.sqlite`\n"]);
[$output] = run($root);
check('no reporta la base que crea la suite en paralelo', ! str_contains($output, 'testing.sqlite_test_N'), $output);
check('sigue reportando una ruta inexistente', str_contains($output, 'database/fantasma.sqlite'), $output);
remove($root);

echo "\nCaso 6: monorepo (rutas de código desde backend/, docs desde la raíz)\n";
$root = fixture([
    'docs/04-desarrollo/a.md' => "`app/Services/S1.php`, `backend/app/Services/S2.php`, `docs/03-plan-desarrollo/handoff.md` y `app/Services/Fantasma.php`\n",
    'backend/CLAUDE.md' => "[h](../docs/03-plan-desarrollo/handoff.md) [r](../CLAUDE.md) [x](../docs/fantasma.md) `docs/04-desarrollo/a.md` `tests/Feature/Auth/LoginTest.php`\n",
]);
[$output] = run($root);
check('resuelve app/ desde backend/', ! str_contains($output, 'app/Services/S1.php'), $output);
check('resuelve backend/ y docs/ desde la raíz', ! str_contains($output, 'S2.php') && ! str_contains($output, ': docs/03-plan-desarrollo/handoff.md') && ! str_contains($output, ': docs/04-desarrollo/a.md'), $output);
check('reporta una ruta de código inexistente', str_contains($output, 'app/Services/Fantasma.php'), $output);
check('valida los enlaces de backend/CLAUDE.md hacia docs/', str_contains($output, 'backend/CLAUDE.md: ../docs/fantasma.md') && ! str_contains($output, '../docs/03-plan-desarrollo/handoff.md') && ! str_contains($output, ': ../CLAUDE.md'), $output);
check('resuelve tests/ de backend/CLAUDE.md desde backend/', ! str_contains($output, 'LoginTest.php'), $output);
remove($root);

echo "\nCaso 7: cifras del frontend, separadas de las del backend\n";
$root = fixture([
    'docs/04-desarrollo/a.md' => implode("\n", [
        'Frontend: 5 tests.',
        'El frontend tiene 4 tests.',
        '| Backend | 9 tests |',
        'Además, `a.test.ts` (3 tests) y `lib/a.test.ts` (3 tests).',
        'Y `b.test.tsx` (1 tests).',
        'Y `fantasma.test.ts` (2 tests).',
        'El frontend tiene 5 rutas.',
    ]) . "\n",
    'frontend/CLAUDE.md' => "Hay 5 tests.\nEl backend tiene 9 tests.\nHay 6 tests.\n",
]);
mkdir("$root/frontend/src/lib", 0777, true);
// 3 tests (it, test e it.skip); /x/.test( es el método de una expresión regular y no cuenta.
file_put_contents("$root/frontend/src/lib/a.test.ts", "it('a', () => { expect(/x/.test('x')).toBe(true); });\ntest('b', () => {});\nit.skip('c', () => {});\n");
file_put_contents("$root/frontend/src/b.test.tsx", "it('d', () => {});\nit('e', () => {});\n");
[$output, $code] = run($root, '--detalle');
check('cuenta los tests del frontend aparte', str_contains($output, 'Frontend: 5 tests en 2 archivos.'), $output);
check('acepta la cifra correcta del frontend', ! str_contains($output, 'a.md:1:') && ! str_contains($output, 'frontend/CLAUDE.md:1:'), $output);
check('reporta una cifra del frontend incorrecta', str_contains($output, 'a.md:2: dice 4 tests del frontend; el frontend tiene 5'), $output);
check('no compara la cifra del backend con el frontend', ! str_contains($output, 'a.md:3:') && ! str_contains($output, 'frontend/CLAUDE.md:2:'), $output);
check('en frontend/, una cifra sin marca es del frontend', str_contains($output, 'frontend/CLAUDE.md:3: dice 6 tests del frontend; el frontend tiene 5'), $output);
check('valida la cifra de un archivo de test', ! str_contains($output, 'a.md:4:') && str_contains($output, 'a.md:5: b.test.tsx tiene 2 tests, no 1'), $output);
check('reporta un archivo de test inexistente', str_contains($output, 'a.md:6: cita fantasma.test.ts, que no existe'), $output);
check('no compara las rutas del frontend con las de la API', str_contains($output, 'excluida: frontend sin cifra comparable: docs/04-desarrollo/a.md:7: 5 rutas'), $output);
check('sale con 1', $code === 1);
remove($root);

printf("\n%d correctas, %d fallidas.\n", $passed, $failures);
exit($failures === 0 ? 0 : 1);
