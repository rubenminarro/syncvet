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

// El frontend (React + Vitest). Sus cifras ("93 tests del frontend") se comparan con la última corrida registrada, nunca
// con los tests del backend.
const FRONTEND = 'frontend';

// La cifra de tests del frontend no se cuenta en el código (los .each y los bucles generan casos): sale de la última
// corrida de `npm test` registrada en "Resultado" de una fase del frontend (desde R-091, en su `fN-resultados.md`), con
// esta línea de formato fijo:
// Corrida de `npm test` del frontend (2026-11-12): 93 tests en 9 archivos, en verde.
const FRONTEND_PHASES = 'docs/03-plan-desarrollo/fases/frontend';
const FRONTEND_RUN = '/^Corrida de `npm test` del frontend \((\d{4}-\d{2}-\d{2})\): (\d+) tests en (\d+) archivos\b/u';

// Documentos del frontend fuera de frontend/: una cifra sin marca es del frontend, como en frontend/ (R-091).
const FRONTEND_DOCS = ['docs/04-desarrollo/frontend-convenciones.md'];

// Las fases: el diseño de cada una (`f3-agenda.md`, `fase-03-agenda-y-turnos.md`) y, aparte, sus resultados
// (`f3-resultados.md`, `fase-03-resultados.md`; R-091). Con el archivo de resultados, el diseño no lleva "Resultado".
const PHASES = 'docs/03-plan-desarrollo/fases';
const RESULTS_SUFFIX = '-resultados.md';

// El registro de decisiones (R-091): el índice, una línea por R, y las decisiones en archivos de 50 (`registro/R-001-050.md`).
const REGISTER_INDEX = 'docs/01-arquitectura/decisiones/registro.md';
const REGISTER_DIR = 'docs/01-arquitectura/decisiones/registro';
const REGISTER_RANGE = 50;

// Raíces de una ruta citada que se leen desde la raíz del repositorio; las demás, desde BACKEND.
const REPOSITORY_ROOTS = ['docs', 'backend', 'frontend'];

const LIVE = ['CLAUDE.md', 'README.md', 'backend/CLAUDE.md', 'backend/README.md', 'frontend/CLAUDE.md', 'frontend/README.md', 'docs/README.md', 'docs/00-vision/overview.md', 'docs/00-vision/principios.md', 'docs/01-arquitectura/architecture.md', 'docs/01-arquitectura/contrato-api.md', 'docs/01-arquitectura/technology-stack.md', 'docs/01-arquitectura/decisiones/README.md', 'docs/02-requisitos', 'docs/03-plan-desarrollo/README.md', 'docs/03-plan-desarrollo/handoff.md', 'docs/03-plan-desarrollo/modulos.md', 'docs/03-plan-desarrollo/fases/frontend', 'docs/04-desarrollo'];

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

/**
 * Las líneas de un archivo fuera de los bloques de código, con su índice (desde 0) de siempre.
 *
 * @return array<int, string>
 */
function linesOutsideCode(string $file): array
{
    $found = [];
    $inFence = false;

    foreach (preg_split('/\R/', file_get_contents($file)) as $index => $line) {
        if (str_starts_with(ltrim($line), '```')) {
            $inFence = ! $inFence;
        } elseif (! $inFence) {
            $found[$index] = $line;
        }
    }

    return $found;
}

// Enlaces y anclas
$docs =files('docs', '/\.(md|html)$/');
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

/**
 * Las corridas de `npm test` del frontend registradas en "Resultado" de las fases del frontend (FRONTEND_RUN), con su
 * fecha, tests, archivos y dónde están. Una línea con ese formato fuera de "Resultado" no cuenta.
 *
 * @return list<array{date: string, tests: int, files: int, where: string}>
 */
