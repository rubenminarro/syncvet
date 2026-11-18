import { useQuery } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import type { SelectOption } from '../../components/ui';
import type { Me } from '../auth/types';
import { branchScheduleOptionsQueryOptions } from './api';
import { isVisible, selectColumns, type ColumnSchedule } from './grid';
import type { Appointment } from './types';

/** Lo que comparten el día (3.2), la semana y la lista (3.3), sin componentes (los componentes, en `AgendaShared.tsx`). */

/** Por debajo de este ancho, el día muestra una agenda por vez, la semana es una lista por día y la lista, tarjetas. */
export const SINGLE_COLUMN_QUERY = '(max-width: 767.98px)';

/** El aviso de los turnos que no entraron en el pedido (pregunta 1: nunca corta en silencio). */
export const MISSING_APPOINTMENTS_MESSAGE = 'Hay más turnos de los que se muestran: usá la lista.';

export function countText(count: number): string {
  if (count === 0) {
    return 'sin turnos';
  }

  return count === 1 ? '1 turno' : `${String(count)} turnos`;
}

/** El nombre de una agenda en los selectores: la inactivada, con la misma marca que su columna. */
export function scheduleOptionLabel(schedule: ColumnSchedule): string {
  return schedule.is_active ? schedule.name : schedule.name + ' (inactivada)';
}

/** La ficha del veterinario para su vista por defecto (pregunta 3), o null si no es veterinario o eligió "toda la sucursal". */
export function ownVeterinarianOf(me: Me | undefined, todos: 1 | undefined): number | null {
  return me?.veterinarian !== null && me?.veterinarian !== undefined && todos !== 1
    ? me.veterinarian.id
    : null;
}

/**
 * Las agendas de la sucursal para la agenda: las activas (y las inactivadas con turnos visibles en lo pedido), las que pasan
 * los filtros de la URL y la vista por defecto del veterinario, y los veterinarios de los filtros.
 */
export function useAgendaSchedules(input: {
  branchId: number | null;
  appointments: readonly Appointment[];
  showCancelled: boolean;
  agenda: number | undefined;
  veterinario: number | undefined;
  ownVeterinarianId: number | null;
}) {
  const schedulesQuery = useQuery({
    ...branchScheduleOptionsQueryOptions(input.branchId ?? 0),
    enabled: input.branchId !== null,
  });
  const active: ColumnSchedule[] = schedulesQuery.data ?? [];
  // Las agendas inactivadas entran solo con turnos que se ven (sin los cancelados ocultos): una columna vacía confunde.
  const visibleAppointments = input.appointments.filter((appointment) =>
    isVisible(appointment, input.showCancelled),
  );
  // Las agendas que se pueden elegir en los filtros: las de la sucursal (y las inactivas con turnos), sin filtrar.
  const candidates = selectColumns(active, visibleAppointments, {
    scheduleId: undefined,
    veterinarianId: undefined,
    ownVeterinarianId: null,
  });
  const filtered = selectColumns(active, visibleAppointments, {
    scheduleId: input.agenda,
    veterinarianId: input.veterinario,
    ownVeterinarianId: input.ownVeterinarianId,
  });

  return { schedulesQuery, candidates, filtered, vetOptions: veterinarianOptions(candidates) };
}

/** "Todos" y los veterinarios de las agendas, sin repetir. */
export function veterinarianOptions(schedules: readonly ColumnSchedule[]): SelectOption[] {
  const options: SelectOption[] = [{ value: '', label: 'Todos' }];
  const seen = new Set<number>();

  for (const schedule of schedules) {
    if (schedule.veterinarian !== null && !seen.has(schedule.veterinarian.id)) {
      seen.add(schedule.veterinarian.id);
      options.push({ value: String(schedule.veterinarian.id), label: schedule.veterinarian.name });
    }
  }

  return options;
}

/**
 * El panel de solo lectura de un turno (el día, la semana y la lista): abrirlo guarda el elemento que tenía el foco y pasa
 * el foco al título del panel; cerrarlo lo devuelve.
 */
export function useAppointmentPanel<T>() {
  const [selected, setSelected] = useState<T | null>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);
  const openerRef = useRef<HTMLElement | null>(null);

  const open = (item: T) => {
    openerRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setSelected(item);
    window.setTimeout(() => headingRef.current?.focus(), 0);
  };
  const close = () => {
    setSelected(null);
    openerRef.current?.focus();
  };

  return { selected, setSelected, headingRef, open, close };
}

/**
 * Las clases de la tarjeta de un turno (la grilla, la semana en el celular y las tarjetas de la lista): los ausentes
 * atenuados, los cancelados tachados y la marca del bloqueo, más las de cada vista.
 */
export function appointmentItemClass(
  appointment: Pick<Appointment, 'status'>,
  inBlock: boolean,
  ...extra: string[]
): string {
  const status = appointment.status.value;

  return [
    'agenda-item',
    ...extra,
    status === 'no_show' ? 'agenda-item-dimmed' : '',
    status === 'cancelled' ? 'agenda-item-cancelled' : '',
    inBlock ? 'agenda-item-in-block' : '',
  ]
    .filter((name) => name !== '')
    .join(' ');
}

/** Un id de un `<select>` (vacío: ninguno). */
export function selectedId(value: string): number | undefined {
  return value === '' ? undefined : Number(value);
}
