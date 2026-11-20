import { z } from 'zod';
import type { CreateVeterinarianPayload, VeterinarianDetail, VeterinarianPayload } from './types';
import { trimLikeApi } from '../../lib/format/text';

/**
 * El formulario de alta y edición de la ficha de veterinario (2.3). Una función pura repite las reglas de forma de
 * StoreVeterinarianRequest y UpdateVeterinarianRequest (VeterinarianMessages) con sus mensajes; lo que necesita la base (el
 * usuario inactivo, sin el rol o ya veterinario; la matrícula repetida) lo dice el 422 de la API. El usuario guarda su id
 * como texto ('' es "sin elegir"); la comisión, lo escrito (con coma o punto).
 */
export interface VeterinarianFormValues {
  user_id: string;
  license_number: string;
  specialty: string;
  commission_percentage: string;
  notes: string;
}

/** Los campos del formulario, para `applyValidationErrors` (las claves de `errors` de la API). */
export const VETERINARIAN_FORM_FIELDS = [
  'user_id',
  'license_number',
  'specialty',
  'commission_percentage',
  'notes',
] as const satisfies readonly (keyof VeterinarianFormValues)[];

/** En la edición no está el usuario (solo para leer): un error suyo iría a la alerta. */
export const VETERINARIAN_EDIT_FIELDS = VETERINARIAN_FORM_FIELDS.filter(
  (field) => field !== 'user_id',
);

/** `VeterinarianMessages::LICENSE_MAX`. */
export const LICENSE_MAX = 30;
export const SPECIALTY_MAX = 100;
export const NOTES_MAX = 1000;

/** Los mensajes de VeterinarianMessages y, sin mensaje propio, los de `lang/es/validation.php` con su atributo. */
export const VETERINARIAN_MESSAGES = {
  user_id: { required: 'Elegí el usuario del veterinario.' },
  license_number: { max: 'La matrícula no debe tener más de 30 caracteres.' },
  specialty: { max: 'El campo especialidad no puede tener más de 100 caracteres.' },
  commission_percentage: {
    numeric: 'La comisión debe ser un número.',
    between: 'La comisión debe estar entre 0 y 100.',
    decimal: 'La comisión puede tener hasta 2 decimales.',
  },
  notes: { max: 'El campo notas no puede tener más de 1000 caracteres.' },
} as const;

export const EMPTY_VETERINARIAN_FORM: VeterinarianFormValues = {
  user_id: '',
  license_number: '',
  specialty: '',
  commission_percentage: '',
  notes: '',
};

/** La comisión de la API ("12.50") como se escribe: con coma decimal ("12,50"). */
export function commissionToForm(commission: string): string {
  return commission.replace('.', ',');
}

/** La ficha guardada como valores del formulario de edición (null → vacío). */
export function veterinarianToForm(veterinarian: VeterinarianDetail): VeterinarianFormValues {
  return {
    user_id: String(veterinarian.user.id),
    license_number: veterinarian.license_number ?? '',
    specialty: veterinarian.specialty ?? '',
    commission_percentage: commissionToForm(veterinarian.commission_percentage),
    notes: veterinarian.notes ?? '',
  };
}

/** La matrícula como la guarda y la compara la API: en mayúsculas y sin los espacios de los extremos. */
export function normalizeLicense(value: string): string {
  return trimLikeApi(value).toUpperCase();
}

/** La comisión como se manda: sin los espacios de los extremos y con la coma pasada a punto ("12,5" → "12.5"). */
export function normalizeCommission(value: string): string {
  return trimLikeApi(value).replace(',', '.');
}

/** `is_numeric` de PHP, sin los espacios: signo, dígitos con un punto, y el exponente (como el peso del paciente). */
const NUMERIC = /^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/;
/** La regla `decimal` de Laravel: los decimales después del punto. */
const DECIMAL = /^[+-]?\d*\.?(\d*)$/;

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

