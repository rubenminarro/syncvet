<?php

/**
 * Clasifica una cifra como "45 services" dentro de una línea. La regla es que toda cifra describe el
 * estado actual y se compara con el código, salvo una marca explícita en su misma cláusula. Ante la
 * duda devuelve 'current': es preferible un falso positivo a ocultar un error.
 *
 * Tipos: current (del backend), frontend (del frontend: lo nombra su cláusula), class (cifra de una
 * clase de test del backend: `LoginTest` (9 tests)), test-file (cifra de un archivo de test del
 * frontend: `client.test.ts` (12 tests); check-docs.php no la compara, porque los tests del frontend no se
 * cuentan en el código), historical, delta, example.
 *
 * En un documento del frontend (frontend/…) una cifra 'current' es del frontend salvo que su cláusula
 * nombre al backend: eso lo decide check-docs.php con mentionsBackend(), porque depende del archivo.
 *
 * @return array{type: string, class?: string, file?: string}
 */
function classifyFigure(string $line, int $offset, int $length, bool $inFence = false): array
{
    $before = substr($line, 0, $offset);
    $after = substr($line, $offset + $length);

    if ($inFence || substr_count($before, '`') % 2 === 1) {
        return ['type' => 'example'];
    }

    if (preg_match('/`([A-Z]\w*)`\s*\(\s*$/', $before, $match)) {
        return ['type' => 'class', 'class' => $match[1]];
    }

    if (preg_match('/`([\w.\/-]+\.test\.tsx?)`\s*\(\s*$/', $before, $match)) {
        return ['type' => 'test-file', 'file' => $match[1]];
    }

    if (preg_match('/^\s*(nuev|adicional|más\b)/iu', $after) || preg_match('/\+\s*$/', $before)) {
        return ['type' => 'delta'];
    }

    $clause = figureClause($before, $after);

    if (preg_match('/\b(antes|anteriores?|hasta el|hasta la|l[ií]nea base|era|eran|hab[ií]a|ten[ií]a)\b/iu', $clause)) {
        return ['type' => 'historical'];
    }

    // "por ejemplo" o "ejemplo:" marcan un ejemplo; "el módulo de ejemplo" es un nombre y no excluye la cifra.
    if (preg_match('/\b(por ejemplo|ejemplo:|p\. ?ej\.?|supongamos)/iu', $clause)) {
        return ['type' => 'example'];
    }

    // Una cifra del frontend se compara con el frontend; "Frontend y backend: 45 services" sigue siendo del backend.
    if (preg_match('/\b(frontend|front|vitest)\b/iu', $clause) && ! mentionsBackend($clause)) {
        return ['type' => 'frontend'];
    }

    return ['type' => 'current'];
}

/**
 * La cláusula nombra al backend.
 */
function mentionsBackend(string $clause): bool
{
    return preg_match('/\b(backend|phpunit|laravel)\b/iu', $clause) === 1;
}

/**
 * La cláusula termina en una celda de tabla, un punto y coma o el fin de una oración.
 */
function figureClause(string $before, string $after): string
{
    $start = preg_split('/\||;|\.\s/u', $before);
    $end = preg_split('/\||;|\.\s/u', $after);

    return end($start) . ' ' . $end[0];
}
