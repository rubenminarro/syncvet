import { TZDate } from '@date-fns/tz';
import { addDays, format } from 'date-fns';
import { APP_TIME_ZONE, inAppZone, toApiDateTime } from '../../lib/format/dates';
import type { ScheduleBlock, ScheduleBlockPayload } from './types';
import { trimLikeApi } from '../../lib/format/text';

/**
 * Los bloqueos de una agenda (3.1b): el formulario del alta y cómo se muestra un bloqueo.
 *
 * "Desde" y "hasta" son una fecha (`yyyy-MM-dd`, `DateField`) y una hora (`HH:mm`, `TimeField`) de pared de Asunción. Se
 * mandan con `toApiDateTime` (la hora de pared con el desplazamiento de esa fecha, `-03:00`), nunca con la zona de la PC.
 * "Todo el día" va de las 00:00 de la fecha desde a las 00:00 del día siguiente a la fecha hasta, armado con `TZDate` en
 * Asunción (un día de 23 o 25 horas queda entero). Un bloqueo, a diferencia de un turno, puede cruzar la medianoche.
 *
 * Las reglas repiten las de StoreScheduleBlockRequest, en su orden y con sus mensajes: los dos obligatorios; en múltiplos de
 * 5 minutos (en la hora de Asunción); el fin después del inicio (comparando los instantes); hasta 366 días (el inicio más
 * 366 días de calendario en Asunción, como `addDays` de Carbon); el motivo, hasta 255 caracteres.
 */

export interface BlockFormValues {
  fromDate: string;
  fromTime: string;
  toDate: string;
  toTime: string;
  allDay: boolean;
  reason: string;
}

export type BlockFormField = Exclude<keyof BlockFormValues, 'allDay'>;
export type BlockFormErrors = Partial<Record<BlockFormField, string>>;

export const BLOCK_MAX_DAYS = 366;
export const BLOCK_REASON_MAX = 255;

/** Los mensajes de StoreScheduleBlockRequest, tal cual; los de una fecha o una hora inválida son del navegador. */
export const BLOCK_MESSAGES = {
  startsRequired: 'El inicio del bloqueo es obligatorio.',
  endsRequired: 'El fin del bloqueo es obligatorio.',
  invalidDate: 'La fecha no es válida.',
  invalidTime: 'La hora no es válida.',
  offGrid: 'La hora debe estar en múltiplos de 5 minutos, sin segundos.',
  endsBeforeStart: 'El fin del bloqueo debe ser posterior a su inicio.',
  tooLong: `El bloqueo no puede durar más de ${String(BLOCK_MAX_DAYS)} días.`,
  reasonMax: `El motivo no debe tener más de ${String(BLOCK_REASON_MAX)} caracteres.`,
} as const;

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/;
const TIME = /^([01]\d|2[0-3]):([0-5]\d)$/;

type DateParts = [number, number, number];
type DateTimeParts = [number, number, number, number, number];

/** El formulario vacío: las dos fechas en hoy (de Asunción) y las horas sin elegir. */
export function emptyBlockForm(today: string): BlockFormValues {
  return { fromDate: today, fromTime: '', toDate: today, toTime: '', allDay: false, reason: '' };
}

/** La fecha `yyyy-MM-dd` como números (el mes empieza en 1), o null si no existe (2026-02-30). */
function dateParts(value: string): DateParts | null {
  const match = DATE_ONLY.exec(trimLikeApi(value));

  if (match === null) {
    return null;
  }

  const [year, month, day] = [Number(match[1]), Number(match[2]), Number(match[3])];
  const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
  const daysInMonth = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1];

  return daysInMonth === undefined || year < 1 || day < 1 || day > daysInMonth
    ? null
    : [year, month, day];
}

/** La hora `HH:mm` como números, o null. */
function timeParts(value: string): [number, number] | null {
  const match = TIME.exec(trimLikeApi(value));

  return match === null ? null : [Number(match[1]), Number(match[2])];
}

/** El instante de Asunción de unas partes de pared (`TZDate` normaliza el día 32 al 1 del mes siguiente). */
function wall([year, month, day, hours, minutes]: DateTimeParts): TZDate {
  return new TZDate(year, month - 1, day, hours, minutes, 0, 0, APP_TIME_ZONE);
}

interface Bounds {
  starts: DateTimeParts;
  ends: DateTimeParts;
}

