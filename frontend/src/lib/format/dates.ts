import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';

/**
 * Fechas de la aplicación: la API manda las fechas con hora en UTC (`Z`) y se muestran siempre en la zona de la clínica,
 * nunca en la del navegador. Desde el 15/10/2024, America/Asuncion es UTC-3 todo el año (base de zonas IANA 2025a o
 * posterior; dates.test.ts lo comprueba, como AsuncionTimezoneTest en el backend).
 *
 * Las fechas sin hora (`birth_date`, en `Y-m-d`) no son un instante: se muestran con formatDateOnly, que solo cambia el
 * formato del texto. Un valor vacío o inválido se muestra vacío (''), sin romper el render.
 */
export const APP_TIME_ZONE = 'America/Asuncion';

type DateInput = string | number | Date;

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/;

/** El instante en la zona de Asunción. */
export function inAppZone(value: DateInput): TZDate {
  const timestamp = value instanceof Date ? value.getTime() : new Date(value).getTime();

  return new TZDate(timestamp, APP_TIME_ZONE);
}

/** El instante formateado en Asunción, o '' si el valor no es una fecha. */
function formatInstant(value: DateInput | null | undefined, pattern: string): string {
  if (value === null || value === undefined || value === '') {
    return '';
  }

  const date = inAppZone(value);

  return Number.isNaN(date.getTime()) ? '' : format(date, pattern);
}

/**
 * Una fecha sin hora de la API (`2020-05-10`, como `birth_date`) como 10/05/2020: solo cambia el formato del texto, sin
 * pasar por Date (que la tomaría como medianoche UTC y en Asunción mostraría el día anterior). '' si no es una fecha válida.
 */
export function formatDateOnly(value: string | null | undefined): string {
  const match = DATE_ONLY.exec(value ?? '');

  if (match === null) {
    return '';
  }

  const [, year = '', month = '', day = ''] = match;
  const monthNumber = Number(month);
  const dayNumber = Number(day);
  const leap = Number(year) % 4 === 0 && (Number(year) % 100 !== 0 || Number(year) % 400 === 0);
  const daysInMonth = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][monthNumber - 1];

  if (daysInMonth === undefined || dayNumber < 1 || dayNumber > daysInMonth) {
    return '';
  }

  return `${day}/${month}/${year}`;
}

/** 31/12/2026. Una fecha sin hora (`2020-05-10`) va a formatDateOnly, sin convertirla de zona. */
export function formatDate(value: DateInput | null | undefined): string {
  if (typeof value === 'string' && DATE_ONLY.test(value)) {
    return formatDateOnly(value);
  }

  return formatInstant(value, 'dd/MM/yyyy');
}

/** 31/12/2026 09:00 */
export function formatDateTime(value: DateInput | null | undefined): string {
  return formatInstant(value, 'dd/MM/yyyy HH:mm');
}

/** 09:00 */
export function formatTime(value: DateInput | null | undefined): string {
  return formatInstant(value, 'HH:mm');
}

/**
 * Una fecha y hora de pared de Asunción, lista para mandar a la API (ISO 8601 con el desplazamiento de la zona, por ejemplo
 * 2026-07-01T09:00:00.000-03:00). El mes empieza en 1.
 */
export function toApiDateTime(
  year: number,
  month: number,
  day: number,
  hours = 0,
  minutes = 0,
): string {
  return new TZDate(year, month - 1, day, hours, minutes, 0, 0, APP_TIME_ZONE).toISOString();
}

/** "Hoy" en Asunción, como yyyy-MM-dd (el día de la clínica, no el de la PC). */
export function todayInAppZone(now: DateInput = Date.now()): string {
  return format(inAppZone(now), 'yyyy-MM-dd');
}
