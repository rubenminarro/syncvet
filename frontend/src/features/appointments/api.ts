import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import type { Page } from '../../lib/api/types';
import { branchKey, BRANCH_SCOPE } from '../../lib/api/query-keys';
import type { Patient } from '../patients/types';
import type { Schedule, ScheduleBlock } from '../schedules/types';
import type {
  Appointment,
  AppointmentDetail,
  AppointmentPayload,
  AppointmentUpdatePayload,
  Availability,
  QueueAppointment,
  QueueMeta,
} from './types';

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

/** Los bloqueos de una agenda en el día, la semana o el rango de la lista: en un pedido. */
export const RANGE_BLOCKS_PER_PAGE = 100;

/** Los turnos por página de la lista (/turnos, 3.3), como los demás listados. */
export const LIST_PER_PAGE = 20;

/**
 * Las claves de la agenda (diseño de la F3, "Claves de las consultas"): los turnos del rango llevan la sucursal que
 * muestran (`branchKey`, D3): el cambio de sucursal los invalida. Las agendas y sus bloqueos son de la clínica.
 */
export const appointmentKeys = {
  range: (branchId: number | null, range: { from: string; to: string }) =>
    branchKey(branchId, 'appointments', 'range', range),
  list: (branchId: number | null, filters: AppointmentListFilters) =>
    branchKey(branchId, 'appointments', 'list', filters),
  /** La cola del día (3.7, D4): con todos sus filtros, también los que la pantalla no usa (null). */
  queue: (branchId: number | null, filters: QueueFilters) =>
    branchKey(branchId, 'appointments', 'queue', filters),
};

/**
 * Los filtros de GET appointments/queue (D4): el día de Asunción, `all` (toda la sucursal, para quien tiene ficha), el
 * veterinario y la agenda.
 */
export interface QueueFilters {
  date: string;
  all: boolean;
  veterinarian_id: number | null;
  schedule_id: number | null;
}

/** Lo que devuelve la cola: los turnos, en el orden de la API (`starts_at` e id), y su `meta`. */
export interface AppointmentQueue {
  items: QueueAppointment[];
  meta: QueueMeta;
}

