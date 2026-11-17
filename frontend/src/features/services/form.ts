import { z } from 'zod';
import {
  SERVICE_CATEGORY_VALUES,
  TAX_RATES,
  type ServiceCategory,
  type ServiceDetail,
  type ServicePayload,
  type TaxRate,
} from './types';

/**
 * El formulario de alta y edición del servicio (2.4). Una función pura repite las reglas de forma de StoreServiceRequest y
 * UpdateServiceRequest (`ServiceMessages` y `WholeAmount`) con sus mensajes; lo que necesita la base (el nombre repetido,
 * `SERVICE_NAME_NOT_EDITABLE`) lo dice la API. Cada campo guarda lo escrito: la duración y el precio como texto, el IVA como
 * el valor del grupo de opciones ('0', '5' o '10').
 */
export interface ServiceFormValues {
  name: string;
  category: string;
  duration_minutes: string;
  standard_price: string;
  tax_rate: string;
  description: string;
}

/** Los campos del formulario, para `applyValidationErrors` (las claves de `errors` de la API). */
export const SERVICE_FORM_FIELDS = [
  'name',
  'category',
  'duration_minutes',
  'standard_price',
  'tax_rate',
  'description',
] as const satisfies readonly (keyof ServiceFormValues)[];

/** `ServiceMessages::NAME_MAX`. */
export const NAME_MAX = 120;
export const DESCRIPTION_MAX = 1000;
/** `new WholeAmount(12)`: hasta 999.999.999.999. */
export const PRICE_MAX_DIGITS = 12;
export const DURATION_MIN = 5;
export const DURATION_MAX = 720;
export const DURATION_STEP = 5;
export const DEFAULT_DURATION = 30;
export const DEFAULT_TAX_RATE: TaxRate = 10;

/** Los mensajes de `ServiceMessages::messages()` y de `WholeAmount` (con el atributo "precio" de `lang/es/validation.php`). */
export const SERVICE_MESSAGES = {
  name: {
    required: 'El nombre del servicio es obligatorio.',
    min: 'El nombre del servicio debe tener al menos 2 caracteres.',
    max: 'El nombre del servicio no debe tener más de 120 caracteres.',
    regex:
      'El nombre del servicio debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, comas, guiones, apóstrofos, paréntesis, barras, "+" y "%", sin dos barras seguidas.',
  },
  category: { required: 'La categoría es obligatoria.' },
  duration_minutes: {
    required: 'La duración es obligatoria.',
    integer: 'La duración debe ser un número entero de minutos.',
    between: 'La duración debe estar entre 5 y 720 minutos.',
    multiple_of: 'La duración debe ser un múltiplo de 5 minutos.',
  },
  standard_price: {
    required: 'El precio es obligatorio.',
    whole: 'El precio debe ser un número entero, sin decimales, de 0 a 999.999.999.999.',
  },
  tax_rate: { required: 'La tasa de IVA es obligatoria.' },
  description: { max: 'La descripción no debe tener más de 1000 caracteres.' },
} as const;

/**
 * `ServiceMessages::NAME_REGEX` (`/^(?=[^\pL]*\pL)(?!.*\/\/)[\pL\pN\s.,\-'()\/+%]+$/su`): al menos una letra, sin `//` (el
 * comienzo del sufijo de baja) y solo letras, números, espacios y `. , - ' ( ) / + %`.
 */
