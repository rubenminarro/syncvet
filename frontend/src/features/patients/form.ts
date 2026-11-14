import { z } from 'zod';
import { todayInAppZone } from '../../lib/format/dates';
import type { PatientDetail, PatientPayload, PatientSex } from './types';

/**
 * El formulario de alta y edición de pacientes (1.3a). Una función pura repite las reglas de forma de StorePatientRequest y
 * UpdatePatientRequest (obligatorios, largos, expresiones regulares, la fecha no futura y el peso) con los mismos mensajes de
 * PatientMessages; lo que necesita la base (especie o raza inactiva, el microchip en uso) lo dice el 422 de la API.
 *
 * Los selectores guardan el id como texto ('' es "sin elegir"); la fecha, el texto `yyyy-MM-dd` del `<input type="date">`,
 * sin pasar nunca por `Date`; el peso, lo escrito (con coma o punto).
 */
export interface PatientFormValues {
  name: string;
  species_id: string;
  breed_id: string;
  sex: PatientSex;
  is_neutered: boolean;
  birth_date: string;
  birth_date_estimated: boolean;
  color: string;
  weight_kg: string;
  microchip: string;
  allergies: string;
  notes: string;
}

/** Los campos del formulario, para `applyValidationErrors` (las claves de `errors` de la API). */
export const PATIENT_FORM_FIELDS = [
  'name',
  'species_id',
  'breed_id',
  'sex',
  'is_neutered',
  'birth_date',
  'birth_date_estimated',
  'color',
  'weight_kg',
  'microchip',
  'allergies',
  'notes',
] as const satisfies readonly (keyof PatientFormValues)[];

export const EMPTY_PATIENT_FORM: PatientFormValues = {
  name: '',
  species_id: '',
  breed_id: '',
  // "Sin determinar" por defecto (diseño de la 1.3a).
  sex: 'unknown',
  is_neutered: false,
  birth_date: '',
  birth_date_estimated: false,
  color: '',
  weight_kg: '',
  microchip: '',
  allergies: '',
  notes: '',
};

/** El peso de la API ("12.50") como se escribe en el formulario: con coma decimal ("12,50"). */
export function weightToForm(weight: string | null): string {
  return weight === null ? '' : weight.replace('.', ',');
}

/** Los datos guardados de un paciente, como valores del formulario de edición (null → vacío). */
export function patientToForm(patient: PatientDetail): PatientFormValues {
  return {
    name: patient.name,
    species_id: patient.species === null ? '' : String(patient.species.id),
    breed_id: patient.breed === null ? '' : String(patient.breed.id),
    sex: patient.sex.value,
    is_neutered: patient.is_neutered,
    birth_date: patient.birth_date ?? '',
    birth_date_estimated: patient.birth_date_estimated,
    color: patient.color ?? '',
    weight_kg: weightToForm(patient.weight_kg),
    microchip: patient.microchip ?? '',
    allergies: patient.allergies ?? '',
    notes: patient.notes ?? '',
  };
}

// Las expresiones de la API (PHP con /u), con \pL y \pN escritos \p{L} y \p{N}.
const NAME = /^[\p{L}\p{N}\s.\-']+$/u;
const COLOR = /^[\p{L}\s,-]+$/u;
const MICROCHIP = /^[A-Za-z0-9]+$/;
const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/;
/** `is_numeric` de PHP, sin los espacios (TrimStrings ya los sacó): signo, dígitos con un punto, y el exponente. */
const NUMERIC = /^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/;
/** La regla `decimal` de Laravel (ValidatesAttributes::validateDecimal): los decimales después del punto. */
const DECIMAL = /^[+-]?\d*\.?(\d*)$/;

/** Los mensajes de PatientMessages (backend), tal cual. */
export const PATIENT_MESSAGES = {
  name: {
    required: 'El nombre del paciente es obligatorio.',
    max: 'El nombre no debe tener más de 100 caracteres.',
    regex: 'El nombre contiene caracteres no permitidos.',
  },
  species_id: { required: 'La especie es obligatoria.' },
  birth_date: {
    date_format: 'La fecha de nacimiento debe tener el formato AAAA-MM-DD.',
    before_or_equal: 'La fecha de nacimiento no puede ser futura.',
  },
  color: {
    max: 'El color no debe tener más de 80 caracteres.',
    regex: 'El color solo puede contener letras, espacios, comas y guiones.',
  },
  weight_kg: {
    numeric: 'El peso debe ser un número.',
    min: 'El peso debe ser mayor que cero.',
    max: 'El peso no puede superar los 2000 kg.',
    decimal: 'El peso admite hasta 2 decimales.',
  },
  microchip: {
    max: 'El microchip no debe tener más de 30 caracteres.',
    regex: 'El microchip solo puede contener letras y números.',
    unique: 'Este microchip ya está registrado en otro paciente.',
  },
  allergies: { max: 'Las alergias no deben tener más de 1000 caracteres.' },
  notes: { max: 'Las notas no deben tener más de 1000 caracteres.' },
} as const;

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

/** ¿Es una fecha del calendario (`date_format:Y-m-d` de la API: 2023-02-30 no lo es)? Sin pasar por `Date`. */
function isCalendarDate(value: string): boolean {
  const match = DATE_ONLY.exec(value);

  if (match === null) {
    return false;
  }

  const [, year = '', month = '', day = ''] = match;
  const y = Number(year);
  const leap = y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0);
  const daysInMonth = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][
    Number(month) - 1
  ];

  return daysInMonth !== undefined && Number(day) >= 1 && Number(day) <= daysInMonth;
}

