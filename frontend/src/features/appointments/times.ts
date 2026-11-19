import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { APP_TIME_ZONE, inAppZone } from '../../lib/format/dates';
import { parseDay } from './dates';

/**
 * La hora del turno entre el formulario y la API (3.4): el formulario trabaja con la fecha y la hora de pared de Asunción
 * (`2026-11-17` y `09:30`) y la duración en minutos; la API recibe los instantes con el desplazamiento de esa fecha
 * (`2026-11-17T09:30:00.000-03:00`) y responde en UTC (`Z`). Nunca con la zona de la PC ni con un `-03:00` fijo: hasta el
 * 15/10/2024 Paraguay tuvo horario de verano (el 01/10/2023 duró 23 horas y el 23/03/2024, 25; H9).
 *
 * El fin se calcula como la API (`AppointmentService`: el inicio más la duración, en instantes) y "el mismo día" se compara
 * con el día de pared del fin: así un turno que pasa por la hora repetida del 23/03/2024 termina el mismo día aunque la
 * cuenta de pared diga lo contrario, y uno que termina a las 00:00 del día siguiente cruza la medianoche (H10).
 */

const CLOCK = /^([01]\d|2[0-3]):([0-5]\d)$/;

/** "09:30" como sus horas y minutos, o null si no tiene la forma `HH:mm`. */
export function parseClock(value: string): { hours: number; minutes: number } | null {
  const match = CLOCK.exec(value);

  if (match === null) {
    return null;
  }

  return { hours: Number(match[1]), minutes: Number(match[2]) };
}

/** El instante (ms) de una fecha y una hora de pared de Asunción, o null si alguna está mal formada. */
export function wallToInstant(day: string, clock: string): number | null {
  const parts = parseDay(day);
  const time = parseClock(clock);

  if (parts === null || time === null) {
    return null;
  }

  return new TZDate(
    parts.year,
    parts.month - 1,
    parts.day,
    time.hours,
    time.minutes,
    0,
    0,
    APP_TIME_ZONE,
  ).getTime();
}

/** Un instante como lo recibe la API: ISO 8601 con el desplazamiento de Asunción en esa fecha. */
export function instantToApi(instant: number): string {
  return new TZDate(instant, APP_TIME_ZONE).toISOString();
}

export interface AppointmentTimes {
  starts_at: string;
  ends_at: string;
  /** El día y la hora de pared del fin (para "Termina a las 10:00" y para la regla de la medianoche). */
  endDay: string;
  endClock: string;
}

/** El inicio y el fin para la API, o null si la fecha, la hora o la duración no tienen forma. */
export function appointmentTimes(
  day: string,
  clock: string,
  durationMinutes: number,
): AppointmentTimes | null {
  const start = wallToInstant(day, clock);

  if (start === null || !Number.isInteger(durationMinutes) || durationMinutes <= 0) {
    return null;
  }

  const end = start + durationMinutes * 60_000;
  const endWall = inAppZone(end);

  return {
    starts_at: instantToApi(start),
    ends_at: instantToApi(end),
    endDay: format(endWall, 'yyyy-MM-dd'),
    endClock: format(endWall, 'HH:mm'),
  };
}

/** ¿El turno termina otro día (también a las 00:00 del siguiente)? La API lo rechaza (H10). */
export function crossesMidnight(day: string, clock: string, durationMinutes: number): boolean {
  const times = appointmentTimes(day, clock, durationMinutes);

  return times !== null && times.endDay !== day;
}

/** La fecha y la hora de pared de Asunción de un instante de la API (`2026-11-17T12:30:00.000000Z` → 2026-11-17, 09:30). */
export function instantToWall(instant: string): { day: string; clock: string } {
  const wall = inAppZone(instant);

  return { day: format(wall, 'yyyy-MM-dd'), clock: format(wall, 'HH:mm') };
}

/** Los minutos entre dos instantes de la API (la duración de un turno guardado). */
export function minutesBetween(startsAt: string, endsAt: string): number {
  return Math.round((new Date(endsAt).getTime() - new Date(startsAt).getTime()) / 60_000);
}

/** ¿Los dos valores son el mismo instante? (`…Z` de la API contra `…-03:00` del formulario). */
export function sameInstant(a: string, b: string): boolean {
  return new Date(a).getTime() === new Date(b).getTime();
}

/** `2026-11-17T09:30` (el parámetro `inicio` de /turnos/nuevo, la hora de pared de Asunción) como fecha y hora, o null. */
export function parseInicio(value: string): { day: string; clock: string } | null {
  const [day = '', clock = '', ...rest] = value.split('T');

  if (rest.length > 0 || parseDay(day) === null || parseClock(clock) === null) {
    return null;
  }

  return { day, clock };
}

/** "30 minutos", "1 hora y 15 minutos" (el detalle del turno). */
export function durationText(minutes: number): string {
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  const hoursText = hours === 0 ? '' : hours === 1 ? '1 hora' : `${String(hours)} horas`;
  const minutesText = rest === 0 ? '' : rest === 1 ? '1 minuto' : `${String(rest)} minutos`;

  return [hoursText, minutesText].filter((text) => text !== '').join(' y ');
}
