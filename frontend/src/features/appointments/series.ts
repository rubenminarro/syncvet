import { isApiError } from '../../lib/api/errors';
import { formatDateOnly, formatTime } from '../../lib/format/dates';
import { addDaysToDay, daysBetween, parseDay, weekdayOf } from './dates';
import { appointmentTimes } from './times';
import type { Appointment } from './types';

/**
 * Las series de turnos (3.6; diseño de la F3, "3.6 Las series" y preguntas 16 y 17), espejo de `App\Support\AppointmentRecurrence`
 * y de `StoreAppointmentRequest::validateRecurrence`: el turno `i` es el primero más `7 × every_weeks × i` días **de
 * calendario de Asunción**, a la misma hora de pared (nunca sumando milisegundos: hasta el 15/10/2024 hubo días de 23 y de 25
 * horas, H9). Uno de `count` (2 a 52) o `until` (una fecha desde la del primer turno que dé de 2 a 52 turnos); la última
 * fecha, como mucho un año después del primero.
 */

/** `AppointmentRecurrence::MIN_COUNT`, `MAX_COUNT` y `MAX_EVERY_WEEKS`. */
export const SERIES_MIN_COUNT = 2;
export const SERIES_MAX_COUNT = 52;
export const SERIES_MAX_EVERY_WEEKS = 12;

/** Los mensajes de la API (`AppointmentMessages`), tal cual. */
export const SERIES_MESSAGES = {
  everyWeeks: `La serie se repite cada 1 a ${String(SERIES_MAX_EVERY_WEEKS)} semanas.`,
  count: `La serie tiene de ${String(SERIES_MIN_COUNT)} a ${String(SERIES_MAX_COUNT)} turnos.`,
  tooMany: `La serie no puede tener más de ${String(SERIES_MAX_COUNT)} turnos.`,
  tooFew: `La serie tiene que tener al menos ${String(SERIES_MIN_COUNT)} turnos.`,
  untilFormat: 'Ingresá la fecha de fin de la serie (2026-12-31).',
  untilBefore: 'La fecha de fin de la serie no puede ser anterior al primer turno.',
  untilTooFar:
    'La fecha de fin de la serie puede ser, como mucho, un año después del primer turno.',
  countTooFar:
    'La última fecha de la serie puede ser, como mucho, un año después del primer turno: reducí la cantidad de turnos o las semanas entre turnos.',
} as const;

export type SeriesEnd = 'count' | 'until';

/** Lo que el formulario sabe de la repetición, ya leído de sus campos de texto. */
export interface SeriesInput {
  /** El día del primer turno (`yyyy-MM-dd` de Asunción). */
  firstDay: string;
  everyWeeks: number;
  end: SeriesEnd;
  count: number | null;
  until: string | null;
}

/**
 * Un año después del día, como `CarbonImmutable::addYear()` (con desborde: el 29/02 da el 01/03 del año siguiente).
 */
export function oneYearAfter(day: string): string {
  const parts = parseDay(day);

  if (parts === null) {
    throw new Error(`Día mal formado: ${day}`);
  }

  const next = `${String(parts.year + 1).padStart(4, '0')}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`;

  // El 29/02 en un año que no es bisiesto no existe: el día siguiente al 28/02.
  return parseDay(next) === null
    ? addDaysToDay(`${String(parts.year + 1).padStart(4, '0')}-02-28`, 1)
    : next;
}

/** El día del turno `index` (0 es el primero): `7 × everyWeeks × index` días de calendario después. */
export function seriesDay(firstDay: string, everyWeeks: number, index: number): string {
  return addDaysToDay(firstDay, 7 * everyWeeks * index);
}

/**
 * Cuántos turnos da la serie: `count`, o los que entran hasta `until` inclusive (`AppointmentRecurrence::count`: la
 * diferencia en días de calendario, sin horas).
 */
