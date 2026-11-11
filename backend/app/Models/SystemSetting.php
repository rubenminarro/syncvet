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

    /**
     * El valor que guarda la auditoría en lugar de la ruta del logo.
     */
    public const AUDIT_FILE = '(archivo)';

    protected function casts(): array
    {
        return [
            'key' => SystemSettingKey::class,
        ];
    }

    /**
     * La clave, siempre (corrección de la 2.5): sin ella, un cambio de valor quedaría en la
     * auditoría como {"value": …}, sin saber de qué clave es. Se lee sin el cast, para
     * no fallar con una clave que no está en el enum.
     */
    public function auditContext(): array
    {
        return ['key' => $this->getAttributes()['key'] ?? null];
    }

    /**
     * La ruta del logo (logo_path) nunca queda en la auditoría: en su lugar, "(archivo)",
     * en el alta, la modificación y la baja (corrección de la 2.5).
     */
    public function auditValues(array $values): array
    {
        // Sin distinguir mayúsculas: una fila LOGO_PATH que put() normaliza (MySQL).
        $isLogo = strcasecmp((string) ($values['key'] ?? ''), SystemSettingKey::LOGO_PATH->value) === 0;

        if ($isLogo && ($values['value'] ?? null) !== null) {
            $values['value'] = self::AUDIT_FILE;
        }

        return $values;
    }
}
