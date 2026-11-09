<?php

namespace App\Services;

use App\Enums\SystemSettingKey;
use App\Models\Clinic;
use App\Models\SystemSetting;
use App\Support\CurrentClinic;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Configuración de la clínica (FASE 2, entrega 2.5; P11 y P13): sus datos (nombre de
 * fantasía y razón social; el RUC se muestra y no se edita, pregunta 24) y las claves
 * de system_settings (SystemSettingKey, una lista cerrada). Una clave sin valor no
 * tiene fila: null la borra.
 *
 * Concurrencia. Orden de bloqueo: clínica → configuración. La edición bloquea la fila
 * de la clínica (CatalogLock) antes de leer las claves, como el logo (ClinicLogoService):
 * dos ediciones simultáneas se ordenan y el índice (clinic_id, key) no llega a chocar.
 */
class SystemSettingsService
{
    /**
     * La configuración de la clínica en contexto: sus datos, cada clave editable (null
     * si no tiene valor) y si tiene logo. logo_path nunca sale.
     *
     * @return array{clinic: Clinic, settings: array<string, ?string>, has_logo: bool}
     */
    public function snapshot(?Clinic $clinic = null): array
    {
        $clinic ??= Clinic::query()->findOrFail(CurrentClinic::id());
        $values = SystemSetting::query()->get()->mapWithKeys(fn (SystemSetting $setting) => [$setting->key->value => $setting->value]);

        $settings = [];
        foreach (SystemSettingKey::editable() as $key) {
            $settings[$key->value] = $values[$key->value] ?? null;
        }

        return [
            'clinic' => $clinic,
            'settings' => $settings,
            'has_logo' => ($values[SystemSettingKey::LOGO_PATH->value] ?? null) !== null,
        ];
    }

    /**
     * Semántica parcial: solo cambia lo que llega. name y legal_name en la clínica;
     * settings.<clave> en su fila (null la borra).
     *
     * @param array<string, mixed> $data los campos validados de UpdateSystemSettingsRequest
     * @return array{clinic: Clinic, settings: array<string, ?string>, has_logo: bool}
     */
    public function update(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $clinic = CatalogLock::forCurrentClinic();
            $clinic->fill(Arr::only($data, ['name', 'legal_name']));
            $clinic->save();

            foreach ($data['settings'] ?? [] as $key => $value) {
                $this->put(SystemSettingKey::from($key), $value);
            }

            return $this->snapshot($clinic);
        });
    }

    /**
     * El valor de una clave, con la clínica ya bloqueada. null borra la fila; el mismo
     * valor no es un cambio (la auditoría no lo registra).
     */
    public function put(SystemSettingKey $key, ?string $value): void
    {
        $setting = SystemSetting::query()->where('key', $key->value)->first();

        if ($value === null) {
            $setting?->delete();

            return;
        }

        $setting ??= new SystemSetting(['key' => $key]);
        $setting->value = $value;
        $setting->save();
    }

    /**
     * El valor de una clave en la clínica en contexto (sin bloqueo).
     */
    public function get(SystemSettingKey $key): ?string
    {
        return SystemSetting::query()->where('key', $key->value)->value('value');
    }
}
