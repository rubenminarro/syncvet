<?php

namespace App\Http\Resources;

use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * El detalle de un registro de auditoría (FASE 2, entrega 2.6): suma old_values,
 * new_values y user_agent. Las contraseñas y los tokens ya llegan enmascarados
 * (AuditLogger). La ruta de la foto del paciente se guarda como "(archivo)" desde la
 * 2.6 (Patient::auditValues); los registros anteriores que ya la tienen la muestran
 * igual, como "(archivo)". La del logo no se guarda desde R-041; los registros
 * anteriores que la tienen (con la forma clinics/{id}/logo/…, en cualquier valor) la
 * muestran como "(archivo)" (revisión de la 2.6).
 */
class ShowAuditLogResource extends AuditLogResource
{
    /**
     * Campos con una ruta interna, que nunca salen.
     */
    public const HIDDEN_PATHS = ['photo_path'];

    /**
     * La ruta de un logo de la clínica en cualquier valor: la guardaron los registros de
     * system_setting anteriores a R-041, a veces sin la clave ({"value": …}; revisión de
     * la 2.6).
     */
    public const LOGO_PATH = '#^clinics/\d+/logo/#';

    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'old_values' => $this->withoutPaths($this->old_values),
            'new_values' => $this->withoutPaths($this->new_values),
            'user_agent' => $this->user_agent,
        ];
    }

    /**
     * @param array<string, mixed>|null $values
     * @return array<string, mixed>|null
     */
    private function withoutPaths(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::HIDDEN_PATHS as $key) {
            if (($values[$key] ?? null) !== null) {
                $values[$key] = SystemSetting::AUDIT_FILE;
            }
        }

        return $this->withoutLogoPaths($values);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private function withoutLogoPaths(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->withoutLogoPaths($value);
            } elseif (is_string($value) && preg_match(self::LOGO_PATH, $value) === 1) {
                $values[$key] = SystemSetting::AUDIT_FILE;
            }
        }

        return $values;
    }
}
