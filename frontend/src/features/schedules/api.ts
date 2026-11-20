import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import type { Service } from '../services/types';
import type { Veterinarian } from '../veterinarians/types';
import type {
  Schedule,
  ScheduleBlock,
  ScheduleBlockPayload,
  ScheduleDetail,
  ScheduleListFilters,
  SchedulePayload,
  StoredScheduleBlock,
} from './types';

/** Agendas por página: fijo, como los demás listados. */
export const SCHEDULES_PER_PAGE = 20;

/** Los veterinarios y los servicios de los selectores del formulario: los activos de la clínica, en un pedido. */
export const OPTIONS_PER_PAGE = 100;

/**
 * Las claves de las agendas (diseño de la F3, "Claves de las consultas"): son de la clínica, no de la sucursal actual. Los
 * selectores del formulario cuelgan de su módulo (`['veterinarians', 'options']`, `['services', 'options']`), así un cambio
 * de un veterinario o de un servicio los invalida con el resto de su módulo.
 */
export const scheduleKeys = {
  all: ['schedules'] as const,
  lists: ['schedules', 'list'] as const,
  list: (filters: ScheduleListFilters) => ['schedules', 'list', filters] as const,
  detail: (id: number) => ['schedules', 'detail', id] as const,
  /** Lo de una agenda que cambia con un bloqueo: sus bloqueos y su disponibilidad (desde la 3.2). */
  ofSchedule: (id: number) => ['schedules', id] as const,
  /** Los bloqueos de la tarjeta del detalle: sin `from` ni `to` (los que terminan desde hoy), por página. */
  blocks: (id: number, filters: { from: string | null; to: string | null; page: number }) =>
    ['schedules', id, 'blocks', filters] as const,
};

/** Bloqueos por página en la tarjeta del detalle. */
export const BLOCKS_PER_PAGE = 20;

/**
 * GET schedules/{id}/blocks: sin `from` ni `to`, los que terminan desde hoy (Asunción), por `starts_at`. Mientras llega otra
 * página, la tabla sigue con la anterior.
 */
export function scheduleBlocksQueryOptions(scheduleId: number, page: number) {
  return queryOptions({
    queryKey: scheduleKeys.blocks(scheduleId, { from: null, to: null, page }),
    queryFn: ({ signal }) =>
      getPage<ScheduleBlock>(`schedules/${String(scheduleId)}/blocks`, {
        signal,
        query: { page, per_page: BLOCKS_PER_PAGE },
      }),
    placeholderData: keepPreviousData,
  });
}

/** POST schedules/{id}/blocks (201): el bloqueo, con los turnos que quedaron adentro (`affected_appointments`). */
export function createScheduleBlock(scheduleId: number, payload: ScheduleBlockPayload) {
  return api.post<StoredScheduleBlock>(`schedules/${String(scheduleId)}/blocks`, payload);
}

/** DELETE schedule-blocks/{id}: no se editan; se eliminan y se crea otro. */
export function deleteScheduleBlock(blockId: number) {
  return api.delete<null>(`schedule-blocks/${String(blockId)}`);
}

/**
 * Después de crear o eliminar un bloqueo (diseño de la F3, "Invalidaciones": un bloqueo → sus bloqueos, el detalle de la
 * agenda y la disponibilidad). El detalle a la vista no muestra `upcoming_blocks`: queda viejo, sin otro pedido.
 */
export function afterBlocksChanged(queryClient: QueryClient, scheduleId: number) {
  void queryClient.invalidateQueries({ queryKey: scheduleKeys.ofSchedule(scheduleId) });
  void queryClient.invalidateQueries({
    queryKey: scheduleKeys.detail(scheduleId),
    refetchType: 'none',
  });
}

export const scheduleOptionKeys = {
  veterinarians: ['veterinarians', 'options'] as const,
  services: ['services', 'options'] as const,
};