/**
 * El peso como se manda: sin los espacios de los extremos y con la coma decimal pasada a punto ("12,5" → "12.5"); el texto,
 * tal cual, sin pasar por un número de coma flotante. '' si está vacío.
 */
export function normalizeWeight(value: string): string {
  return value.trim().replace(',', '.');
}

/** El primer error del peso (ya normalizado), en el orden de las reglas de la API: numeric, min, max, decimal. */
function weightError(weight: string): string | null {
  const m = PATIENT_MESSAGES.weight_kg;

  if (weight === '') {
    return null;
  }

  if (!NUMERIC.test(weight)) {
    return m.numeric;
  }

  // Los límites con el número (dos decimales: 0.01 y 2000 se representan bien); los decimales, con el texto.
  const number = Number(weight);

  if (number < 0.01) {
    return m.min;
  }

  if (number > 2000) {
    return m.max;
  }

  const decimals = DECIMAL.exec(weight)?.[1];

  return decimals === undefined || decimals.length > 2 ? m.decimal : null;
}

/** Los errores de cada campo; vacío si el formulario se puede mandar. `today` es el día de hoy en Asunción (`yyyy-MM-dd`). */
export function patientFormErrors(
  values: PatientFormValues,
  today: string = todayInAppZone(),
): Partial<Record<keyof PatientFormValues, string>> {
  const errors: Partial<Record<keyof PatientFormValues, string | null>> = {};
  const m = PATIENT_MESSAGES;

  const name = values.name.trim();

  if (name === '') {
    errors.name = m.name.required;
  } else if (length(name) > 100) {
    errors.name = m.name.max;
  } else if (!NAME.test(name)) {
    errors.name = m.name.regex;
  }

  if (values.species_id === '') {
    errors.species_id = m.species_id.required;
  }

  const birthDate = values.birth_date.trim();

  if (birthDate !== '') {
    if (!isCalendarDate(birthDate)) {
      errors.birth_date = m.birth_date.date_format;
    } else if (birthDate > today) {
      // Los dos son `yyyy-MM-dd`: comparar el texto es comparar las fechas. "Hoy" es el de Asunción, no el de la PC.
      errors.birth_date = m.birth_date.before_or_equal;
    }
  }

  const color = values.color.trim();

  if (color !== '') {
    if (length(color) > 80) {
      errors.color = m.color.max;
    } else if (!COLOR.test(color)) {
      errors.color = m.color.regex;
    }
  }

  errors.weight_kg = weightError(normalizeWeight(values.weight_kg));

  const microchip = values.microchip.trim();

  if (microchip !== '') {
    if (length(microchip) > 30) {
      errors.microchip = m.microchip.max;
    } else if (!MICROCHIP.test(microchip)) {
      errors.microchip = m.microchip.regex;
    }
  }

  if (length(values.allergies.trim()) > 1000) {
    errors.allergies = m.allergies.max;
  }

  if (length(values.notes.trim()) > 1000) {
    errors.notes = m.notes.max;
  }

  return Object.fromEntries(
    Object.entries(errors).filter(
      (entry): entry is [string, string] => typeof entry[1] === 'string',
    ),
  );
}

/** El esquema de React Hook Form: cada error de `patientFormErrors` en su campo, con el "hoy" de Asunción al validar. */
export const patientFormSchema = z
  .object({
    name: z.string(),
    species_id: z.string(),
    breed_id: z.string(),
    sex: z.enum(['male', 'female', 'unknown']),
    is_neutered: z.boolean(),
    birth_date: z.string(),
    birth_date_estimated: z.boolean(),
    color: z.string(),
    weight_kg: z.string(),
    microchip: z.string(),
    allergies: z.string(),
    notes: z.string(),
  })
  .superRefine((values, context) => {
    for (const [field, message] of Object.entries(patientFormErrors(values))) {
      context.addIssue({ code: 'custom', path: [field], message });
    }
  });

/** Un texto opcional: sin los espacios de los extremos, y null si queda vacío. */
function optional(value: string): string | null {
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

/**
 * El cuerpo que se manda: los ids como números ("Sin raza" es null), la fecha tal cual (`yyyy-MM-dd`), el peso como texto
 * con punto y los opcionales vacíos en null.
 */
export function patientFormToPayload(values: PatientFormValues): PatientPayload {
  return {
    name: values.name.trim(),
    species_id: Number(values.species_id),
    breed_id: values.breed_id === '' ? null : Number(values.breed_id),
    sex: values.sex,
    is_neutered: values.is_neutered,
    birth_date: optional(values.birth_date),
    birth_date_estimated: values.birth_date_estimated,
    color: optional(values.color),
    weight_kg: optional(normalizeWeight(values.weight_kg)),
    microchip: optional(values.microchip),
    allergies: optional(values.allergies),
    notes: optional(values.notes),
  };
}

/** El peso para mostrar: el texto de la API con coma decimal y sin los ceros de más ("12.50" → "12,5 kg"). */
export function formatWeight(weight: string | null): string {
  if (weight === null || !/^\d+(\.\d+)?$/.test(weight)) {
    return '';
  }

  const trimmed = weight.includes('.') ? weight.replace(/\.?0+$/, '') : weight;

  return `${trimmed.replace('.', ',')} kg`;
}
