import { keepPreviousData, queryOptions } from '@tanstack/react-query';
import { getPage } from '../../lib/api/client';
import { branchKey } from '../../lib/api/query-keys';
import type { Schedule, ScheduleBlock } from '../schedules/types';
import type { Appointment } from './types';

/**
 * Los turnos de la agenda por pedido (front-03, pregunta 1): hasta 500, el máximo de GET appointments. Si
 * `meta.pagination.total` supera lo traído, la pantalla avisa que faltan turnos (nunca corta en silencio).
 */
export const RANGE_PER_PAGE = 500;

/** Cada cuánto se vuelve a pedir la agenda con la pestaña visible y sin un diálogo abierto (pregunta 9). */
export const AUTO_REFRESH_MS = 60_000;

/** Cuánto vale lo traído antes de pedirlo otra vez al volver (diseño de la F3, "Actualización"). */
export const AGENDA_STALE_MS = 30_000;

/** Las agendas de una sucursal, para las columnas y los filtros: las activas, en un pedido (son pocas por sucursal). */
export const SCHEDULE_OPTIONS_PER_PAGE = 100;

/** Los bloqueos de una agenda en el día: en un pedido. */
export const DAY_BLOCKS_PER_PAGE = 100;

/**
 * Las claves de la agenda (diseño de la F3, "Claves de las consultas"): los turnos del rango llevan la sucursal que
 * muestran (`branchKey`, D3): el cambio de sucursal los invalida. Las agendas y sus bloqueos son de la clínica.
 */
export const appointmentKeys = {
  range: (branchId: number | null, range: { from: string; to: string }) =>
    branchKey(branchId, 'appointments', 'range', range),
};

export const agendaScheduleKeys = {
  options: (branchId: number) => ['schedules', 'options', { branch_id: branchId }] as const,
  dayBlocks: (scheduleId: number, day: string) =>
    ['schedules', scheduleId, 'blocks', { from: day, to: day, page: 1 }] as const,
};

/** Lo que devuelve el pedido del rango: los turnos y cuántos hay en total (el aviso de los que faltan). */
export interface AppointmentRange {
  items: Appointment[];
  total: number;
}

/** El intervalo de la actualización: 60 s, o ninguno en pausa (un diálogo abierto o la pestaña oculta). */
export function refreshInterval(paused: boolean): number | false {
  return paused ? false : AUTO_REFRESH_MS;
}

/**
 * GET appointments?branch_id=&from=&to=&per_page=500: todos los estados (también los cancelados: el filtro va en el
 * navegador, H2). `from` y `to` como fechas solas (la API las toma como días enteros de Asunción, también uno de 23 o 25
 * horas). Al cambiar de día, la grilla sigue con el anterior hasta que llega el nuevo (una respuesta vieja no pisa la nueva:
 * cada día tiene su clave).
 */
export function appointmentRangeQueryOptions(
  branchId: number,
  range: { from: string; to: string },
  paused: boolean,
) {
  return queryOptions({
    queryKey: appointmentKeys.range(branchId, range),
    queryFn: async ({ signal }): Promise<AppointmentRange> => {
      const page = await getPage<Appointment>('appointments', {
        signal,
        query: { branch_id: branchId, from: range.from, to: range.to, per_page: RANGE_PER_PAGE },
      });

      return { items: page.items, total: page.pagination.total };
    },
    staleTime: AGENDA_STALE_MS,
    refetchInterval: refreshInterval(paused),
    refetchIntervalInBackground: false,
    placeholderData: keepPreviousData,
  });
}

/** GET schedules?branch_id=&per_page=100: las activas de la sucursal (por nombre). */
export function branchScheduleOptionsQueryOptions(branchId: number) {
  return queryOptions({
    queryKey: agendaScheduleKeys.options(branchId),
    queryFn: async ({ signal }) =>
      (
        await getPage<Schedule>('schedules', {
          signal,
          query: { branch_id: branchId, per_page: SCHEDULE_OPTIONS_PER_PAGE },
        })
      ).items,
    staleTime: 5 * 60 * 1000,
  });
}

/** GET schedules/{id}/blocks?from=<día>&to=<día>: los bloqueos que tocan el día (para el sombreado y la marca). */
export function dayBlocksQueryOptions(scheduleId: number, day: string, paused: boolean) {
  return queryOptions({
    queryKey: agendaScheduleKeys.dayBlocks(scheduleId, day),
    queryFn: async ({ signal }) =>
      (
        await getPage<ScheduleBlock>(`schedules/${String(scheduleId)}/blocks`, {
          signal,
          query: { from: day, to: day, per_page: DAY_BLOCKS_PER_PAGE },
        })
      ).items,
    staleTime: AGENDA_STALE_MS,
    refetchInterval: refreshInterval(paused),
    refetchIntervalInBackground: false,
  });
}
