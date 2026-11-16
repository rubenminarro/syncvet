import { z } from 'zod';
import { hasValidRucCheckDigit, RUC_MESSAGE } from './ruc';
import type { Client, ClientKind, ClientPayload } from './types';

/**
 * El formulario de alta y edición de clientes (1.2). Zod repite las reglas de forma de StoreClientRequest y
 * UpdateClientRequest (obligatorios, largos, expresiones regulares y el dígito verificador del RUC) con los mismos mensajes
 * de ClientMessages; lo que necesita la base (documento o email en uso, el DNS del email) lo dice el 422 de la API.
 */
export interface ClientFormValues {
  kind: ClientKind;
  first_name: string;
  last_name: string;
  business_name: string;
  document_number: string;
  email: string;
  phone: string;
  address: string;
  city: string;
  notifications_enabled: boolean;
  notes: string;
}

/** Los campos del formulario, para `applyValidationErrors` (las claves de `errors` de la API). */
export const CLIENT_FORM_FIELDS = [
  'kind',
  'first_name',
  'last_name',
  'business_name',
  'document_number',
  'email',
  'phone',
  'address',
  'city',
  'notifications_enabled',
  'notes',
] as const satisfies readonly (keyof ClientFormValues)[];

export const EMPTY_CLIENT_FORM: ClientFormValues = {
  kind: 'person',
  first_name: '',
  last_name: '',
  business_name: '',
  document_number: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  // "Recibe recordatorios", marcado por defecto (diseño de la 1.2).
  notifications_enabled: true,
  notes: '',
};

/** Los datos guardados de un cliente, como valores del formulario de edición (null → vacío). */
export function clientToForm(client: Client): ClientFormValues {
  return {
    kind: client.kind.value,
    first_name: client.first_name ?? '',
    last_name: client.last_name ?? '',
    business_name: client.business_name ?? '',
    document_number: client.document_number,
    email: client.email ?? '',
    phone: client.phone ?? '',
    address: client.address ?? '',
    city: client.city ?? '',
    notifications_enabled: client.notifications_enabled,
    notes: client.notes ?? '',
  };
}