export function seriesCount(
  input: Pick<SeriesInput, 'firstDay' | 'everyWeeks' | 'end' | 'count' | 'until'>,
): number {
  if (input.end === 'count') {
    return input.count ?? 0;
  }

  if (input.until === null) {
    return 0;
  }

  const days = daysBetween(input.firstDay, input.until);

  return days < 0 ? 0 : Math.floor(days / (7 * input.everyWeeks)) + 1;
}

export type SeriesField = 'every_weeks' | 'count' | 'until';

/**
 * El error de la repetición, como la API y con su mensaje (en el campo que la API usa: `recurrence.every_weeks`,
 * `recurrence.count` o `recurrence.until`), o null. `everyWeeks` y `count` llegan como texto del campo.
 */
export function seriesError(raw: {
  firstDay: string;
  everyWeeks: string;
  end: SeriesEnd;
  count: string;
  until: string;
}): { field: SeriesField; message: string } | null {
  const everyWeeks = /^\d+$/.test(raw.everyWeeks.trim()) ? Number(raw.everyWeeks.trim()) : null;

  if (everyWeeks === null || everyWeeks < 1 || everyWeeks > SERIES_MAX_EVERY_WEEKS) {
    return { field: 'every_weeks', message: SERIES_MESSAGES.everyWeeks };
  }

  if (raw.end === 'count') {
    const count = /^\d+$/.test(raw.count.trim()) ? Number(raw.count.trim()) : null;

    if (count === null || count < SERIES_MIN_COUNT) {
      return { field: 'count', message: SERIES_MESSAGES.count };
    }

    if (count > SERIES_MAX_COUNT) {
      return { field: 'count', message: SERIES_MESSAGES.tooMany };
    }

    // Sin el primer día, la API responde el error del inicio (lo marca el campo de la fecha).
    if (parseDay(raw.firstDay) === null) {
      return null;
    }

    if (seriesDay(raw.firstDay, everyWeeks, count - 1) > oneYearAfter(raw.firstDay)) {
      return { field: 'count', message: SERIES_MESSAGES.countTooFar };
    }

    return null;
  }

  if (parseDay(raw.until) === null) {
    return { field: 'until', message: SERIES_MESSAGES.untilFormat };
  }

  if (parseDay(raw.firstDay) === null) {
    return null;
  }

  if (raw.until < raw.firstDay) {
    return { field: 'until', message: SERIES_MESSAGES.untilBefore };
  }

  if (raw.until > oneYearAfter(raw.firstDay)) {
    return { field: 'until', message: SERIES_MESSAGES.untilTooFar };
  }

  const count = seriesCount({
    firstDay: raw.firstDay,
    everyWeeks,
    end: 'until',
    count: null,
    until: raw.until,
  });

  if (count > SERIES_MAX_COUNT) {
    return { field: 'until', message: SERIES_MESSAGES.tooMany };
  }

  if (count < SERIES_MIN_COUNT) {
    return { field: 'until', message: SERIES_MESSAGES.tooFew };
  }

  return null;
}

/** Una fecha de la vista previa: el día, el inicio y el fin para la API (con el desplazamiento de ese día en Asunción). */
export interface SeriesOccurrence {
  day: string;
  starts_at: string;
  ends_at: string;
}

/**
 * Las fechas de la serie, a la misma hora de pared y con la misma duración (la vista previa antes de guardar). Vacío si
 * algo no tiene forma (la vista previa se muestra solo con la repetición válida).
 */
export function seriesOccurrences(
  input: SeriesInput,
  clock: string,
  durationMinutes: number,
): SeriesOccurrence[] {
  const total = seriesCount(input);
  const occurrences: SeriesOccurrence[] = [];

  for (let index = 0; index < Math.min(total, SERIES_MAX_COUNT); index++) {
    const day = seriesDay(input.firstDay, input.everyWeeks, index);
    const times = appointmentTimes(day, clock, durationMinutes);

    if (times === null) {
      return [];
    }

    occurrences.push({ day, starts_at: times.starts_at, ends_at: times.ends_at });
  }

  return occurrences;
}