export const SERVICE_NAME_PATTERN = /^(?=[^\p{L}]*\p{L})(?!.*\/\/)[\p{L}\p{N}\s.,\-'()/+%]+$/su;

export const EMPTY_SERVICE_FORM: ServiceFormValues = {
  name: '',
  category: '',
  duration_minutes: String(DEFAULT_DURATION),
  standard_price: '',
  tax_rate: String(DEFAULT_TAX_RATE),
  description: '',
};

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

/** Dígitos con separador de miles bien agrupados ("150.000", "12.500.000.000"), como se escriben en Paraguay. */
const GROUPED = /^\d{1,3}(?:\.\d{3})+$/;

/**
 * El precio escrito como lo manda el formulario: sin los espacios de los extremos y sin los puntos de miles ("150.000" →
 * "150000"), solo si están bien agrupados; si no, tal cual (y la validación lo rechaza: "1500.00" no es "150000").
 */
export function normalizePrice(value: string): string {
  const trimmed = value.trim();

  return GROUPED.test(trimmed) ? trimmed.replaceAll('.', '') : trimmed;
}

/**
 * El importe que acepta `WholeAmount`: solo dígitos y, sin los ceros de adelante, hasta 12. Devuelve los dígitos sin ceros
 * de adelante ("007" → "7"), como los compara la API, o null si no es un importe entero válido. Todo como texto: nunca
 * pasa por un número de coma flotante.
 */
export function wholeAmount(normalized: string): string | null {
  if (!/^\d+$/.test(normalized)) {
    return null;
  }

  const digits = normalized.replace(/^0+/, '');
  const amount = digits === '' ? '0' : digits;

  return amount.length > PRICE_MAX_DIGITS ? null : amount;
}

/** Los dígitos con separador de miles ("150000" → "150.000"), como texto. */
function groupThousands(digits: string): string {
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * El precio de la API ("150000.00") como se escribe: sin los `.00` (como texto, sin pasar por un número) y con los puntos de
 * miles ("150.000"). Si viniera con decimales distintos de cero, tal cual: la API decide.
 */
export function priceToForm(price: string): string {
  const match = /^(\d+)(?:\.0+)?$/.exec(price);

  return match?.[1] === undefined ? price : groupThousands(match[1]);
}

/** El servicio guardado como valores del formulario de edición (null → vacío). */
export function serviceToForm(service: ServiceDetail): ServiceFormValues {
  return {
    name: service.name,
    category: service.category.value,
    duration_minutes: String(service.duration_minutes),
    standard_price: priceToForm(service.standard_price),
    tax_rate: String(service.tax_rate),
    description: service.description ?? '',
  };
}

function nameError(value: string): string | null {
  const m = SERVICE_MESSAGES.name;
  const name = value.trim();
  const size = length(name);

  if (size === 0) {
    return m.required;
  }

  if (size < 2) {
    return m.min;
  }

  if (size > NAME_MAX) {
    return m.max;
  }

  return SERVICE_NAME_PATTERN.test(name) ? null : m.regex;
}

/** La duración: un entero (como `integer` de Laravel, con signo opcional), de 5 a 720 y múltiplo de 5, en ese orden. */
function durationError(value: string): string | null {
  const m = SERVICE_MESSAGES.duration_minutes;
  const duration = value.trim();

  if (duration === '') {
    return m.required;
  }

  if (!/^[+-]?\d+$/.test(duration)) {
    return m.integer;
  }

  const minutes = Number(duration);

  if (minutes < DURATION_MIN || minutes > DURATION_MAX) {
    return m.between;
  }

  return minutes % DURATION_STEP === 0 ? null : m.multiple_of;
}

function priceError(value: string): string | null {
  const m = SERVICE_MESSAGES.standard_price;
  const price = normalizePrice(value);

  if (price === '') {
    return m.required;
  }

  return wholeAmount(price) === null ? m.whole : null;
}

/** Los errores de cada campo; vacío si se puede mandar. */
export function serviceFormErrors(
  values: ServiceFormValues,
): Partial<Record<keyof ServiceFormValues, string>> {
  const m = SERVICE_MESSAGES;
  const errors: Partial<Record<keyof ServiceFormValues, string | null>> = {
    name: nameError(values.name),
    category: SERVICE_CATEGORY_VALUES.includes(values.category as ServiceCategory)
      ? null
      : m.category.required,
    duration_minutes: durationError(values.duration_minutes),
    standard_price: priceError(values.standard_price),
    tax_rate: TAX_RATES.map(String).includes(values.tax_rate) ? null : m.tax_rate.required,
    description: length(values.description.trim()) > DESCRIPTION_MAX ? m.description.max : null,
  };

  return Object.fromEntries(
    Object.entries(errors).filter(
      (entry): entry is [string, string] => typeof entry[1] === 'string',
    ),
  );
}

const shape = z.object({
  name: z.string(),
  category: z.string(),
  duration_minutes: z.string(),
  standard_price: z.string(),
  tax_rate: z.string(),
  description: z.string(),
});

/** El esquema de React Hook Form: cada error de `serviceFormErrors` en su campo. */
export const serviceFormSchema = shape.superRefine((values, context) => {
  for (const [field, message] of Object.entries(serviceFormErrors(values))) {
    context.addIssue({ code: 'custom', path: [field], message });
  }
});

/**
 * El cuerpo del alta y de la edición (ya validado): el nombre sin los espacios de los extremos (la API lo limpia), la
 * duración y el IVA como números enteros, el precio como texto de solo dígitos ("150000", nunca un número) y la descripción
 * vacía en null. Nunca `code` (`prohibited`) ni `status` (se cambia con su botón en el detalle).
 */
export function serviceFormToPayload(values: ServiceFormValues): ServicePayload {
  const description = values.description.trim();

  return {
    name: values.name.trim(),
    category: values.category as ServiceCategory,
    duration_minutes: Number(values.duration_minutes.trim()),
    standard_price: wholeAmount(normalizePrice(values.standard_price)) ?? '',
    tax_rate: Number(values.tax_rate) as TaxRate,
    description: description === '' ? null : description,
  };
}

/** La duración para mostrar: "30 min". */
export function formatDuration(minutes: number): string {
  return `${String(minutes)} min`;
}

/** El IVA para mostrar: "10 %". */
export function formatTaxRate(rate: number): string {
  return `${String(rate)} %`;
}