// Las expresiones de la API (PHP con /u), con \pL y \pN escritos \p{L} y \p{N}.
/** `App\Support\PersonName::REGEX`: letras separadas por espacios, o por un apóstrofo o un guion entre letras. */
const PERSON_NAME = /^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ]+(?:(?:\s+|['-])[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ]+)*$/u;
const BUSINESS_NAME = /^[\p{L}\p{N}\s.,\-&()']+$/u;
const DOCUMENT = /^[a-zA-Z0-9-]+$/;
const PHONE = /^\+?[1-9]\d{7,14}$/;
const ADDRESS = /^[\p{L}\p{N}\s.,#\-/°º&()']+$/u;
const CITY = /^[\p{L}\s.-]+$/u;
const NOTES = /^[\p{L}\p{N}\s.,;:()\-#@!?]*$/u;

/** Los mensajes de ClientMessages (backend), tal cual. */
export const CLIENT_MESSAGES = {
  first_name: {
    required: 'El nombre es obligatorio.',
    min: 'El nombre debe tener al menos 2 caracteres.',
    max: 'El nombre no debe tener más de 100 caracteres.',
    regex: 'El nombre solo puede contener letras y espacios, y apóstrofos o guiones entre letras.',
  },
  last_name: {
    required: 'El apellido es obligatorio.',
    min: 'El apellido debe tener al menos 2 caracteres.',
    max: 'El apellido no debe tener más de 100 caracteres.',
    regex:
      'El apellido solo puede contener letras y espacios, y apóstrofos o guiones entre letras.',
  },
  business_name: {
    required: 'La razón social es obligatoria para una empresa.',
    min: 'La razón social debe tener al menos 2 caracteres.',
    max: 'La razón social no debe tener más de 150 caracteres.',
    regex: 'La razón social contiene caracteres no permitidos.',
  },
  document_number: {
    required: 'El número de documento es obligatorio.',
    min: 'El número de documento debe tener al menos 5 caracteres.',
    max: 'El número de documento no debe tener más de 20 caracteres.',
    regex: 'El número de documento solo puede contener letras, números y guiones.',
    ruc: RUC_MESSAGE,
  },
  email: {
    required: 'El correo es obligatorio.',
    email: 'El correo debe tener el formato correcto.',
    max: 'El correo no debe tener más de 50 caracteres.',
  },
  phone: {
    required: 'El teléfono es obligatorio.',
    regex:
      'El teléfono debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).',
  },
  address: {
    max: 'La dirección no debe tener más de 255 caracteres.',
    regex: 'La dirección contiene caracteres no permitidos.',
  },
  city: {
    max: 'La ciudad no debe tener más de 100 caracteres.',
    regex: 'La ciudad solo puede contener letras, espacios, puntos y guiones.',
  },
  notes: {
    max: 'Las notas no deben tener más de 1000 caracteres.',
    regex:
      'Las notas solo pueden contener letras, números, espacios y los siguientes caracteres: . , ; : ( ) - # @ ! ?',
  },
} as const;

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

/** El teléfono como se manda: sin los espacios y guiones que se escriban ("0981 123-456" → "0981123456"). */
export function normalizePhone(value: string): string {
  return value.replace(/[\s-]/g, '');
}

/** El email como se manda: sin los espacios de los extremos y en minúsculas (la API lo rechaza con mayúsculas, H3). */
export function normalizeEmail(value: string): string {
  return value.trim().toLowerCase();
}

interface TextRule {
  required?: string;
  min?: [number, string];
  max: [number, string];
  pattern?: [RegExp, string];
}

/** El primer error de un texto (ya sin los espacios de los extremos), en el orden de las reglas de la API. */
function textError(value: string, rule: TextRule): string | null {
  if (value === '') {
    return rule.required ?? null;
  }

  if (rule.min !== undefined && length(value) < rule.min[0]) {
    return rule.min[1];
  }

  if (length(value) > rule.max[0]) {
    return rule.max[1];
  }

  if (rule.pattern !== undefined && !rule.pattern[0].test(value)) {
    return rule.pattern[1];
  }

  return null;
}

/**
 * Solo la forma mínima del email (algo, una arroba y algo, sin espacios): el formato completo (RFC) y el DNS los valida
 * la API. Un control más estricto en el navegador rechazaba emails que la API acepta (josé@…, dominios con ñ; revisión
 * de la 1.2).
 */
const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+$/;

/** Los errores de cada campo; vacío si el formulario se puede mandar. */
export function clientFormErrors(
  values: ClientFormValues,
): Partial<Record<keyof ClientFormValues, string>> {
  const errors: Partial<Record<keyof ClientFormValues, string | null>> = {};
  const m = CLIENT_MESSAGES;

  if (values.kind === 'company') {
    errors.business_name = textError(values.business_name.trim(), {
      required: m.business_name.required,
      min: [2, m.business_name.min],
      max: [150, m.business_name.max],
      pattern: [BUSINESS_NAME, m.business_name.regex],
    });
  } else {
    for (const field of ['first_name', 'last_name'] as const) {
      errors[field] = textError(values[field].trim(), {
        required: m[field].required,
        min: [2, m[field].min],
        max: [100, m[field].max],
        pattern: [PERSON_NAME, m[field].regex],
      });
    }
  }

  const document = values.document_number.trim();
  errors.document_number =
    textError(document, {
      required: m.document_number.required,
      min: [5, m.document_number.min],
      max: [20, m.document_number.max],
      pattern: [DOCUMENT, m.document_number.regex],
    }) ?? (hasValidRucCheckDigit(document) ? null : m.document_number.ruc);

  const email = normalizeEmail(values.email);

  if (email === '') {
    errors.email = m.email.required;
  } else if (!EMAIL_SHAPE.test(email)) {
    errors.email = m.email.email;
  } else if (length(email) > 50) {
    errors.email = m.email.max;
  }

  const phone = normalizePhone(values.phone);
  errors.phone = phone === '' ? m.phone.required : PHONE.test(phone) ? null : m.phone.regex;

  errors.address = textError(values.address.trim(), {
    max: [255, m.address.max],
    pattern: [ADDRESS, m.address.regex],
  });
  errors.city = textError(values.city.trim(), {
    max: [100, m.city.max],
    pattern: [CITY, m.city.regex],
  });
  errors.notes = textError(values.notes.trim(), {
    max: [1000, m.notes.max],
    pattern: [NOTES, m.notes.regex],
  });

  return Object.fromEntries(
    Object.entries(errors).filter((entry): entry is [string, string] => entry[1] !== null),
  );
}

/** El esquema de React Hook Form: cada error de `clientFormErrors` en su campo. */
export const clientFormSchema = z
  .object({
    kind: z.enum(['person', 'company']),
    first_name: z.string(),
    last_name: z.string(),
    business_name: z.string(),
    document_number: z.string(),
    email: z.string(),
    phone: z.string(),
    address: z.string(),
    city: z.string(),
    notifications_enabled: z.boolean(),
    notes: z.string(),
  })
  .superRefine((values, context) => {
    for (const [field, message] of Object.entries(clientFormErrors(values))) {
      context.addIssue({ code: 'custom', path: [field], message });
    }
  });

/** Un texto opcional: sin los espacios de los extremos, y null si queda vacío. */
function optional(value: string): string | null {
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

/**
 * El cuerpo que se manda: los campos del otro tipo en null (la API los rechaza con un valor, `prohibited`), los opcionales
 * vacíos en null, el email en minúsculas y el teléfono sin espacios ni guiones.
 */
export function clientFormToPayload(values: ClientFormValues): ClientPayload {
  const isCompany = values.kind === 'company';

  return {
    kind: values.kind,
    first_name: isCompany ? null : values.first_name.trim(),
    last_name: isCompany ? null : values.last_name.trim(),
    business_name: isCompany ? values.business_name.trim() : null,
    document_number: values.document_number.trim(),
    email: normalizeEmail(values.email),
    phone: normalizePhone(values.phone),
    address: optional(values.address),
    city: optional(values.city),
    notifications_enabled: values.notifications_enabled,
    notes: optional(values.notes),
  };
}