/** GET schedules: 20 por página; mientras llega otra página o búsqueda, la tabla sigue con la anterior. */
export function scheduleListQueryOptions(filters: ScheduleListFilters) {
  return queryOptions({
    queryKey: scheduleKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<Schedule>('schedules', {
        signal,
        query: {
          search: filters.search,
          branch_id: filters.branch_id ?? undefined,
          include_inactive: filters.include_inactive ? 1 : undefined,
          page: filters.page,
          per_page: SCHEDULES_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET schedules/{id}: con el horario semanal y los próximos bloqueos; `staleTime` de 5 minutos (diseño de la F3). */
export function scheduleDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: scheduleKeys.detail(id),
    queryFn: async ({ signal }) =>
      (await api.get<ScheduleDetail>(`schedules/${String(id)}`, { signal })).data,
    staleTime: 5 * 60 * 1000,
  });
}

/**
 * GET veterinarians?per_page=100: los activos de la clínica, cada uno con sus sucursales; el formulario muestra los de la
 * sucursal elegida con el usuario activo (sin otro pedido al cambiar de sucursal).
 */
export function veterinarianOptionsQueryOptions() {
  return queryOptions({
    queryKey: scheduleOptionKeys.veterinarians,
    queryFn: async ({ signal }) =>
      (
        await getPage<Veterinarian>('veterinarians', {
          signal,
          query: { per_page: OPTIONS_PER_PAGE },
        })
      ).items,
  });
}

/** GET services?per_page=100: los activos de la clínica. */
export function serviceOptionsQueryOptions() {
  return queryOptions({
    queryKey: scheduleOptionKeys.services,
    queryFn: async ({ signal }) =>
      (await getPage<Service>('services', { signal, query: { per_page: OPTIONS_PER_PAGE } })).items,
  });
}

/** POST schedules (201, ShowScheduleResource): queda activa. */
export function createSchedule(payload: SchedulePayload) {
  return api.post<ScheduleDetail>('schedules', payload);
}

/** PATCH schedules/{id}: el formulario completo; `hours` reemplaza el horario entero (`[]` lo vacía). */
export function updateSchedule(id: number, payload: SchedulePayload) {
  return api.patch<ScheduleDetail>(`schedules/${String(id)}`, payload);
}

/** PATCH schedules/{id} con solo `{ is_active }`: se inactiva aunque tenga turnos (los que tiene siguen). */
export function setScheduleActive(id: number, isActive: boolean) {
  return api.patch<ScheduleDetail>(`schedules/${String(id)}`, { is_active: isActive });
}

/** DELETE schedules/{id}: solo sin turnos; con turnos, 409 SCHEDULE_IN_USE. */
export function deleteSchedule(id: number) {
  return api.delete<null>(`schedules/${String(id)}`);
}

/**
 * Después de crear, editar, activar o inactivar (diseño de la F3, "Invalidaciones": una agenda → `['schedules']`): los
 * listados quedan viejos y el detalle toma la respuesta, sin otro pedido.
 */
export function afterScheduleChanged(queryClient: QueryClient, saved: ScheduleDetail) {
  void queryClient.invalidateQueries({ queryKey: scheduleKeys.all, refetchType: 'none' });
  queryClient.setQueryData(scheduleKeys.detail(saved.id), saved);
}

/**
 * Después de eliminar (o de un 404 en una acción): los listados se vacían (al volver se espera la lista nueva, sin verla un
 * momento). Devuelve lo que se hace después de salir del detalle: sacarlo de la caché.
 */
export function afterScheduleGone(queryClient: QueryClient, id: number): () => void {
  void queryClient.resetQueries({ queryKey: scheduleKeys.lists });
  void queryClient.invalidateQueries({ queryKey: scheduleKeys.all, refetchType: 'none' });

  return () => {
    queryClient.removeQueries({ queryKey: scheduleKeys.detail(id), exact: true });
  };
}