/** Los días de la semana en plural, como los dice el resumen ("los martes"). */
const WEEKDAY_PLURALS = [
  'lunes',
  'martes',
  'miércoles',
  'jueves',
  'viernes',
  'sábados',
  'domingos',
];

/**
 * El resumen de la serie: "8 turnos, los martes a las 09:30, hasta el 12/01/2027" o "4 turnos, cada 2 semanas, los lunes a
 * las 10:00, hasta el 21/12/2026".
 */
export function seriesSummary(
  occurrences: readonly SeriesOccurrence[],
  everyWeeks: number,
): string {
  const first = occurrences[0];
  const last = occurrences.at(-1);

  if (first === undefined || last === undefined) {
    return '';
  }

  const weekday = WEEKDAY_PLURALS[weekdayOf(first.day) - 1] ?? '';
  const every = everyWeeks === 1 ? '' : `, cada ${String(everyWeeks)} semanas`;

  return `${String(occurrences.length)} turnos${every}, los ${weekday} a las ${formatTime(first.starts_at)}, hasta el ${formatDateOnly(last.day)}`;
}

/** El cuerpo de `recurrence` para la API: `every_weeks` y uno de `count` o `until` (el otro, null). */
export function recurrencePayload(input: SeriesInput): {
  every_weeks: number;
  count: number | null;
  until: string | null;
} {
  return input.end === 'count'
    ? { every_weeks: input.everyWeeks, count: input.count, until: null }
    : { every_weeks: input.everyWeeks, count: null, until: input.until };
}

/** Un motivo de `errors.conflicts` (`AppointmentService::OUTSIDE_HOURS`, `IN_BLOCK`, `OVERLAP`). */
export type ConflictReason = 'outside_hours' | 'in_block' | 'overlap';

export interface SeriesConflict {
  starts_at: string;
  reason: string;
  message: string;
}

/**
 * La lista de `errors.conflicts` de un 409 `APPOINTMENT_SERIES_CONFLICT` (H8): cada fecha con su motivo y su mensaje, o null
 * si el error es otro. Una entrada sin la forma esperada se descarta (la lista nunca rompe la pantalla).
 */
export function seriesConflicts(error: unknown): SeriesConflict[] | null {
  if (!isApiError(error) || error.errorCode !== 'APPOINTMENT_SERIES_CONFLICT') {
    return null;
  }

  const raw = error.errors?.conflicts;

  if (!Array.isArray(raw)) {
    return [];
  }

  return raw.flatMap((item: unknown) => {
    if (typeof item !== 'object' || item === null) {
      return [];
    }

    const { starts_at, reason, message } = item as Record<string, unknown>;

    return typeof starts_at === 'string' &&
      typeof reason === 'string' &&
      typeof message === 'string'
      ? [{ starts_at, reason, message }]
      : [];
  });
}

/**
 * Los conflictos de la última respuesta que caen en la vista previa, por el día de cada fecha (revisión de la 3.6): se compara
 * el instante de inicio, así que si el formulario cambió de hora o de fechas después del 409, no se marca nada viejo.
 */
export function conflictsByDay(
  occurrences: readonly SeriesOccurrence[],
  conflicts: readonly SeriesConflict[],
): Map<string, SeriesConflict> {
  const byInstant = new Map(
    conflicts.map((conflict) => [new Date(conflict.starts_at).getTime(), conflict]),
  );
  const found = new Map<string, SeriesConflict>();

  for (const occurrence of occurrences) {
    const conflict = byInstant.get(new Date(occurrence.starts_at).getTime());

    if (conflict !== undefined) {
      found.set(occurrence.day, conflict);
    }
  }

  return found;
}

/** El anuncio de cuántas fechas de la vista previa chocan ("2 de las 8 fechas no se pueden dar: …"). */
export function previewConflictText(conflicting: number, total: number): string {
  if (conflicting === 0) {
    return '';
  }

  return conflicting === 1
    ? `1 de las ${String(total)} fechas no se puede dar: está marcada en la lista.`
    : `${String(conflicting)} de las ${String(total)} fechas no se pueden dar: están marcadas en la lista.`;
}

