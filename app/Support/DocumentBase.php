<?php

namespace App\Support;

/**
 * Número base del documento de un cliente (FASE 1, entrega 1.2; R-018). En
 * Paraguay el RUC de una persona es su cédula más el dígito verificador:
 * "99999903", "099999903" y "99999903-6" son el mismo documento. La base son los
 * dígitos antes del guion, sin ceros adelante, y es única por clínica.
 *
 * Un documento con letras (pasaporte, extranjero) es su propia base, en
 * mayúsculas: así SQLite y MySQL comparan igual. El sufijo de baja se conserva
 * entero, para que la base de un cliente dado de baja quede libre.
 */
class DocumentBase
{
    public static function of(string $document): string
    {
        $suffix = '';
        $position = strpos($document, DeletedSuffix::PREFIX);

        if ($position !== false) {
            $suffix = substr($document, $position);
            $document = substr($document, 0, $position);
        }

        if (preg_match('/^(\d+)(?:-\d)?$/', $document, $parts)) {
            $base = ltrim($parts[1], '0');

            return ($base === '' ? '0' : $base) . $suffix;
        }

        return strtoupper($document) . $suffix;
    }
}