export const agendaScheduleKeys = {
  options: (branchId: number) => ['schedules', 'options', { branch_id: branchId }] as const,
  rangeBlocks: (scheduleId: number, range: { from: string; to: string }) =>
    ['schedules', scheduleId, 'blocks', { from: range.from, to: range.to, page: 1 }] as const,
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

/**
 * GET appointments/queue?date=&all=1 (3.7): la cola del día de la **sucursal actual del usuario** (la API no recibe la
 * sucursal: la clave la lleva con `branchKey`, así el cambio de sucursal la invalida y no se mezclan dos). Sin paginar (hasta
 * 200, `meta.truncated`). Se actualiza como la agenda (cada 60 s, con la pausa) y, además, al volver a la pestaña si pasaron
 * más de 30 s (`refetchOnWindowFocus`, que el cliente apaga por defecto). `all` va solo si es true (sin él, la API da la
 * vista por defecto).
 */
export function appointmentQueueQueryOptions(
  branchId: number,
  filters: QueueFilters,
  paused: boolean,
) {
  return queryOptions({
    queryKey: appointmentKeys.queue(branchId, filters),
    queryFn: async ({ signal }): Promise<AppointmentQueue> => {
      const response = await api.get<QueueAppointment[]>('appointments/queue', {
        signal,
        query: {
          date: filters.date,
          all: filters.all ? 1 : null,
          veterinarian_id: filters.veterinarian_id,
          schedule_id: filters.schedule_id,
        },
      });
      const meta = (response.meta ?? {}) as Partial<QueueMeta>;

      return {
        items: response.data,
        meta: {
          date: meta.date ?? filters.date,
          branch_id: meta.branch_id ?? branchId,
          veterinarian_id: meta.veterinarian_id ?? null,
          includes_unassigned: meta.includes_unassigned === true,
          truncated: meta.truncated === true,
        },
      };
    },
    staleTime: AGENDA_STALE_MS,
    refetchInterval: refreshInterval(paused),
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: true,
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

/**
 * GET schedules/{id}/blocks?from=&to=: los bloqueos que tocan el día, la semana o el rango de la lista (para el sombreado y la
 * marca "Dentro de un bloqueo"). La API no limita el rango de los bloqueos.
 */
export function rangeBlocksQueryOptions(
  scheduleId: number,
  range: { from: string; to: string },
  paused: boolean,
) {
  return queryOptions({
    queryKey: agendaScheduleKeys.rangeBlocks(scheduleId, range),
    queryFn: async ({ signal }) =>
      (
        await getPage<ScheduleBlock>(`schedules/${String(scheduleId)}/blocks`, {
          signal,
          query: { from: range.from, to: range.to, per_page: RANGE_BLOCKS_PER_PAGE },
        })
      ).items,
    staleTime: AGENDA_STALE_MS,
    refetchInterval: refreshInterval(paused),
    refetchIntervalInBackground: false,
  });
}

/** Los filtros de la lista (/turnos) con los nombres de la API; `null` o una lista vacía: sin ese filtro. */
export interface AppointmentListFilters {
  from: string | null;
  to: string | null;
  schedule_id: number | null;
  veterinarian_id: number | null;
  /** Los valores de la API (`confirmed`, `checked_in`…), mandados como `status[]` (H5). */
  status: readonly string[];
  search: string;
  page: number;
  /** La serie (3.6, `?serie=`): con ella no van `from`, `to` ni `branch_id` (la serie entera, front-04). */
  series_id: string | null;
}

/**
 * GET appointments?branch_id=<actual>&from&to&schedule_id&veterinarian_id&status[]&search&page&per_page=20: la lista, con los
 * filtros de la API (H2) y su paginación. Se actualiza como la agenda (cada 60 s, con la pausa) y, al cambiar de filtro o de
 * página, sigue con la anterior hasta que llega la nueva. Con la serie (3.6), sin rango ni sucursal: la serie entera, aunque
 * sea de otra sucursal (un turno se abre desde cualquiera; la clave sigue con `branchKey`, que la invalida como las demás).
 */
export function appointmentListQueryOptions(
  branchId: number,
  filters: AppointmentListFilters,
  paused: boolean,
) {
  return queryOptions({
    queryKey: appointmentKeys.list(branchId, filters),
    queryFn: ({ signal }): Promise<Page<Appointment>> =>
      getPage<Appointment>('appointments', {
        signal,
        query: {
          ...(filters.series_id === null
            ? { branch_id: branchId, from: filters.from, to: filters.to }
            : { series_id: filters.series_id }),
          schedule_id: filters.schedule_id,
          veterinarian_id: filters.veterinarian_id,
          status: filters.status,
          search: filters.search,
          page: filters.page,
          per_page: LIST_PER_PAGE,
        },
      }),
    staleTime: AGENDA_STALE_MS,
    refetchInterval: refreshInterval(paused),
    refetchIntervalInBackground: false,
    placeholderData: keepPreviousData,
  });
}

/**
 * Las claves del turno (3.4): el detalle no lleva `branchKey` (un turno se abre desde cualquier sucursal); la disponibilidad
 * cuelga de su agenda (`['schedules', id, …]`), así un bloqueo nuevo también la invalida.
 */
export const appointmentDetailKeys = {
  detail: (id: number) => ['appointments', 'detail', id] as const,
  availability: (
    scheduleId: number,
    filters: { from: string; to: string; service_id: number | null; duration_minutes: number },
  ) => ['schedules', scheduleId, 'availability', filters] as const,
  patientOptions: (search: string) => ['patients', 'options', search] as const,
};

/** Los pacientes del buscador del turno: los 10 primeros (`GET patients?search=`, que también busca por el dueño). */
export const PATIENT_OPTIONS_PER_PAGE = 10;

/** GET appointments/{id}: el detalle (las horas de cada estado, quién lo creó). */
export function appointmentDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: appointmentDetailKeys.detail(id),
    queryFn: async ({ signal }) =>
      (await api.get<AppointmentDetail>(`appointments/${String(id)}`, { signal })).data,
  });
}

/**
 * GET schedules/{id}/availability?from=&to=&service_id=&duration_minutes=: los horarios libres de un día, `staleTime` de 0
 * (otra PC pudo dar uno hace un segundo). La agenda o el servicio inactivos responden 422 (`schedule`, `service_id`).
 */
export function availabilityQueryOptions(
  scheduleId: number,
  day: string,
  serviceId: number | null,
  durationMinutes: number,
) {
  const filters = { from: day, to: day, service_id: serviceId, duration_minutes: durationMinutes };

  return queryOptions({
    queryKey: appointmentDetailKeys.availability(scheduleId, filters),
    queryFn: async ({ signal }) =>
      (
        await api.get<Availability>(`schedules/${String(scheduleId)}/availability`, {
          signal,
          query: filters,
        })
      ).data,
    staleTime: 0,
    retry: false,
  });
}

/** GET patients?search=&per_page=10 (sin los fallecidos: la API no les da turnos). */
export function patientOptionsQueryOptions(search: string) {
  return queryOptions({
    queryKey: appointmentDetailKeys.patientOptions(search),
    queryFn: async ({ signal }) =>
      (
        await getPage<Patient>('patients', {
          signal,
          query: { search, per_page: PATIENT_OPTIONS_PER_PAGE },
        })
      ).items,
    placeholderData: keepPreviousData,
  });
}

export function createAppointment(payload: AppointmentPayload) {
  return api.post<AppointmentDetail>('appointments', payload);
}

/**
 * POST appointments con `recurrence` (3.6): 201 con la lista de los turnos (`AppointmentResource`), "Serie de N turnos creada
 * correctamente." y `meta.series_id`; si alguna fecha no entra, 409 `APPOINTMENT_SERIES_CONFLICT` y no se crea ninguna.
 */
export function createAppointmentSeries(payload: AppointmentPayload) {
  return api.post<Appointment[]>('appointments', payload);
}

/** Los turnos de una serie en un pedido: tiene hasta 52 (`AppointmentRecurrence::MAX_COUNT`). */
export const SERIES_PER_PAGE = 100;

/** La serie de un turno (3.6): sin `branchKey`, como el detalle (un turno se abre desde cualquier sucursal). */
export const appointmentSeriesKeys = {
  series: (seriesId: string) => ['appointments', 'series', seriesId] as const,
};

/**
 * GET appointments?series_id=&per_page=100, **sin `from` ni `to`**: la serie entera, de cualquier estado y sin el tope de 31
 * días (front-04, pregunta 16). De ahí salen "Turno 3 de 8" y la cuenta de la confirmación de cancelar.
 */
export function appointmentSeriesQueryOptions(seriesId: string) {
  return queryOptions({
    queryKey: appointmentSeriesKeys.series(seriesId),
    queryFn: async ({ signal }) =>
      (
        await getPage<Appointment>('appointments', {
          signal,
          query: { series_id: seriesId, per_page: SERIES_PER_PAGE },
        })
      ).items,
    staleTime: AGENDA_STALE_MS,
  });
}

/**
 * Después del 201 de una serie: la agenda del día, la semana, la lista y la cola (las que se ven) y la disponibilidad de su
 * agenda. No hay detalle que fijar: se va al primer turno, que se pide.
 */
export function afterSeriesCreated(queryClient: QueryClient, scheduleId: number) {
  invalidateBranchAppointments(queryClient);
  void queryClient.invalidateQueries({ queryKey: ['schedules', scheduleId, 'availability'] });
}

export function updateAppointment(id: number, payload: AppointmentUpdatePayload) {
  return api.patch<AppointmentDetail>(`appointments/${String(id)}`, payload);
}

/**
 * POST appointments/{id}/<acción> (3.5): responde 200 con el detalle y el aviso ("Turno confirmado."). Cancelar manda el
 * motivo y, desde la 3.6, el alcance (`scope`) **solo en un turno de una serie** (en uno sin serie no cambia nada y no va);
 * deshacer el ausente, `overbook: true` solo después del 409 y con el permiso.
 */
export function runAppointmentTransition(
  id: number,
  path: string,
  body: {
    cancellation_reason?: string;
    overbook?: true;
    scope?: 'single' | 'following' | 'series';
  } = {},
) {
  return api.post<AppointmentDetail>(`appointments/${String(id)}/${path}`, body);
}

/**
 * Después de una acción rechazada (el turno cambió en otra PC: un 409 o un 422 de negocio): el detalle, su serie y la agenda
 * del día, la semana, la lista y la cola se vuelven a pedir (las que se ven), para mostrar el estado actual.
 */
export function refreshAppointment(queryClient: QueryClient, id: number, seriesId?: string | null) {
  void queryClient.invalidateQueries({ queryKey: appointmentDetailKeys.detail(id) });
  invalidateSeries(queryClient, seriesId);
  invalidateBranchAppointments(queryClient);
}

/** La serie del turno, si tiene (la cuenta del alcance y "Turno 3 de 8"); los demás turnos de la serie pudieron cambiar. */
function invalidateSeries(queryClient: QueryClient, seriesId: string | null | undefined) {
  if (seriesId !== null && seriesId !== undefined) {
    void queryClient.invalidateQueries({ queryKey: appointmentSeriesKeys.series(seriesId) });
  }
}

/**
 * Después de cancelar con "Este y los siguientes" o "Toda la serie" (revisión de la 3.6): la API cambió también otros turnos de
 * la serie, así que sus detalles en caché (y solo esos: los de la misma `series_id`, salvo el pedido, que ya llegó) se vuelven a
 * pedir. Sin esto, "Turno 4 de 8" seguía mostrándose confirmado hasta vencer la caché.
 */
export function invalidateSeriesDetails(
  queryClient: QueryClient,
  seriesId: string,
  exceptId: number,
) {
  void queryClient.invalidateQueries({
    queryKey: ['appointments', 'detail'],
    predicate: (query) => {
      const data = query.state.data as Pick<AppointmentDetail, 'id' | 'series_id'> | undefined;

      return data?.series_id === seriesId && data.id !== exceptId;
    },
  });
}

/** ¿Es una consulta de turnos de una sucursal (la agenda del día o de la semana, la lista o la cola)? */
function isBranchAppointmentsKey(queryKey: readonly unknown[]): boolean {
  return queryKey[0] === BRANCH_SCOPE && queryKey[2] === 'appointments';
}

/** El día, la semana, la lista y la cola de cualquier sucursal: solo las que se ven se piden de nuevo. */
function invalidateBranchAppointments(queryClient: QueryClient) {
  void queryClient.invalidateQueries({
    predicate: (query) => isBranchAppointmentsKey(query.queryKey),
    refetchType: 'active',
  });
}

/**
 * Después de un alta, una edición o una reprogramación (diseño de la F3, "Invalidaciones"; nunca toda la caché): el detalle,
 * con la respuesta; la agenda del día, la semana, la lista y la cola (las que se ven se piden de nuevo); la disponibilidad
 * de la agenda de origen y la de destino.
 */
export function afterAppointmentSaved(
  queryClient: QueryClient,
  saved: AppointmentDetail,
  previousScheduleId?: number,
) {
  queryClient.setQueryData(appointmentDetailKeys.detail(saved.id), saved);
  invalidateSeries(queryClient, saved.series_id);
  invalidateBranchAppointments(queryClient);

  for (const scheduleId of new Set([saved.schedule.id, previousScheduleId ?? saved.schedule.id])) {
    void queryClient.invalidateQueries({ queryKey: ['schedules', scheduleId, 'availability'] });
  }
}
