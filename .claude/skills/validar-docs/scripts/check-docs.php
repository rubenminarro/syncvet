<?php

/**
 * Auditoría de la documentación, de solo lectura. Correr desde la raíz del repositorio (monorepo: backend/, frontend/ y docs/):
 *   php .claude/skills/validar-docs/scripts/check-docs.php [--detalle]
 * Sale con 1 si encuentra algo. --detalle lista las cifras excluidas de la comparación.
 * Pruebas de regresión: php .claude/skills/validar-docs/scripts/check-docs-test.php
 */

require __DIR__ . '/figures.php';

// El backend (Laravel) vive en esta carpeta: las rutas de código citadas sin prefijo (`app/…`, `tests/…`) se leen desde acá.
const BACKEND = 'backend';

// El frontend (React + Vitest). Sus cifras ("61 tests del frontend") se comparan con sus tests, nunca con los del backend.
const FRONTEND = 'frontend';

// Un test de Vitest: it( o test( (también .skip, .only, .todo…), sin contar el método .test( de una expresión regular.
// frontend/CLAUDE.md exige un it() por caso, sin .each ni bucles (lo controla ESLint), para que este conteo sea exacto.
const FRONTEND_TEST_CALL = '/(?<![.\w$])(?:it|test)(?:\.(?:skip|only|todo|fails|concurrent))?\(/';

// Raíces de una ruta citada que se leen desde la raíz del repositorio; las demás, desde BACKEND.
const REPOSITORY_ROOTS = ['docs', 'backend', 'frontend'];

const LIVE = ['CLAUDE.md', 'README.md', 'backend/CLAUDE.md', 'backend/README.md', 'frontend/CLAUDE.md', 'frontend/README.md', 'docs/README.md', 'docs/00-vision/overview.md', 'docs/00-vision/principios.md', 'docs/01-arquitectura/architecture.md', 'docs/01-arquitectura/technology-stack.md', 'docs/01-arquitectura/decisiones/README.md', 'docs/02-requisitos', 'docs/03-plan-desarrollo/README.md', 'docs/03-plan-desarrollo/handoff.md', 'docs/03-plan-desarrollo/modulos.md', 'docs/03-plan-desarrollo/fases/frontend', 'docs/04-desarrollo'];

// Estado por módulo: sus cifras de una clase de test (`LoginTest` 9) se comparan con la clase.
const MODULES = 'docs/03-plan-desarrollo/modulos.md';

// Rutas que crea la suite al correr, no el repositorio: las bases de `composer test:fast` (N es el número del proceso, R-045), en BACKEND.
const GENERATED_PATHS = ['database/testing.sqlite_test_N'];

if (! is_file(BACKEND . '/artisan') || ! is_dir('docs')) {
    fwrite(STDERR, "Correr desde la raíz del repositorio.\n");
    exit(2);
}


$findings = [];

function report(string $check, string $where, string $detail): void
{
    global $findings;
    $findings[$check][] = "$where: $detail";
}

function files(string $dir, string $pattern): array
{
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());

        if (preg_match($pattern, $path)) {
            $found[] = $path;
        }
    }

    sort($found);

    return $found;
}

function live(): array
{
    $found = [];

    foreach (LIVE as $path) {
        // Una ruta que no existe (frontend/CLAUDE.md antes de la F0, por ejemplo) no es un documento vivo.
        $found = array_merge($found, is_dir($path) ? files($path, '/\.md$/') : (is_file($path) ? [$path] : []));
    }

    return $found;
}

/**
 * Una ruta citada entre comillas invertidas: `docs/…`, `backend/…` y `frontend/…` desde la raíz; `app/…`, `tests/…` y las
 * demás carpetas de Laravel, desde BACKEND (la convención del CLAUDE.md de la raíz).
 */
function citedPath(string $path): string
{
    return in_array(explode('/', $path)[0], REPOSITORY_ROOTS, true) ? $path : BACKEND . '/' . $path;
}

function normalize(string $path): string
{
    $parts = [];

    foreach (explode('/', $path) as $part) {
        if ($part === '..') {
            array_pop($parts);
        } elseif ($part !== '.' && $part !== '') {
            $parts[] = $part;
        }
    }

    return implode('/', $parts);
}

function slug(string $heading): string
{
    $text = mb_strtolower(trim(str_replace('`', '', $heading)));
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);

    return preg_replace('/\s/u', '-', $text);
}

function anchors(string $file): array
{
    preg_match_all('/^#{1,6}\s+(.+?)\s*$/m', str_replace("\r", '', file_get_contents($file)), $matches);

    return array_map('slug', $matches[1]);
}

function withoutCode(string $text): string
{
    return preg_replace('/```.*?```/s', '', str_replace("\r", '', $text));
}