function frontendRuns(): array
{
    $runs = [];

    foreach (is_dir(FRONTEND_PHASES) ? files(FRONTEND_PHASES, '/\.md$/') : [] as $file) {
        $inResult = false;

        foreach (preg_split('/\R/', file_get_contents($file)) as $number => $line) {
            if (preg_match('/^(#+)\s+(.*)$/u', $line, $heading)) {
                // "Resultado" abre la sección; un título del mismo nivel o superior, la cierra.
                if ($inResult !== false && strlen($heading[1]) <= $inResult) {
                    $inResult = false;
                }

                if (preg_match('/^Resultado\b/u', trim($heading[2]))) {
                    $inResult = strlen($heading[1]);
                }

                continue;
            }

            if ($inResult !== false && preg_match(FRONTEND_RUN, $line, $match)) {
                $runs[] = ['date' => $match[1], 'tests' => (int) $match[2], 'files' => (int) $match[3], 'where' => $file . ':' . ($number + 1)];
            }
        }
    }

    return $runs;
}

/**
 * Las líneas (índice desde 0) de las secciones de "Resultado" de entregas ya cerradas: dentro de "Resultado", cada bloque
 * con su título (`### Entrega 0.1`, o el texto del propio "Resultado" antes del primer subtítulo) que dice COMPLETADO o
 * COMPLETADA. Sus cifras son historia y no se comparan; la corrida registrada se sigue leyendo con frontendRuns().
 *
 * @param  list<string>  $lines
 * @return array<int, true>
 */
function closedResultLines(array $lines): array
{
    $closed = [];
    $resultLevel = null;
    $block = [];
    $blockClosed = false;
    $inFence = false;

    $flush = function () use (&$closed, &$block, &$blockClosed): void {
        if ($blockClosed) {
            foreach ($block as $index) {
                $closed[$index] = true;
            }
        }

        $block = [];
        $blockClosed = false;
    };

    foreach ($lines as $index => $line) {
        if (str_starts_with(ltrim($line), '```')) {
            $inFence = ! $inFence;
        }

        if (! $inFence && preg_match('/^(#+)\s+(.*)$/u', $line, $heading)) {
            $level = strlen($heading[1]);

            if ($resultLevel !== null) {
                // Un título nuevo cierra el bloque anterior; uno del mismo nivel que "Resultado" o superior, la sección.
                $flush();

                if ($level <= $resultLevel) {
                    $resultLevel = null;
                }
            }

            if ($resultLevel === null && preg_match('/^Resultado\b/u', trim($heading[2]))) {
                $resultLevel = $level;
            }

            if ($resultLevel !== null) {
                $block[] = $index;
            }

            continue;
        }

        if ($resultLevel !== null) {
            $block[] = $index;
            $blockClosed = $blockClosed || preg_match('/\bCOMPLETAD[OA]\b/u', $line) === 1;
        }
    }

    $flush();

    return $closed;
}

// La última corrida registrada (la de fecha más reciente; con la misma fecha, la última encontrada). Sin frontend/src,
// sin dato.
$frontendRuns = frontendRuns();
usort($frontendRuns, fn ($a, $b) => strcmp($a['date'], $b['date']));
$frontendRun = is_dir(FRONTEND . '/src') && $frontendRuns !== [] ? end($frontendRuns) : null;
$frontendTotal = $frontendRun['tests'] ?? null;
$frontendTestFiles = is_dir(FRONTEND . '/src') ? count(files(FRONTEND . '/src', '/\.test\.tsx?$/')) : 0;

if (is_dir(FRONTEND . '/src') && $frontendTestFiles > 0 && $frontendRun === null) {
    report('Cifras', FRONTEND_PHASES, 'no hay ninguna corrida de `npm test` del frontend registrada en "Resultado"');
} elseif ($frontendRun !== null && $frontendRun['files'] !== $frontendTestFiles) {
    // Un archivo de test agregado o borrado después de la última corrida registrada: la cifra quedó vieja.
    report('Cifras', $frontendRun['where'], "la última corrida registrada tiene {$frontendRun['files']} archivos de test; frontend/src tiene $frontendTestFiles");
}

