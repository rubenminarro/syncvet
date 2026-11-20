import { z } from 'zod';
import { normalizePhone, PHONE_PATTERN } from '../../lib/phone';
import type { SettingKey, SystemSettings, SystemSettingsPayload } from './types';
import { trimLikeApi } from '../../lib/format/text';

/**
 * El formulario de la configuración de la clínica (2.5): las reglas de forma de UpdateSystemSettingsRequest y de
 * `SystemSettingKey::rules()`, con sus mensajes. Lo que necesita la red (el formato completo del email, el DNS) lo dice la
 * API. El RUC no está en el formulario: no se edita desde acá y nunca se manda.
 */

export const NAME_MAX = 100;
export const LEGAL_NAME_MAX = 150;
export const CONTACT_EMAIL_MAX = 150;
export const WEBSITE_MAX = 150;
export const DOCUMENT_FOOTER_MAX = 500;

/** Los mensajes de UpdateSystemSettingsRequest (backend), tal cual. */
export const SETTINGS_MESSAGES = {
  name: {
    required: 'El nombre de la clínica es obligatorio.',
    min: 'El nombre de la clínica debe tener al menos 2 caracteres.',
    max: 'El nombre de la clínica no debe tener más de 100 caracteres.',
  },
  legal_name: {
    required: 'La razón social es obligatoria.',
    min: 'La razón social debe tener al menos 2 caracteres.',
    max: 'La razón social no debe tener más de 150 caracteres.',
  },
  contact_email: {
    max: 'El correo de contacto no debe tener más de 150 caracteres.',
    email: 'El correo de contacto debe tener el formato correcto.',
  },
  contact_phone: {
    regex:
      'El teléfono de contacto debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).',
  },
  website: {
    max: 'El sitio web no debe tener más de 150 caracteres.',
    url: 'El sitio web debe ser una dirección que empiece con https://.',
  },
  document_footer: {
    max: 'El pie de recetas y comprobantes no debe tener más de 500 caracteres.',
  },
} as const;

/** El texto de la pantalla para el RUC, que no se edita. */
export const RUC_HINT = 'El RUC no se cambia desde acá.';

/** Solo la forma mínima del email (como en clientes y usuarios): el formato completo (RFC) y el DNS los valida la API. */
const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+$/;

/** `url:https` de Laravel no distingue mayúsculas en el protocolo ("HTTPS://" pasa). */
const HTTPS_PREFIX = /^https:\/\//i;

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

/** El correo como se manda: sin los espacios de los extremos y en minúsculas (como `mb_strtolower` de la API). */
export function normalizeEmail(value: string): string {
  return trimLikeApi(value).toLowerCase();
}

/** Un texto opcional: sin los espacios de los extremos, y null si queda vacío (la API borra la clave). */
function optionalText(value: string): string | null {
  const trimmed = trimLikeApi(value);

  return trimmed === '' ? null : trimmed;
}

/** ¿El navegador entiende la dirección? (`new URL()`; la API decide lo demás con su expresión de `url`). */
function parsesAsUrl(value: string): boolean {
  try {
    new URL(value);

    return true;
  } catch {
    return false;
  }
}

/** Los valores del formulario, con la forma del cuerpo: las claves van en `settings`, como las nombra la API. */
export interface SettingsFormValues {
  name: string;
  legal_name: string;
  settings: Record<SettingKey, string>;
}

/** Un campo del formulario, con el nombre de su clave en los errores de la API (`settings.website`). */
export type SettingsField = 'name' | 'legal_name' | `settings.${SettingKey}`;

/** Los campos del formulario, para `applyValidationErrors`: cada clave de `errors` de la API va a su campo. */
export const SETTINGS_FORM_FIELDS = [
  'name',
  'legal_name',
  'settings.contact_email',
  'settings.contact_phone',
  'settings.website',
  'settings.document_footer',
] as const satisfies readonly SettingsField[];

