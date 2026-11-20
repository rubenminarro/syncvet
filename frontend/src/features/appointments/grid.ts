import { formatTime } from '../../lib/format/dates';
import type { ScheduleBlock, ScheduleHour } from '../schedules/types';
import {
  clockToMinutes,
  dayBounds,
  dayName,
  MINUTES_PER_DAY,
  shortDayName,
  wallDay,
  wallMinutes,
  weekDaysOf,
  weekdayOf,
} from './dates';
import { OCCUPYING_STATUSES, type Appointment } from './types';

/**
 * El armado de la grilla del día (diseño de la F3, "La grilla", 3.2), puro y sin React: qué agendas se ven, el rango de
 * horas, lo sombreado (fuera del horario y los bloqueos) y dónde va cada turno (fila, alto y carril). Todo en minutos de
 * pared de Asunción desde las 00:00 del día (`dates.ts`).
 */

/** Minutos de cada fila de la grilla. */
export const ROW_MINUTES = 15;

/** El rango por defecto sin tramos ni turnos: de 08:00 a 18:00. */
export const DEFAULT_RANGE = { start: 8 * 60, end: 18 * 60 } as const;

/** Lo que la grilla necesita de una agenda: de GET schedules (o del turno, si la agenda ya no está activa). */
export interface ColumnSchedule {
  id: number;
  name: string;
  color: string | null;
  veterinarian: { id: number; name: string } | null;
  /** false: no está entre las activas de la sucursal (inactiva, con turnos ese día). */
  is_active: boolean;
}

/** Una agenda de la grilla con lo que llegó de su detalle y de sus bloqueos (null: todavía no llegó). */
export interface GridScheduleInput {
  schedule: ColumnSchedule;
  hours: readonly ScheduleHour[] | null;
  blocks: readonly ScheduleBlock[] | null;
}

export interface Interval {
  start: number;
  end: number;
}

export interface GridBlock extends Interval {
  id: number;
  reason: string | null;
}

export interface GridItem {
  appointment: Appointment;
  /** Minutos de pared del inicio y del fin (el fin, el inicio más la duración, hasta las 24:00). */
  start: number;
  end: number;
  /** El carril y la cantidad de carriles del grupo de turnos superpuestos (lado a lado). */
  lane: number;
  lanes: number;
  /** Se superpone con un bloqueo de su agenda y ocupa el horario (la marca "Dentro de un bloqueo"). */
  inBlock: boolean;
  /** Termina después de la medianoche (la API no lo permite: H10; si llegara, se corta a las 24:00 con la marca). */
  continuesNextDay: boolean;
}

export interface GridColumn {
  /** Única en la grilla: la agenda (en el día) o el día (en la semana, 3.3). */
  key: string;
  /** El día de Asunción de la columna. */
  day: string;
  /** El encabezado: el nombre de la agenda o el día de la semana ("Lun 16"). */
  heading: string;
  /** Debajo del encabezado: el veterinario de la agenda o, en la semana, "Hoy". */
  subheading: string;
  /** Lo que nombra la columna en el nombre accesible de cada celda: la agenda o el día ("Lunes 16 de noviembre"). */
  cellLabel: string;
  /** Lo que se suma al final del nombre accesible de cada turno: la agenda y, en la semana, el día. */
  itemContext: string;
  schedule: ColumnSchedule;
  /** Los tramos sin atención dentro del rango (vacío si el horario todavía no llegó). */
  closed: Interval[];
  blocks: GridBlock[];
  items: GridItem[];
}

export interface DayGrid {
  /** El rango de horas que se dibuja, en horas enteras. */
  start: number;
  end: number;
  /** El comienzo de cada fila (de a `ROW_MINUTES`). */
  rows: number[];
  columns: GridColumn[];
}

const ms = (instant: string) => new Date(instant).getTime();

/** El turno en minutos de pared: el inicio por su hora de pared; el fin, por su duración (así, también en un día de 25 horas). */
function appointmentMinutes(appointment: Appointment, dayEnd: number) {
  const start = wallMinutes(appointment.starts_at);
  const duration = (ms(appointment.ends_at) - ms(appointment.starts_at)) / 60_000;

  return {
    start,
    end: Math.min(start + duration, MINUTES_PER_DAY),
    continuesNextDay: ms(appointment.ends_at) > dayEnd,
  };
}