// Enlaces y anclas
$docs = files('docs', '/\.(md|html)$/');
$sources = array_merge($docs, array_values(array_filter(['CLAUDE.md', 'README.md', 'backend/CLAUDE.md', 'backend/README.md', 'frontend/CLAUDE.md', 'frontend/README.md'], 'is_file')));
$graph = [];
$total = 0;

foreach ($sources as $file) {
    $text = withoutCode(file_get_contents($file));
    $pattern = str_ends_with($file, '.md') ? '/\]\(([^)\s]+)\)/' : '/href="([^"]+)"/';
    preg_match_all($pattern, $text, $matches);
    $graph[$file] = [];

    foreach ($matches[1] as $link) {
        if (preg_match('/^(https?:|mailto:)/', $link)) {
            continue;
        }

        $total++;
        [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);
        $target = $path === '' ? $file : normalize(dirname($file) . '/' . $path);

        if (! file_exists($target)) {
            report('Enlaces rotos', $file, $link);
            continue;
        }

        if ($anchor && str_ends_with($target, '.md') && ! in_array(rawurldecode($anchor), anchors($target), true)) {
            report('Anclas rotas', $file, $link);
        }

        $graph[$file][] = $target;
    }
}

// Documentos huérfanos
$seen = ['docs/README.md' => true];
$queue = ['docs/README.md'];

while ($queue) {
    foreach ($graph[array_shift($queue)] ?? [] as $target) {
        if (str_starts_with($target, 'docs/') && ! isset($seen[$target])) {
            $seen[$target] = true;
            $queue[] = $target;
        }
    }
}

foreach ($docs as $file) {
    if (! isset($seen[$file])) {
        report('Documentos huérfanos', $file, 'no se alcanza desde docs/README.md');
    }
}

// Rutas citadas en documentos vivos
foreach (live() as $file) {
    preg_match_all('/`((?:docs|backend|frontend|app|tests|config|routes|database|lang|bootstrap)\/[\w.\/-]+\.\w+)`/', file_get_contents($file), $matches);

    foreach (array_unique($matches[1]) as $path) {
        if (! file_exists(citedPath($path)) && ! in_array($path, GENERATED_PATHS, true)) {
            report('Rutas inexistentes', $file, $path);
        }
    }
}

// Cifras contra el código
$tests = [];

foreach (array_merge(files(BACKEND . '/tests/Feature', '/Test\.php$/'), files(BACKEND . '/tests/Unit', '/Test\.php$/')) as $file) {
    $tests[basename($file, 'Test.php')] = preg_match_all('/function test_|#\[Test\]/', file_get_contents($file));
}

$routes = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BACKEND . '/artisan') . ' route:list --path=api --json 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')), true);
$actual = [
    'tests' => array_sum($tests),
    'rutas' => is_array($routes) ? count($routes) : null,
    'controllers' => count(glob(BACKEND . '/app/Http/Controllers/Api/*.php')),
    'services' => count(glob(BACKEND . '/app/Services/*.php')),
    'migraciones' => count(glob(BACKEND . '/database/migrations/*.php')),
    'permisos' => permissionCount(),
];

/**
 * Permisos de RolesAndPermissionsSeeder::PERMISSIONS (solo se lee la constante: no arranca Laravel).
 */
function permissionCount(): ?int
{
    if (! is_file(BACKEND . '/vendor/autoload.php')) {
        return null;
    }

    require_once BACKEND . '/vendor/autoload.php';

    $class = 'Database\\Seeders\\RolesAndPermissionsSeeder';

    return class_exists($class) && defined("$class::PERMISSIONS") ? count($class::PERMISSIONS) : null;
}

// Tests del frontend, por archivo (ruta desde frontend/src). Sin frontend/src, sin dato.
$frontendTests = [];

foreach (is_dir(FRONTEND . '/src') ? files(FRONTEND . '/src', '/\.test\.tsx?$/') : [] as $file) {
    $frontendTests[substr($file, strlen(FRONTEND . '/src/'))] = preg_match_all(FRONTEND_TEST_CALL, file_get_contents($file));
}

$frontendTotal = is_dir(FRONTEND . '/src') ? array_sum($frontendTests) : null;

/**
 * El archivo de test del frontend que cita un documento (`client.test.ts` o `lib/api/client.test.ts`): el único que
 * termina así, o null.
 */
function frontendTestFile(array $frontendTests, string $cited): ?string
{
    $matches = array_values(array_filter(array_keys($frontendTests), fn ($path) => $path === $cited || str_ends_with($path, '/' . $cited)));

    return count($matches) === 1 ? $matches[0] : null;
}

$compared = 0;
$excluded = [];

