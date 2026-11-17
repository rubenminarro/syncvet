import { TZDate } from '@date-fns/tz';
import { APP_TIME_ZONE } from '../../lib/format/dates';

/**
 * Los filtros de fecha de la auditoría ("desde" y "hasta"; diseño de la F2, 2.6, H7 y pregunta 10).
 *
 * En la URL van como la hora de pared de Asunción, sin zona: una fecha sola (`2026-11-09`) o una fecha con hora
 * (`2026-11-09T13:00`). A la API van como un instante en UTC (`2026-11-09T16:00:00.000Z`), calculado con `TZDate` en
 * America/Asuncion, nunca con la zona de la PC:
 *
 * - fecha sola: el día completo de Asunción; "desde" a las 00:00:00 y "hasta" a las 23:59:59 (el último segundo antes de
 *   que empiece el día siguiente, también en un día con cambio de horario);
 * - con hora: "desde" en ese minuto (segundo 0) y "hasta" con los segundos en 59: "hasta las 18:00" incluye todo ese minuto.
 */

/** La forma de un filtro de fecha en la URL. */
const FILTER_VALUE = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?$/;

/** El mensaje de la API para una fecha mal formada (IndexAuditLogRequest::DATE_FORMAT). */
export const AUDIT_DATE_FORMAT_MESSAGE =
  'Ingresá una fecha (2026-11-09) o una fecha con hora (2026-11-09T13:00:00Z).';

/** El mensaje de la API para "hasta" antes de "desde" (IndexAuditLogRequest::TO_BEFORE_FROM). */
export const AUDIT_TO_BEFORE_FROM_MESSAGE =
  'La fecha hasta no puede ser anterior a la fecha desde.';

export type AuditDateBound = 'from' | 'to';

interface WallTime {
  year: number;
  month: number;
  day: number;
  /** null: una fecha sola. */
  time: { hours: number; minutes: number } | null;
}

function daysInMonth(year: number, month: number): number {
  const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);

  return [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1] ?? 0;
}

/** El valor de la URL como hora de pared, o null si no tiene la forma o no es una fecha que exista (31/02, 24:00). */
export function parseAuditDate(value: string): WallTime | null {
  const match = FILTER_VALUE.exec(value);

  if (match === null) {
    return null;
  }

  const [, year = '', month = '', day = '', hours, minutes] = match;
  const wall: WallTime = {
    year: Number(year),
    month: Number(month),
    day: Number(day),
    time: hours === undefined ? null : { hours: Number(hours), minutes: Number(minutes) },
  };

  if (
    wall.year < 1 ||
    wall.month < 1 ||
    wall.month > 12 ||
    wall.day < 1 ||
    wall.day > daysInMonth(wall.year, wall.month)
  ) {
    return null;
  }

  if (wall.time !== null && (wall.time.hours > 23 || wall.time.minutes > 59)) {
    return null;
  }

  return wall;
}

/** ¿Es un filtro de fecha válido para la URL? */
export function isAuditDate(value: string): boolean {
  return parseAuditDate(value) !== null;
}

/** El instante de Asunción, en UTC (`toISOString()` de un `Date`, con `Z`). */
function utc(date: TZDate | number): string {
  return new Date(typeof date === 'number' ? date : date.getTime()).toISOString();
}

/**
 * El valor que se manda a la API (`from` o `to`) para un filtro de la URL, en UTC; null si el valor no es válido.
 * Fecha sola: "desde", a las 00:00:00 de Asunción; "hasta", el último segundo del día (el comienzo del día siguiente menos un
 * segundo: así también en un día de 23 o 25 horas). Con hora: "desde", con los segundos en 0; "hasta", en 59.
 */
export function toAuditApiDate(value: string, bound: AuditDateBound): string | null {
  const wall = parseAuditDate(value);

  if (wall === null) {
    return null;
  }

  const { year, month, day, time } = wall;

  if (time === null) {
    if (bound === 'from') {
      return utc(new TZDate(year, month - 1, day, 0, 0, 0, 0, APP_TIME_ZONE));
    }

    return utc(new TZDate(year, month - 1, day + 1, 0, 0, 0, 0, APP_TIME_ZONE).getTime() - 1000);
  }

  const seconds = bound === 'from' ? 0 : 59;

  return utc(new TZDate(year, month - 1, day, time.hours, time.minutes, seconds, 0, APP_TIME_ZONE));
}

/**
 * La hora de pared de un filtro como texto comparable (`2026-11-09T13:00:00`): una fecha sola es 00:00:00 en "desde" y
 * 23:59:59 en "hasta", y "hasta" con hora lleva los segundos en 59. Se compara como texto, sin pasar por `Date`.
 */
function wallTimeKey(value: string, bound: AuditDateBound): string | null {
  const wall = parseAuditDate(value);

  if (wall === null) {
    return null;
  }

  const date = value.slice(0, 10);

  if (wall.time === null) {
    return `${date}T${bound === 'from' ? '00:00:00' : '23:59:59'}`;
  }

  return `${value.slice(0, 16)}:${bound === 'from' ? '00' : '59'}`;
}

/** ¿"Hasta" es anterior a "desde"? Con uno de los dos vacío o inválido, no (eso lo dice su propio control). */
export function isAuditRangeReversed(from: string | undefined, to: string | undefined): boolean {
  const start = from === undefined ? null : wallTimeKey(from, 'from');
  const end = to === undefined ? null : wallTimeKey(to, 'to');

  return start !== null && end !== null && end < start;
}

/** La fecha y la hora de los campos, como van en la URL: '' sin fecha; la fecha sola sin hora; si no, `fechaThh:mm`. */
export function joinAuditDate(date: string, time: string): string {
  if (date === '') {
    return '';
  }

  return time === '' ? date : `${date}T${time}`;
}

/** El valor de la URL separado en los dos campos (la fecha `yyyy-MM-dd` y la hora `HH:mm`, o '' sin hora). */
export function splitAuditDate(value: string | undefined): { date: string; time: string } {
  if (value === undefined || !isAuditDate(value)) {
    return { date: '', time: '' };
  }

  return { date: value.slice(0, 10), time: value.slice(11, 16) };
}
