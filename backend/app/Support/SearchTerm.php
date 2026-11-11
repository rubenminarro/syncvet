<?php

namespace App\Support;

/**
 * Texto de búsqueda (`search`) para un LIKE literal: "%" y "_" no son comodines y
 * la barra invertida es un carácter más (revisión de la entrega 1.3, R-021). Sin
 * escapar, `search=%` devolvía todo el listado.
 *
 * El carácter de escape es "!" y no la barra invertida: SQLite no tiene uno por
 * defecto y MySQL usa la barra invertida, así que un ESCAPE explícito y el mismo
 * en las dos bases da el mismo resultado. Con ESCAPE '!', la barra invertida es
 * literal en las dos.
 */
class SearchTerm
{
    public const ESCAPE = '!';

    /**
     * Patrón "contiene" con los comodines escapados.
     */
    public static function contains(string $search): string
    {
        return '%' . str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE . self::ESCAPE, self::ESCAPE . '%', self::ESCAPE . '_'],
            $search
        ) . '%';
    }
}