$compared = 0;
$excluded = [];

foreach (live() as $file) {
    $inFence = false;
    $lines = preg_split('/\R/', file_get_contents($file));
    $closedResult = closedResultLines($lines);

    foreach ($lines as $number => $line) {
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
            if ($figure['type'] === 'current' && (str_starts_with($file, FRONTEND . '/') || in_array($file, FRONTEND_DOCS, true))
                && ! mentionsBackend(figureClause(substr($line, 0, $offset), substr($line, $offset + strlen($text))))) {
                $figure = ['type' => 'frontend'];
            }

            if (preg_match(FRONTEND_RUN, $line)) {
                // La línea de una corrida registrada es el dato, no una cifra a comparar (las de fases anteriores son
                // historia).
                $excluded[] = "corrida registrada: $where: $text";
            } elseif (isset($closedResult[$number])) {
                // El resultado de una entrega ya cerrada es historia: sus cifras eran las de ese momento.
                $excluded[] = "resultado de una entrega cerrada: $where: $text";
            } elseif ($figure['type'] === 'frontend' && $kind === 'tests') {
                $compared++;

                if ($frontendTotal !== null && $count !== $frontendTotal) {
                    report('Cifras', $where, "dice $count tests del frontend; la última corrida registrada tiene $frontendTotal");
                }
            } elseif ($figure['type'] === 'frontend') {
                // El frontend solo tiene cifra de tests: "5 rutas" del frontend no se compara con las rutas de la API.
                $excluded[] = "frontend sin cifra comparable: $where: $text";
            } elseif ($figure['type'] === 'test-file') {
                // Los tests de un archivo del frontend no se pueden contar en el código (.each, bucles): no se compara.
                $excluded[] = "cifra de un archivo de test del frontend: $where: $text";
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

// Registro de decisiones partido (R-091): cada R una sola vez, en el archivo de su rango y en orden, sin huecos, y una línea
// por R en el índice, en el mismo orden, con su archivo, su ancla, su fecha y su título.
$register = null;

if (is_file(REGISTER_INDEX)) {
    $decisions = [];

    foreach (is_dir(REGISTER_DIR) ? files(REGISTER_DIR, '/\.md$/') : [] as $file) {
        if (! preg_match('/\/R-(\d{3})-(\d{3})\.md$/', $file, $range) || (int) $range[2] - (int) $range[1] !== REGISTER_RANGE - 1 || ((int) $range[1] - 1) % REGISTER_RANGE !== 0) {
            report('Registro', $file, 'el nombre no es un rango de ' . REGISTER_RANGE . ' (R-001-050.md, R-051-100.md…)');
            continue;
        }

        foreach (linesOutsideCode($file) as $number => $line) {
            if (! preg_match('/^# (R-(\d{3})) (.+) \((\d{4}-\d{2}-\d{2})\)$/u', $line, $match)) {
                if (preg_match('/^# R-\d/', $line)) {
                    report('Registro', "$file:" . ($number + 1), 'el título no tiene la forma "# R-NNN Título (AAAA-MM-DD)"');
                }

                continue;
            }

            $n = (int) $match[2];

            if ($n < (int) $range[1] || $n > (int) $range[2]) {
                report('Registro', "$file:" . ($number + 1), "$match[1] no es de este rango");
            }

            $decisions[] = ['id' => $match[1], 'n' => $n, 'title' => $match[3], 'date' => $match[4], 'file' => $file, 'anchor' => slug(substr($line, 2))];
        }
    }

    usort($decisions, fn ($a, $b) => [$a['file'], $a['n']] <=> [$b['file'], $b['n']]);

    foreach ($decisions as $i => $decision) {
        if ($decision['n'] !== $i + 1) {
            report('Registro', $decision['file'], "{$decision['id']} fuera de orden, repetida o después de un hueco (se esperaba R-" . sprintf('%03d', $i + 1) . ')');
            break;
        }
    }

    $rows = [];

    foreach (preg_split('/\R/', file_get_contents(REGISTER_INDEX)) as $number => $line) {
        if (preg_match('/^# R-\d/', $line)) {
            report('Registro', REGISTER_INDEX . ':' . ($number + 1), 'una decisión escrita en el índice: va en el archivo de su rango');
        }

        if (preg_match('/^\| \[(R-\d{3})\]\(([^)#]+)#([^)]+)\) \| (\d{4}-\d{2}-\d{2}) \| (.+) \|$/u', $line, $match)) {
            $rows[] = ['id' => $match[1], 'file' => normalize(dirname(REGISTER_INDEX) . '/' . $match[2]), 'anchor' => rawurldecode($match[3]), 'date' => $match[4], 'title' => str_replace('\|', '|', $match[5]), 'line' => $number + 1];
        }
    }

    foreach ($decisions as $i => $decision) {
        $row = $rows[$i] ?? null;
        $where = REGISTER_INDEX . ($row ? ':' . $row['line'] : '');

        if ($row === null || $row['id'] !== $decision['id']) {
            report('Registro', $where, "falta la línea de {$decision['id']} o no está en su lugar");
            break;
        }

        foreach (['file' => 'archivo', 'anchor' => 'ancla', 'date' => 'fecha', 'title' => 'título'] as $key => $name) {
            if ($row[$key] !== $decision[$key]) {
                report('Registro', $where, "{$decision['id']}: el $name no coincide con el de la decisión ({$decision[$key]})");
            }
        }
    }

    if (count($rows) > count($decisions)) {
        report('Registro', REGISTER_INDEX, 'el índice tiene ' . count($rows) . ' líneas y hay ' . count($decisions) . ' decisiones');
    }

    $register = ['count' => count($decisions), 'rows' => count($rows), 'last' => $decisions === [] ? null : end($decisions)['id']];
}

// Resultados aparte (R-091): con su `…-resultados.md`, el diseño de la fase no tiene una sección "Resultado".
foreach (is_dir(PHASES) ? files(PHASES, '/' . preg_quote(RESULTS_SUFFIX, '/') . '$/') : [] as $results) {
    $prefix = substr(basename($results), 0, -strlen(RESULTS_SUFFIX));

    foreach (glob(dirname($results) . "/$prefix-*.md") as $design) {
        $design = str_replace('\\', '/', $design);

        if (str_ends_with($design, RESULTS_SUFFIX)) {
            continue;
        }

        foreach (linesOutsideCode($design) as $number => $line) {
            if (preg_match('/^#+\s+Resultado\b/u', $line)) {
                report('Resultados fuera de su archivo', "$design:" . ($number + 1), 'el resultado va en ' . basename($results));
            }
        }
    }
}

// Resultado
printf("Documentos: %d. Enlaces internos: %d. Alcanzables desde el índice: %d.\n", count($docs), $total, count($seen));
if ($register !== null) {
    printf("Registro: %d decisiones (R-001 a %s), %d líneas en el índice.\n", $register['count'], $register['last'] ?? 'ninguna', $register['rows']);
}
printf(
    "Backend: %d tests, %s rutas, %d controllers, %d services, %d migraciones, %s permisos.\n",
    $actual['tests'], $actual['rutas'] ?? 'sin dato de', $actual['controllers'], $actual['services'],
    $actual['migraciones'], $actual['permisos'] ?? 'sin dato de',
);
if ($frontendRun === null) {
    printf("Frontend: sin corrida registrada (%d archivos de test).\n", $frontendTestFiles);
} else {
    printf(
        "Frontend: %d tests en %d archivos (corrida del %s, %s).\n",
        $frontendRun['tests'], $frontendRun['files'], $frontendRun['date'], $frontendRun['where'],
    );
}
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
