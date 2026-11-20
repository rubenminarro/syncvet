import { TZDate } from '@date-fns/tz';
import { addDays, format } from 'date-fns';
import { APP_TIME_ZONE, inAppZone } from '../../lib/format/dates';
import { WEEKDAYS, type Weekday } from '../schedules/types';

/**
 * Las fechas de la agenda (diseño de la F3, "Fechas", H9 y H10), siempre en America/Asuncion con `@date-fns/tz`, nunca con
 * la zona de la PC:
 *
 * - **El día** es una fecha `yyyy-MM-dd` de Asunción (la de la URL). Se suma de a días con `addDays` sobre un `TZDate` (la
 *   hora de pared se conserva), nunca sumando los milisegundos de 24 horas: un día puede durar 23 horas (01/10/2023) o
 *   25 (23/03/2024).
 * - **La semana** empieza el lunes (`weekday` 1 de la API).
 * - **La posición en la grilla** sale de la hora de pared del instante (`inAppZone`), no de los milisegundos desde la
 *   medianoche: en un día de 25 horas, la hora repetida cae en su misma fila.
 */

const DAY = /^(\d{4})-(\d{2})-(\d{2})$/;

const MONTHS = [
  'enero',
  'febrero',
  'marzo',
  'abril',
  'mayo',
  'junio',
  'julio',
  'agosto',
  'septiembre',
  'octubre',
  'noviembre',
  'diciembre',
] as const;

/** Minutos de un día de 24 horas de pared (la última fila de la grilla termina ahí). */
export const MINUTES_PER_DAY = 24 * 60;

interface DayParts {
  year: number;
  month: number;
  day: number;
}

function daysInMonth(year: number, month: number): number {
  const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);

  return [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1] ?? 0;
}

/** El día como sus partes, o null si no tiene la forma `yyyy-MM-dd` o no existe (31/02). */
export function parseDay(value: string): DayParts | null {
  const match = DAY.exec(value);

  if (match === null) {
    return null;
  }

  const [, year = '', month = '', day = ''] = match;
  const parts = { year: Number(year), month: Number(month), day: Number(day) };

  if (
    parts.year < 1 ||
    parts.month < 1 ||
    parts.month > 12 ||
    parts.day < 1 ||
    parts.day > daysInMonth(parts.year, parts.month)
  ) {
    return null;
  }

  return parts;
}

/** ¿Es un día válido para la URL? */
export function isAgendaDay(value: string): boolean {
  return parseDay(value) !== null;
}

function requireDay(value: string): DayParts {
  const parts = parseDay(value);

  if (parts === null) {
    throw new Error(`Día inválido: ${value}`);
  }

  return parts;
}

/** El mediodía de pared del día en Asunción: existe siempre (los cambios de horario fueron a la medianoche). */
function noonOf(value: string): TZDate {
  const { year, month, day } = requireDay(value);

  return new TZDate(year, month - 1, day, 12, 0, 0, 0, APP_TIME_ZONE);
}

/** El día `amount` días después (o antes, con un negativo), en el calendario de Asunción. */
export function addDaysToDay(value: string, amount: number): string {
  return format(addDays(noonOf(value), amount), 'yyyy-MM-dd');
}

/** El día de la semana, con el número de la API: 1 = lunes … 7 = domingo. */
export function weekdayOf(value: string): Weekday {
  const sunday0 = noonOf(value).getDay();

  return (sunday0 === 0 ? 7 : sunday0) as Weekday;
}

/** El lunes de la semana del día (la semana empieza el lunes, como el horario de la API). */
export function weekStartOf(value: string): string {
  return addDaysToDay(value, 1 - weekdayOf(value));
}

/**
 * El comienzo real del día en Asunción, en milisegundos: las 00:00 de pared o, si no existieron (01/10/2023, el reloj saltó
 * de las 00:00 a la 01:00), el primer instante del día.
 */
export function dayStart(value: string): number {
  const { year, month, day } = requireDay(value);

  return new TZDate(year, month - 1, day, 0, 0, 0, 0, APP_TIME_ZONE).getTime();
}

/** El día como el intervalo [comienzo, comienzo del siguiente) en milisegundos. */
export function dayBounds(value: string): { start: number; end: number } {
  return { start: dayStart(value), end: dayStart(addDaysToDay(value, 1)) };
}

/** Las horas que dura el día en Asunción: 24, o 23 y 25 en los días del cambio de horario (hasta el 15/10/2024). */
export function dayLengthHours(value: string): number {
  const { start, end } = dayBounds(value);

  return (end - start) / 3_600_000;
}

