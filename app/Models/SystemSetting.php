<?php

namespace App\Models;

use App\Enums\SystemSettingKey;
use App\Models\Concerns\BelongsToClinic;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una clave de la configuración de la clínica (FASE 2, entrega 2.5; P13): la lista
 * cerrada de SystemSettingKey, única por clínica (índice clinic_id + key). Una clave
 * sin valor no tiene fila: null la borra. Se lee y se escribe con la fila de la
 * clínica bloqueada (SystemSettingsService, ClinicLogoService).
 */
class SystemSetting extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'key' => SystemSettingKey::class,
        ];
    }
}