/** El inicio y el fin como partes de pared, o los errores de forma (obligatorio, fecha u hora inválida). */
function bounds(values: BlockFormValues): Bounds | BlockFormErrors {
  const errors: BlockFormErrors = {};
  const side = (
    date: string,
    time: string,
    required: string,
    dateKey: BlockFormField,
    timeKey: BlockFormField,
  ) => {
    const day = trimLikeApi(date) === '' ? null : dateParts(date);

    if (trimLikeApi(date) === '') {
      errors[dateKey] = required;
    } else if (day === null) {
      errors[dateKey] = BLOCK_MESSAGES.invalidDate;
    }

    if (values.allDay) {
      return day;
    }

    const clock = trimLikeApi(time) === '' ? null : timeParts(time);

    if (trimLikeApi(time) === '') {
      errors[timeKey] = required;
    } else if (clock === null) {
      errors[timeKey] = BLOCK_MESSAGES.invalidTime;
    }

    return day === null || clock === null ? null : ([...day, ...clock] as DateTimeParts);
  };

  const from = side(
    values.fromDate,
    values.fromTime,
    BLOCK_MESSAGES.startsRequired,
    'fromDate',
    'fromTime',
  );
  const to = side(values.toDate, values.toTime, BLOCK_MESSAGES.endsRequired, 'toDate', 'toTime');

  if (from === null || to === null) {
    return errors;
  }

  if (values.allDay) {
    const [fy, fm, fd] = from;
    const [ty, tm, td] = to;

    // Hasta las 00:00 del día siguiente a la fecha hasta (TZDate lleva el 32 al mes siguiente).
    return { starts: [fy, fm, fd, 0, 0], ends: [ty, tm, td + 1, 0, 0] };
  }

  return { starts: from as DateTimeParts, ends: to as DateTimeParts };
}

function isBounds(value: Bounds | BlockFormErrors): value is Bounds {
  return 'starts' in value;
}

/** El largo en caracteres, como `mb_strlen` (un emoji es uno). */
function length(value: string): number {
  return Array.from(value).length;
}

/** Los errores de cada campo, en el orden de la API; vacío si se puede mandar. */
export function blockFormErrors(values: BlockFormValues): BlockFormErrors {
  const reason =
    length(trimLikeApi(values.reason)) > BLOCK_REASON_MAX
      ? { reason: BLOCK_MESSAGES.reasonMax }
      : {};
  const found = bounds(values);

  if (!isBounds(found)) {
    return { ...found, ...reason };
  }

  const { starts, ends } = found;

  // Múltiplos de 5 minutos: con "Todo el día" son las 00:00. TZDate solo corre una hora que no existe (el cambio de
  // horario de antes de 2024), así que sigue en la grilla.
  const grid: BlockFormErrors = {};

  if (starts[4] % 5 !== 0) {
    grid.fromTime = BLOCK_MESSAGES.offGrid;
  }

  if (ends[4] % 5 !== 0) {
    grid.toTime = BLOCK_MESSAGES.offGrid;
  }

  if (Object.keys(grid).length > 0) {
    return { ...grid, ...reason };
  }

  const start = wall(starts);
  const end = wall(ends);
  // El error del fin, en la fecha si es otro día y en la hora si es el mismo (lo que hay que cambiar).
  const endField: BlockFormField =
    values.allDay || trimLikeApi(values.toDate) !== trimLikeApi(values.fromDate)
      ? 'toDate'
      : 'toTime';

  if (end.getTime() <= start.getTime()) {
    return { [endField]: BLOCK_MESSAGES.endsBeforeStart, ...reason };
  }

  if (end.getTime() > addDays(start, BLOCK_MAX_DAYS).getTime()) {
    return { toDate: BLOCK_MESSAGES.tooLong, ...reason };
  }

  return reason;
}

/** El cuerpo de POST schedules/{id}/blocks; valida antes con `blockFormErrors`. */
export function blockFormToPayload(values: BlockFormValues): ScheduleBlockPayload {
  const found = bounds(values);

  if (!isBounds(found)) {
    throw new Error(
      'blockFormToPayload con un formulario inválido: validá antes con blockFormErrors.',
    );
  }

  const reason = trimLikeApi(values.reason);

  return {
    starts_at: toApiDateTime(...found.starts),
    ends_at: toApiDateTime(...found.ends),
    reason: reason === '' ? null : reason,
  };
}

/** ¿El instante cae a las 00:00 de Asunción? */
function isMidnight(date: TZDate): boolean {
  return date.getHours() === 0 && date.getMinutes() === 0;
}

/**
 * El período de un bloqueo, en Asunción: "20/11/2026, de 09:00 a 10:00"; "Del 20/11/2026 18:00 al 21/11/2026 08:00"; y
 * los de días enteros (de 00:00 a 00:00), "20/11/2026, todo el día" o "Del 20/11/2026 al 22/11/2026, todo el día".
 */
export function formatBlockPeriod(block: Pick<ScheduleBlock, 'starts_at' | 'ends_at'>): string {
  const start = inAppZone(block.starts_at);
  const end = inAppZone(block.ends_at);
  const day = (date: TZDate) => format(date, 'dd/MM/yyyy');
  const time = (date: TZDate) => format(date, 'HH:mm');

  if (isMidnight(start) && isMidnight(end)) {
    // El último día es el anterior al fin (sumando de a días de calendario, no de a 24 horas).
    const lastDay = addDays(end, -1);

    return day(start) === day(lastDay)
      ? `${day(start)}, todo el día`
      : `Del ${day(start)} al ${day(lastDay)}, todo el día`;
  }

  return day(start) === day(end)
    ? `${day(start)}, de ${time(start)} a ${time(end)}`
    : `Del ${day(start)} ${time(start)} al ${day(end)} ${time(end)}`;
}