/** Los tramos del día de la semana, unidos los que se tocan o se superponen, ordenados. */
function openIntervals(hours: readonly ScheduleHour[], weekday: number): Interval[] {
  const ranges = hours
    .filter((hour) => hour.weekday === weekday)
    .map((hour) => ({ start: clockToMinutes(hour.starts_at), end: clockToMinutes(hour.ends_at) }))
    .sort((a, b) => a.start - b.start);
  const merged: Interval[] = [];

  for (const range of ranges) {
    const last = merged.at(-1);

    if (last !== undefined && range.start <= last.end) {
      last.end = Math.max(last.end, range.end);
    } else {
      merged.push({ ...range });
    }
  }

  return merged;
}

/** Lo que queda del rango fuera de los tramos abiertos. */
function closedIntervals(open: Interval[], range: Interval): Interval[] {
  const closed: Interval[] = [];
  let cursor = range.start;

  for (const interval of open) {
    if (interval.start > cursor) {
      closed.push({ start: cursor, end: Math.min(interval.start, range.end) });
    }

    cursor = Math.max(cursor, interval.end);
  }

  if (cursor < range.end) {
    closed.push({ start: cursor, end: range.end });
  }

  return closed.filter((interval) => interval.end > interval.start);
}

/** Un bloqueo recortado al día, en minutos de pared (por su duración desde el comienzo recortado), o null si no lo toca. */
function blockInDay(block: ScheduleBlock, day: { start: number; end: number }): GridBlock | null {
  const startMs = Math.max(ms(block.starts_at), day.start);
  const endMs = Math.min(ms(block.ends_at), day.end);

  if (endMs <= startMs) {
    return null;
  }

  const start = startMs === day.start ? 0 : wallMinutes(startMs);

  return {
    id: block.id,
    reason: block.reason,
    start,
    end:
      endMs === day.end
        ? MINUTES_PER_DAY
        : Math.min(start + (endMs - startMs) / 60_000, MINUTES_PER_DAY),
  };
}

/**
 * ¿El turno está dentro de un bloqueo de su agenda? Solo los estados que ocupan el horario, con la regla de la API
 * (`a.starts_at < b.ends_at` y `a.ends_at > b.starts_at`, en instantes): los que solo se tocan no cuentan.
 */
export function isInBlock(appointment: Appointment, blocks: readonly ScheduleBlock[]): boolean {
  if (!OCCUPYING_STATUSES.has(appointment.status.value)) {
    return false;
  }

  const start = ms(appointment.starts_at);
  const end = ms(appointment.ends_at);

  return blocks.some((block) => start < ms(block.ends_at) && end > ms(block.starts_at));
}

/**
 * Los carriles de los turnos de una agenda: los que se superponen (sobreturnos, o la hora repetida de un día de 25 horas)
 * van lado a lado; cada grupo de superpuestos usa los carriles que necesita.
 */
function assignLanes<T extends { start: number; end: number }>(
  items: T[],
): (T & { lane: number; lanes: number })[] {
  const sorted = [...items].sort((a, b) => a.start - b.start || b.end - a.end);
  const result: (T & { lane: number; lanes: number })[] = [];
  let group: (T & { lane: number; lanes: number })[] = [];
  let groupEnd = -Infinity;
  let laneEnds: number[] = [];

  const closeGroup = () => {
    for (const item of group) {
      item.lanes = laneEnds.length;
    }

    result.push(...group);
    group = [];
    laneEnds = [];
  };

  for (const item of sorted) {
    if (item.start >= groupEnd) {
      closeGroup();
      groupEnd = -Infinity;
    }

    let lane = laneEnds.findIndex((end) => end <= item.start);

    if (lane === -1) {
      lane = laneEnds.length;
      laneEnds.push(item.end);
    } else {
      laneEnds[lane] = item.end;
    }

    group.push({ ...item, lane, lanes: 0 });
    groupEnd = Math.max(groupEnd, item.end);
  }

  closeGroup();

  return result;
}

/** ¿Se ve el turno? Los cancelados, solo con "Mostrar los cancelados" (pregunta 5). */
export function isVisible(appointment: Appointment, showCancelled: boolean): boolean {
  return showCancelled || appointment.status.value !== 'cancelled';
}

/**
 * La grilla del día con las agendas ya elegidas (`selectColumns`) y los turnos del día. El rango va del primer tramo al
 * último de las agendas que se ven, ampliado a los turnos que quedan afuera; sin tramos, de 08:00 a 18:00 (ampliado igual).
 */
