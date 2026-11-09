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
    /**
     * La clínica, bloqueada (la configuración la usa para editar sus datos; FASE 2,
     * entrega 2.5).
     */
    public static function forCurrentClinic(): Clinic
    {
        return Clinic::query()->whereKey(CurrentClinic::id())->lockForUpdate()->firstOrFail();
    }
}
