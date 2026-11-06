<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Sufijo que libera los campos únicos de un dato maestro dado de baja
 * (CLAUDE.md §3): "//deleted_<id>_<timestamp>". El id lo hace único aunque el
 * mismo valor se dé de baja dos veces en el mismo segundo; el timestamp dice
 * cuándo fue.
 */
class DeletedSuffix
{
    public const PREFIX = '//deleted_';

    public static function for(Model $model): string
    {
        return self::PREFIX . $model->getKey() . '_' . now()->timestamp;
    }
}