export function buildDayGrid(input: {
  day: string;
  schedules: readonly GridScheduleInput[];
  appointments: readonly Appointment[];
  showCancelled: boolean;
  /** El rango que la grilla cubre como mínimo (la semana: el de sus siete días, para que las filas coincidan). */
  minRange?: Interval;
}): DayGrid {
  const weekday = weekdayOf(input.day);
  const bounds = dayBounds(input.day);
  const visible = input.appointments.filter((appointment) =>
    isVisible(appointment, input.showCancelled),
  );
  const byColumn = new Map<number, Appointment[]>();

  for (const appointment of visible) {
    const list = byColumn.get(appointment.schedule.id) ?? [];
    list.push(appointment);
    byColumn.set(appointment.schedule.id, list);
  }

  const opens = input.schedules.map((entry) =>
    entry.hours === null ? null : openIntervals(entry.hours, weekday),
  );
  const allOpen = opens.flatMap((open) => open ?? []);
  let start: number =
    allOpen.length === 0 ? DEFAULT_RANGE.start : Math.min(...allOpen.map((o) => o.start));
  let end: number =
    allOpen.length === 0 ? DEFAULT_RANGE.end : Math.max(...allOpen.map((o) => o.end));

  const placed = input.schedules.map((entry) =>
    (byColumn.get(entry.schedule.id) ?? []).map((appointment) => ({
      appointment,
      ...appointmentMinutes(appointment, bounds.end),
    })),
  );

  for (const item of placed.flat()) {
    start = Math.min(start, item.start);
    end = Math.max(end, item.end);
  }

  if (input.minRange !== undefined) {
    start = Math.min(start, input.minRange.start);
    end = Math.max(end, input.minRange.end);
  }

  start = Math.floor(start / 60) * 60;
  end = Math.min(Math.ceil(end / 60) * 60, MINUTES_PER_DAY);

  if (end <= start) {
    end = Math.min(start + 60, MINUTES_PER_DAY);
  }

  const range = { start, end };
  const rows: number[] = [];

  for (let minute = start; minute < end; minute += ROW_MINUTES) {
    rows.push(minute);
  }

  const columns = input.schedules.map((entry, index): GridColumn => {
    const open = opens[index] ?? null;
    const blocks = entry.blocks ?? [];

    return {
      key: `s:${String(entry.schedule.id)}`,
      day: input.day,
      heading: entry.schedule.name,
      subheading: `${entry.schedule.veterinarian?.name ?? 'Sin veterinario'}${entry.schedule.is_active ? '' : ' (inactivada)'}`,
      cellLabel: entry.schedule.name,
      itemContext: entry.schedule.name,
      schedule: entry.schedule,
      closed: open === null ? [] : closedIntervals(open, range),
      blocks: blocks
        .map((block) => blockInDay(block, bounds))
        .filter((block): block is GridBlock => block !== null)
        .map((block) => ({
          ...block,
          start: Math.max(block.start, range.start),
          end: Math.min(block.end, range.end),
        }))
        .filter((block) => block.end > block.start),
      items: assignLanes(placed[index] ?? []).map((item) => ({
        ...item,
        inBlock: isInBlock(item.appointment, blocks),
      })),
    };
  });

  return { start, end, rows, columns };
}

/**
 * La semana de una agenda (3.3, pregunta 4): siete columnas, de lunes a domingo, armadas con `buildDayGrid` (la misma
 * grilla del día, sin duplicarla), con un rango de horas común a los siete días (el de cada día, unido), así las filas
 * coinciden. Cada turno va en el día de Asunción en que empieza; cada día sombrea su horario y los bloqueos que lo tocan.
 */