/** El primer error de la comisión (ya normalizada), en el orden de la API: numeric, min y max, decimal. Vacía es 0. */
function commissionError(commission: string): string | null {
  const m = VETERINARIAN_MESSAGES.commission_percentage;

  if (commission === '') {
    return null;
  }

  if (!NUMERIC.test(commission)) {
    return m.numeric;
  }

  const number = Number(commission);

  if (number < 0 || number > 100) {
    return m.between;
  }

  const decimals = DECIMAL.exec(commission)?.[1];

  return decimals === undefined || decimals.length > 2 ? m.decimal : null;
}

/** Los errores de cada campo; vacío si se puede mandar. `withUser`: el alta (en la edición no se elige el usuario). */
export function veterinarianFormErrors(
  values: VeterinarianFormValues,
  withUser: boolean,
): Partial<Record<keyof VeterinarianFormValues, string>> {
  const m = VETERINARIAN_MESSAGES;
  const errors: Partial<Record<keyof VeterinarianFormValues, string | null>> = {};

  if (withUser && values.user_id === '') {
    errors.user_id = m.user_id.required;
  }

  if (length(normalizeLicense(values.license_number)) > LICENSE_MAX) {
    errors.license_number = m.license_number.max;
  }

  if (length(trimLikeApi(values.specialty)) > SPECIALTY_MAX) {
    errors.specialty = m.specialty.max;
  }

  errors.commission_percentage = commissionError(normalizeCommission(values.commission_percentage));

  if (length(trimLikeApi(values.notes)) > NOTES_MAX) {
    errors.notes = m.notes.max;
  }

  return Object.fromEntries(
    Object.entries(errors).filter(
      (entry): entry is [string, string] => typeof entry[1] === 'string',
    ),
  );
}

const shape = z.object({
  user_id: z.string(),
  license_number: z.string(),
  specialty: z.string(),
  commission_percentage: z.string(),
  notes: z.string(),
});

/** El esquema de React Hook Form: cada error de `veterinarianFormErrors` en su campo. */
export function veterinarianFormSchema(withUser: boolean) {
  return shape.superRefine((values, context) => {
    for (const [field, message] of Object.entries(veterinarianFormErrors(values, withUser))) {
      context.addIssue({ code: 'custom', path: [field], message });
    }
  });
}

/** Un texto opcional: sin los espacios de los extremos, y null si queda vacío. */
function optional(value: string): string | null {
  const trimmed = trimLikeApi(value);

  return trimmed === '' ? null : trimmed;
}

/**
 * El cuerpo de la edición: la matrícula en mayúsculas (vacía, null), la comisión como texto con punto (vacía, "0": la API
 * la exige) y los opcionales vacíos en null. Nunca `user_id` ni `code` (los dos son `prohibited`), ni `status` (se cambia
 * con su botón en el detalle).
 */
export function veterinarianFormToPayload(values: VeterinarianFormValues): VeterinarianPayload {
  const commission = normalizeCommission(values.commission_percentage);

  return {
    license_number: optional(normalizeLicense(values.license_number)),
    specialty: optional(values.specialty),
    commission_percentage: commission === '' ? '0' : commission,
    notes: optional(values.notes),
  };
}

/** El cuerpo del alta: los datos y el usuario. */
export function veterinarianFormToCreatePayload(
  values: VeterinarianFormValues,
): CreateVeterinarianPayload {
  return { user_id: Number(values.user_id), ...veterinarianFormToPayload(values) };
}

/** La comisión para mostrar: con coma decimal, sin los ceros de más y con "%" ("12.50" → "12,5 %"). */
export function formatCommission(commission: string | null | undefined): string {
  if (commission === null || commission === undefined || !/^\d+(\.\d+)?$/.test(commission)) {
    return '';
  }

  const trimmed = commission.includes('.') ? commission.replace(/\.?0+$/, '') : commission;

  return `${trimmed.replace('.', ',')} %`;
}
