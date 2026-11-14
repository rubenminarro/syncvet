import { format } from 'date-fns';
import { inAppZone, toApiDateTime } from '../../lib/format/dates';

/**
 * El fallecimiento del paciente (1.3b): una fecha (`yyyy-MM-dd`, del `<input type="date">`) y una hora (`HH:mm`, del
 * `<input type="time">`), las dos de pared en Asunción. Se mandan con `toApiDateTime` (`2026-11-12T10:30:00.000-03:00`, el
 * desvío 8 de la 0.1), nunca con la zona de la PC. Las reglas repiten las de UpdatePatientRequest con sus mensajes
 * (PatientMessages): una fecha válida, no futura y no anterior a la de nacimiento (comparando el día, como la API).
 */
export interface DeceasedFormValues {
  date: string;
  time: string;
}

/** Los mensajes de la API (PatientMessages), tal cual; el de la hora es del navegador (la API recibe un solo campo). */
export const DECEASED_MESSAGES = {
  date: 'La fecha de fallecimiento no es válida.',
  time: 'La hora de fallecimiento no es válida.',
  future: 'La fecha de fallecimiento no puede ser futura.',
  beforeBirth: 'La fecha de fallecimiento no puede ser anterior a la fecha de nacimiento.',
} as const;

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/;
const TIME = /^([01]\d|2[0-3]):([0-5]\d)$/;

/** El momento como fecha y hora de pared de Asunción (los valores del diálogo): ahora, o el `deceased_at` guardado. */
export function deceasedToForm(value: string | number | Date = Date.now()): DeceasedFormValues {
  const local = inAppZone(value);

  return { date: format(local, 'yyyy-MM-dd'), time: format(local, 'HH:mm') };
}

/** Las partes de la fecha y la hora, o null si alguna no es válida (2023-02-30 no lo es). */
function parts(values: DeceasedFormValues): [number, number, number, number, number] | null {
  const date = DATE_ONLY.exec(values.date.trim());
  const time = TIME.exec(values.time.trim());

  if (date === null || time === null) {
    return null;
  }

  const [year, month, day] = [Number(date[1]), Number(date[2]), Number(date[3])];
  const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
  const daysInMonth = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1];

  if (daysInMonth === undefined || day < 1 || day > daysInMonth) {
    return null;
  }

  return [year, month, day, Number(time[1]), Number(time[2])];
}

/** El valor de `deceased_at` que se manda: la hora de pared de Asunción con su desplazamiento. */
export function deceasedToApi(values: DeceasedFormValues): string {
  const value = parts(values);

  if (value === null) {
    throw new Error(
      'deceasedToApi con una fecha u hora inválida: validá antes con deceasedFormErrors.',
    );
  }

  return toApiDateTime(...value);
}

/**
 * Los errores de cada campo; vacío si se puede mandar. `now` es el instante actual (la API compara con `now`); `birthDate`,
 * la fecha de nacimiento guardada (`yyyy-MM-dd` o null).
 */
export function deceasedFormErrors(
  values: DeceasedFormValues,
  { now = Date.now(), birthDate = null }: { now?: number; birthDate?: string | null } = {},
): Partial<Record<keyof DeceasedFormValues, string>> {
  const value = parts(values);

  if (value === null) {
    const dateOk = parts({ date: values.date, time: '00:00' }) !== null;

    return dateOk ? { time: DECEASED_MESSAGES.time } : { date: DECEASED_MESSAGES.date };
  }

  if (Date.parse(toApiDateTime(...value)) > now) {
    return { date: DECEASED_MESSAGES.future };
  }

  // Los dos son `yyyy-MM-dd` de Asunción: comparar el texto es comparar los días.
  if (birthDate !== null && values.date.trim() < birthDate) {
    return { date: DECEASED_MESSAGES.beforeBirth };
  }

  return {};
}