export function buildWeekGrid(input: {
  /** Cualquier día de la semana. */
  day: string;
  schedule: GridScheduleInput;
  appointments: readonly Appointment[];
  showCancelled: boolean;
  /** "Hoy" en Asunción, para marcar su columna. */
  today: string;
}): DayGrid {
  const days = weekDaysOf(input.day);
  const own = input.appointments.filter(
    (appointment) => appointment.schedule.id === input.schedule.schedule.id,
  );
  const byDay = new Map<string, Appointment[]>();

  for (const appointment of own) {
    const day = wallDay(appointment.starts_at);
    const list = byDay.get(day) ?? [];
    list.push(appointment);
    byDay.set(day, list);
  }

  const dayGrid = (day: string, minRange?: Interval) =>
    buildDayGrid({
      day,
      schedules: [input.schedule],
      appointments: byDay.get(day) ?? [],
      showCancelled: input.showCancelled,
      ...(minRange === undefined ? {} : { minRange }),
    });
  const first = days.map((day) => dayGrid(day));
  const range = {
    start: Math.min(...first.map((grid) => grid.start)),
    end: Math.max(...first.map((grid) => grid.end)),
  };
  const grids = days.map((day) => dayGrid(day, range));
  const columns = grids.map((grid, index): GridColumn => {
    const day = days[index] ?? input.day;
    const column = grid.columns[0];

    if (column === undefined) {
      throw new Error('La semana sin su agenda.');
    }

    const name = dayName(day);

    return {
      ...column,
      key: `d:${day}`,
      day,
      heading: shortDayName(day),
      subheading: day === input.today ? 'Hoy' : '',
      cellLabel: name,
      itemContext: `${column.schedule.name}, ${name.toLowerCase()}`,
    };
  });
  const base = grids[0];

  return {
    start: base?.start ?? range.start,
    end: base?.end ?? range.end,
    rows: base?.rows ?? [],
    columns,
  };
}

/** El filtro de las agendas que se ven (la URL y la vista por defecto del veterinario). */
export interface ColumnFilters {
  /** `agenda` de la URL: una sola agenda. */
  scheduleId: number | undefined;
  /** `veterinario` de la URL: las agendas de ese veterinario (sin las agendas sin veterinario). */
  veterinarianId: number | undefined;
  /**
   * La vista por defecto del veterinario (pregunta 3): su ficha, si es veterinario y no eligió "Ver toda la sucursal". Ve
   * las agendas de su ficha y las agendas sin veterinario, como la cola del día.
   */
  ownVeterinarianId: number | null;
}

/**
 * Las agendas de las columnas: las activas de la sucursal (en el orden de la API, por nombre) y, después, las que no están
 * entre ellas pero tienen turnos ese día (una agenda inactivada con turnos que siguen: no se pierden de la grilla), con los
 * filtros aplicados.
 */
export function selectColumns(
  active: readonly ColumnSchedule[],
  appointments: readonly Appointment[],
  filters: ColumnFilters,
): ColumnSchedule[] {
  const known = new Set(active.map((schedule) => schedule.id));
  const extra = new Map<number, ColumnSchedule>();

  for (const appointment of appointments) {
    if (!known.has(appointment.schedule.id) && !extra.has(appointment.schedule.id)) {
      extra.set(appointment.schedule.id, {
        id: appointment.schedule.id,
        name: appointment.schedule.name,
        color: null,
        veterinarian: appointment.veterinarian,
        is_active: false,
      });
    }
  }

  const all = [
    ...active,
    ...[...extra.values()].sort((a, b) => a.name.localeCompare(b.name, 'es')),
  ];

  return all.filter((schedule) => {
    if (filters.scheduleId !== undefined) {
      return schedule.id === filters.scheduleId;
    }

    if (filters.veterinarianId !== undefined) {
      return schedule.veterinarian?.id === filters.veterinarianId;
    }

    if (filters.ownVeterinarianId !== null) {
      return (
        schedule.veterinarian === null || schedule.veterinarian.id === filters.ownVeterinarianId
      );
    }

    return true;
  });
}

/** Quién es el turno: el paciente (con su dueño), el cliente o el contacto. */
export function appointmentWho(appointment: Appointment): string {
  if (appointment.patient !== null) {
    return appointment.client === null
      ? appointment.patient.name
      : `${appointment.patient.name}, de ${appointment.client.display_name}`;
  }

  if (appointment.client !== null) {
    return appointment.client.display_name;
  }

  return appointment.contact_name ?? '';
}

/** El nombre accesible de un turno: "09:00 a 09:30, Luna, de Laura Martínez, Consulta, Confirmado, Consultorio 1". */
export function appointmentLabel(item: GridItem, context: string): string {
  const { appointment } = item;
  const parts = [
    `${formatTime(appointment.starts_at)} a ${formatTime(appointment.ends_at)}`,
    appointmentWho(appointment),
    appointment.service?.name ?? '',
    appointment.status.label,
    context,
    appointment.is_overbooked ? 'sobreturno' : '',
    appointment.series_id !== null ? 'de una serie' : '',
    item.inBlock ? 'dentro de un bloqueo' : '',
    item.continuesNextDay ? 'sigue el día siguiente' : '',
  ];

  return parts.filter((part) => part !== '').join(', ');
}