/**
 * ¿"Dar la serie con sobreturnos" sirve? Solo si todas las fechas chocan por superposición: `overbook` saltea solo esa
 * regla, y con una fuera del horario o en un bloqueo la API volvería a responder la lista (pregunta 17).
 */
export function onlyOverlaps(conflicts: readonly SeriesConflict[]): boolean {
  return conflicts.length > 0 && conflicts.every((conflict) => conflict.reason === 'overlap');
}

/** El alcance de la cancelación (`App\Enums\AppointmentCancelScope`). */
export type CancelScope = 'single' | 'following' | 'series';

export const CANCEL_SCOPE_OPTIONS: { value: CancelScope; label: string }[] = [
  { value: 'single', label: 'Solo este turno' },
  { value: 'following', label: 'Este y los siguientes' },
  { value: 'series', label: 'Toda la serie' },
];

/** Los estados que la cancelación con alcance toca en los demás turnos de la serie: a confirmar y confirmado. */
const SCOPE_CANCELLABLE: ReadonlySet<string> = new Set(['pending_confirmation', 'confirmed']);

/** El orden de la serie de la API: por `starts_at` y, en el mismo instante, por id. */
function seriesOrder(
  a: Pick<Appointment, 'id' | 'starts_at'>,
  b: Pick<Appointment, 'id' | 'starts_at'>,
): number {
  const byStart = new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime();

  return byStart === 0 ? a.id - b.id : byStart;
}

/**
 * Los turnos que cancelaría un alcance, como `AppointmentTransitionService` (3.3 del backend): el pedido y, de los demás de la
 * serie (los siguientes por `starts_at` e id con `following`; todos con `series`), solo los a confirmar o confirmados que
 * todavía no empezaron (`starts_at > now`). Con `single`, solo el pedido. La cifra real llega en el `message` del 200.
 */
export function scopeAffected(
  appointment: Pick<Appointment, 'id' | 'starts_at'>,
  series: readonly Pick<Appointment, 'id' | 'starts_at' | 'status'>[],
  scope: CancelScope,
  now: Date,
): number {
  if (scope === 'single') {
    return 1;
  }

  const others = series.filter((item) => {
    if (item.id === appointment.id) {
      return false;
    }

    if (scope === 'following' && seriesOrder(item, appointment) < 0) {
      return false;
    }

    return (
      SCOPE_CANCELLABLE.has(item.status.value) && new Date(item.starts_at).getTime() > now.getTime()
    );
  });

  return 1 + others.length;
}

/** "Turno 3 de 8" (el lugar del turno en su serie, por `starts_at` e id), o null si no está en la lista. */
export function seriesPosition(
  appointment: Pick<Appointment, 'id'>,
  series: readonly Pick<Appointment, 'id' | 'starts_at'>[],
): { index: number; total: number } | null {
  const ordered = [...series].sort(seriesOrder);
  const index = ordered.findIndex((item) => item.id === appointment.id);

  return index === -1 ? null : { index: index + 1, total: ordered.length };
}

/** Lo que se puede hacer con una serie que choca, según los motivos y el permiso (pregunta 17: no se omiten fechas). */
export const SERIES_CONFLICT_HINTS = {
  noPermission: 'Cambiá la hora o las fechas: no tenés permiso para dar sobreturnos.',
  fix: 'Cambiá la hora, el día o el fin de la serie, o cancelá el alta: la serie se da entera o no se da.',
} as const;

/** "Se cancelan 4 turnos: este y 3 más de la serie." (la confirmación dice cuántos se afectan). */
export function scopeCountText(count: number): string {
  if (count <= 1) {
    return 'Se cancela solo este turno: ninguno más de la serie está a confirmar o confirmado sin empezar.';
  }

  const others = count - 1;

  return `Se cancelan ${String(count)} turnos: este y ${String(others)} más de la serie.`;
}
