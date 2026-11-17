/** La configuración de la clínica (SystemSettingsResource del backend, 2.5), con los nombres de la API. */

/** Las claves editables de `system_settings` (`SystemSettingKey::editable()`), en el orden de la respuesta. */
export const SETTING_KEYS = [
  'contact_email',
  'contact_phone',
  'website',
  'document_footer',
] as const;

export type SettingKey = (typeof SETTING_KEYS)[number];

export interface SystemSettings {
  clinic: {
    name: string;
    legal_name: string;
    /** Solo para mostrar: no se edita desde la configuración (422 `prohibited`). */
    ruc: string;
    ruc_dv: string;
  };
  /** Cada clave editable; `null` si no tiene valor. */
  settings: Record<SettingKey, string | null>;
  has_logo: boolean;
}

/** El cuerpo de PATCH system-settings: el formulario completo, nunca `ruc` ni `ruc_dv`; `null` borra la clave. */
export interface SystemSettingsPayload {
  name: string;
  legal_name: string;
  settings: Record<SettingKey, string | null>;
}
