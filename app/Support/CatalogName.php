<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Nombre de una especie o una raza para compararlo (FASE 1, entrega 1.4;
 * preguntas 9 y 10 del diseño): sin espacios al inicio ni al final, en
 * minúsculas y sin acentos. "Ángora", "angora" y " ANGORA " son el mismo nombre.
 * Se compara en la aplicación, no con la collation de la base, para que SQLite
 * y MySQL den el mismo resultado.
 */
final class CatalogName
{
    public static function normalize(string $name): string
    {
        return Str::lower(Str::ascii(trim($name)));
    }

    /**
     * @param iterable<string> $names
     */
    public static function isTaken(string $name, iterable $names): bool
    {
        $normalized = self::normalize($name);

        foreach ($names as $existing) {
            if (self::normalize($existing) === $normalized) {
                return true;
            }
        }

        return false;
    }
}
