<?php

namespace App\Services;

use App\Models\Clinic;
use App\Support\CurrentClinic;

/**
 * Bloqueo de los nombres del catálogo (especies y razas) de la clínica en
 * contexto: la fila de la clínica, con lockForUpdate, dentro de la transacción
 * del alta o del cambio de nombre. Dos altas simultáneas del mismo nombre se
 * ordenan y la segunda ve la primera. Orden de bloqueo: clínica → catálogo
 * (CLAUDE.md §3).
 */
final class CatalogLock
{
    public static function forCurrentClinic(): void
    {
        Clinic::query()->whereKey(CurrentClinic::id())->lockForUpdate()->firstOrFail();
    }
}
