<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configuración de la clínica (FASE 2, entrega 2.5): sus datos (el RUC, solo para
 * mostrar), cada clave editable (null si no tiene valor) y has_logo. La ruta del logo
 * (logo_path) nunca sale de la API.
 *
 * Recibe lo que arma SystemSettingsService::snapshot().
 */
class SystemSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clinic = $this->resource['clinic'];

        return [
            'clinic' => [
                'name' => $clinic->name,
                'legal_name' => $clinic->legal_name,
                'ruc' => $clinic->ruc,
                'ruc_dv' => $clinic->ruc_dv,
            ],
            'settings' => $this->resource['settings'],
            'has_logo' => $this->resource['has_logo'],
        ];
    }
}