foreach (live() as $file) {
    $inFence = false;

    foreach (preg_split('/\R/', file_get_contents($file)) as $number => $line) {
        if (str_starts_with(ltrim($line), '```')) {
            $inFence = ! $inFence;
            continue;
        }

        preg_match_all('/\b(\d+) (tests|rutas|controllers|services|migraciones|permisos)\b/u', $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            [$text, $offset] = $match[0];
            $count = (int) $match[1][0];
            $kind = $match[2][0];
            $where = $file . ':' . ($number + 1);
            $figure = classifyFigure($line, $offset, strlen($text), $inFence);

            // "`RolesAndPermissionsSeeder` (119 permisos)": solo una cifra de tests se refiere a la clase citada.
            if (in_array($figure['type'], ['class', 'test-file'], true) && $kind !== 'tests') {
                $figure = ['type' => 'current'];
            }

            // En un documento del frontend, una cifra sin marca es del frontend, salvo que su cláusula nombre al backend.
            if ($figure['type'] === 'current' && str_starts_with($file, FRONTEND . '/')
                && ! mentionsBackend(figureClause(substr($line, 0, $offset), substr($line, $offset + strlen($text))))) {
                $figure = ['type' => 'frontend'];
            }

            if ($figure['type'] === 'frontend' && $kind === 'tests') {
                $compared++;

                if ($frontendTotal !== null && $count !== $frontendTotal) {
                    report('Cifras', $where, "dice $count tests del frontend; el frontend tiene $frontendTotal");
                }
            } elseif ($figure['type'] === 'frontend') {
                // El frontend solo tiene cifra de tests: "5 rutas" del frontend no se compara con las rutas de la API.
                $excluded[] = "frontend sin cifra comparable: $where: $text";
            } elseif ($figure['type'] === 'test-file') {
                $compared++;
                $path = frontendTestFile($frontendTests, $figure['file']);

                // Sin frontend/src no hay con qué comparar.
                if ($frontendTotal === null) {
                    continue;
                }

                if ($path === null) {
                    report('Cifras', $where, "cita {$figure['file']}, que no existe en frontend/src (o hay más de uno)");
                } elseif ($frontendTests[$path] !== $count) {
                    report('Cifras', $where, "$path tiene {$frontendTests[$path]} tests, no $count");
                }
            } elseif ($figure['type'] === 'current') {
                $compared++;

                if ($actual[$kind] !== null && $count !== $actual[$kind]) {
                    report('Cifras', $where, "dice $count $kind; el código tiene {$actual[$kind]}");
                }
            } elseif ($figure['type'] === 'class' && $kind === 'tests') {
                $compared++;

                if (! isset($tests[$figure['class']])) {
                    report('Cifras', $where, "cita {$figure['class']}Test, que no existe");
                } elseif ($tests[$figure['class']] !== $count) {
                    report('Cifras', $where, "{$figure['class']}Test tiene {$tests[$figure['class']]} tests, no $count");
                }
            } else {
                $excluded[] = "{$figure['type']}: $where: $text";
            }
        }
    }
}

foreach (is_file(MODULES) ? [MODULES] : [] as $file) {
    preg_match_all('/`([A-Z]\w+)` (\d+)\b/', file_get_contents($file), $matches, PREG_SET_ORDER);

    foreach ($matches as [, $class, $count]) {
        if (! isset($tests[$class])) {
            report('Cifras', $file, "cita {$class}Test, que no existe");
        } elseif ($tests[$class] !== (int) $count) {
            report('Cifras', $file, "{$class}Test tiene {$tests[$class]} tests, no $count");
        }
    }
}

// Resultado
printf("Documentos: %d. Enlaces internos: %d. Alcanzables desde el índice: %d.\n", count($docs), $total, count($seen));
printf(
    "Backend: %d tests, %s rutas, %d controllers, %d services, %d migraciones, %s permisos.\n",
    $actual['tests'], $actual['rutas'] ?? 'sin dato de', $actual['controllers'], $actual['services'],
    $actual['migraciones'], $actual['permisos'] ?? 'sin dato de',
);
printf(
    "Frontend: %s tests en %d archivos.\n",
    $frontendTotal ?? 'sin dato de', count($frontendTests),
);
printf("Cifras comparadas: %d. Excluidas por una marca explícita: %d.\n\n", $compared, count($excluded));

if (in_array('--detalle', $argv, true)) {
    foreach ($excluded as $item) {
        echo "  excluida: $item\n";
    }

    echo "\n";
}

if ($actual['rutas'] === null) {
    echo "Aviso: no se pudieron contar las rutas.\n\n";
}

if (! $findings) {
    echo "Sin hallazgos.\n";
    exit(0);
}

foreach ($findings as $check => $items) {
    echo "$check (" . count($items) . ")\n";

    foreach ($items as $item) {
        echo "  - $item\n";
    }
}

exit(1);