/** La configuración guardada, como valores del formulario (null → vacío). */
export function settingsToForm(data: SystemSettings): SettingsFormValues {
  return {
    name: data.clinic.name,
    legal_name: data.clinic.legal_name,
    settings: {
      contact_email: data.settings.contact_email ?? '',
      contact_phone: data.settings.contact_phone ?? '',
      website: data.settings.website ?? '',
      document_footer: data.settings.document_footer ?? '',
    },
  };
}

function requiredTextError(
  value: string,
  max: number,
  messages: { required: string; min: string; max: string },
): string | null {
  const text = trimLikeApi(value);

  if (text === '') {
    return messages.required;
  }

  if (length(text) < 2) {
    return messages.min;
  }

  return length(text) > max ? messages.max : null;
}

/** Los errores de cada campo; vacío si se puede mandar. En el orden de las reglas de la API. */
export function settingsFormErrors(
  form: SettingsFormValues,
): Partial<Record<SettingsField, string>> {
  const m = SETTINGS_MESSAGES;
  const errors: Partial<Record<SettingsField, string>> = {};
  const values = form.settings;
  const name = requiredTextError(form.name, NAME_MAX, m.name);
  const legalName = requiredTextError(form.legal_name, LEGAL_NAME_MAX, m.legal_name);

  if (name !== null) {
    errors.name = name;
  }

  if (legalName !== null) {
    errors.legal_name = legalName;
  }

  const email = normalizeEmail(values.contact_email);

  if (email !== '') {
    if (length(email) > CONTACT_EMAIL_MAX) {
      errors['settings.contact_email'] = m.contact_email.max;
    } else if (!EMAIL_SHAPE.test(email)) {
      errors['settings.contact_email'] = m.contact_email.email;
    }
  }

  const phone = normalizePhone(trimLikeApi(values.contact_phone));

  if (phone !== '' && !PHONE_PATTERN.test(phone)) {
    errors['settings.contact_phone'] = m.contact_phone.regex;
  }

  const website = trimLikeApi(values.website);

  if (website !== '') {
    if (length(website) > WEBSITE_MAX) {
      errors['settings.website'] = m.website.max;
    } else if (!HTTPS_PREFIX.test(website) || !parsesAsUrl(website)) {
      errors['settings.website'] = m.website.url;
    }
  }

  if (length(trimLikeApi(values.document_footer)) > DOCUMENT_FOOTER_MAX) {
    errors['settings.document_footer'] = m.document_footer.max;
  }

  return errors;
}

/** El esquema de React Hook Form: cada error de `settingsFormErrors` en su campo. */
export const settingsFormSchema = z
  .object({
    name: z.string(),
    legal_name: z.string(),
    settings: z.object({
      contact_email: z.string(),
      contact_phone: z.string(),
      website: z.string(),
      document_footer: z.string(),
    }),
  })
  .superRefine((values, context) => {
    for (const [field, message] of Object.entries(settingsFormErrors(values))) {
      context.addIssue({ code: 'custom', path: field.split('.'), message });
    }
  });

/**
 * El cuerpo de PATCH system-settings: el formulario completo, sin `ruc` ni `ruc_dv` (la API responde 422 `prohibited`). El
 * correo en minúsculas, el teléfono sin espacios ni guiones y las claves vacías en null (la API las borra).
 */
export function settingsFormToPayload(form: SettingsFormValues): SystemSettingsPayload {
  const values = form.settings;
  const email = normalizeEmail(values.contact_email);
  const phone = normalizePhone(trimLikeApi(values.contact_phone));
  const settings: Record<SettingKey, string | null> = {
    contact_email: email === '' ? null : email,
    contact_phone: phone === '' ? null : phone,
    website: optionalText(values.website),
    document_footer: optionalText(values.document_footer),
  };

  return { name: trimLikeApi(form.name), legal_name: trimLikeApi(form.legal_name), settings };
}