/** El día de Asunción de un instante de la API (`yyyy-MM-dd`). */
export function wallDay(instant: string | number): string {
  return format(inAppZone(instant), 'yyyy-MM-dd');
}

/** Los minutos de pared de un instante de la API en Asunción, desde las 00:00 de su día (09:30 → 570). */
export function wallMinutes(instant: string | number): number {
  const date = inAppZone(instant);

  return date.getHours() * 60 + date.getMinutes();
}

/** "08:00" (los tramos del horario semanal, que son horas de pared) como minutos desde las 00:00. */
export function clockToMinutes(clock: string): number {
  const [hours = '0', minutes = '0'] = clock.split(':');

  return Number(hours) * 60 + Number(minutes);
}

/** Minutos desde las 00:00 como "09:15" (la etiqueta de una fila). */
export function minutesToClock(minutes: number): string {
  const hours = Math.floor(minutes / 60);

  return `${String(hours).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

/** "Martes 17 de noviembre" (el anuncio del lector de pantalla). */
export function dayName(value: string): string {
  const { month, day } = requireDay(value);
  const weekday = WEEKDAYS[weekdayOf(value) - 1]?.label ?? '';

  return `${weekday} ${String(day)} de ${MONTHS[month - 1] ?? ''}`;
}

/** "Martes 17 de noviembre de 2026" (el título de la agenda del día). */
export function dayTitle(value: string): string {
  return `${dayName(value)} de ${String(requireDay(value).year)}`;
}

/** Los siete días de la semana del día, de lunes a domingo (3.3), contados en el calendario de Asunción. */
export function weekDaysOf(value: string): string[] {
  const monday = weekStartOf(value);

  return [0, 1, 2, 3, 4, 5, 6].map((offset) => addDaysToDay(monday, offset));
}

/** El domingo de la semana del día. */
export function weekEndOf(value: string): string {
  return addDaysToDay(weekStartOf(value), 6);
}

/** La semana como el intervalo [lunes 00:00, lunes siguiente 00:00) en milisegundos de Asunción. */
export function weekBounds(value: string): { start: number; end: number } {
  const monday = weekStartOf(value);

  return { start: dayStart(monday), end: dayStart(addDaysToDay(monday, 7)) };
}

/** Las horas que dura la semana en Asunción: 168, o 167 y 169 en las del cambio de horario (hasta el 15/10/2024). */
export function weekLengthHours(value: string): number {
  const { start, end } = weekBounds(value);

  return (end - start) / 3_600_000;
}

/** Los días de calendario de `from` a `to` (0 si son el mismo; negativo si `to` es anterior), sin contar horas. */
export function daysBetween(from: string, to: string): number {
  const a = requireDay(from);
  const b = requireDay(to);

  const utcDays = (parts: DayParts) =>
    Date.UTC(parts.year, parts.month - 1, parts.day) / (24 * 3_600_000);

  // Las fechas como días UTC de calendario: sin zona ni horario de verano, la cuenta es exacta.
  return Math.round(utcDays(b) - utcDays(a));
}

/** "16 al 22 de noviembre de 2026", "30 de noviembre al 6 de diciembre de 2026" o "28 de diciembre de 2026 al 3 de enero de 2027". */
export function weekRangeText(value: string): string {
  const from = requireDay(weekStartOf(value));
  const to = requireDay(weekEndOf(value));
  const month = (parts: DayParts) => MONTHS[parts.month - 1] ?? '';

  if (from.year !== to.year) {
    return `${String(from.day)} de ${month(from)} de ${String(from.year)} al ${String(to.day)} de ${month(to)} de ${String(to.year)}`;
  }

  if (from.month !== to.month) {
    return `${String(from.day)} de ${month(from)} al ${String(to.day)} de ${month(to)} de ${String(to.year)}`;
  }

  return `${String(from.day)} al ${String(to.day)} de ${month(to)} de ${String(to.year)}`;
}

/** "Semana del 16 al 22 de noviembre de 2026" (el título de la semana). */
export function weekTitle(value: string): string {
  return `Semana del ${weekRangeText(value)}`;
}

/** "Lun 16" (el encabezado de una columna de la semana). */
export function shortDayName(value: string): string {
  const weekday = WEEKDAYS[weekdayOf(value) - 1]?.label ?? '';

  return `${weekday.slice(0, 3)} ${String(requireDay(value).day)}`;
}
