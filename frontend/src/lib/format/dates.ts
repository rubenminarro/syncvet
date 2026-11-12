import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';

/**
 * Fechas de la aplicación: la API manda las fechas con hora en UTC (`Z`) y se muestran siempre en la zona de la clínica,
 * nunca en la del navegador. Desde el 15/10/2024, America/Asuncion es UTC-3 todo el año (base de zonas IANA 2025a o
 * posterior; dates.test.ts lo comprueba, como AsuncionTimezoneTest en el backend).
 */
export const APP_TIME_ZONE = 'America/Asuncion';

type DateInput = string | number | Date;

/** El instante en la zona de Asunción. */
export function inAppZone(value: DateInput): TZDate {
  const timestamp = value instanceof Date ? value.getTime() : new Date(value).getTime();

  return new TZDate(timestamp, APP_TIME_ZONE);
}

/** 31/12/2026 */
export function formatDate(value: DateInput): string {
  return format(inAppZone(value), 'dd/MM/yyyy');
}

/** 31/12/2026 09:00 */
export function formatDateTime(value: DateInput): string {
  return format(inAppZone(value), 'dd/MM/yyyy HH:mm');
}

/** 09:00 */
export function formatTime(value: DateInput): string {
  return format(inAppZone(value), 'HH:mm');
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
