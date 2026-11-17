import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import type {
  Service,
  ServiceDetail,
  ServiceListFilters,
  ServicePayload,
  ServiceStatus,
} from './types';

/** Servicios por página: fijo, como los demás listados. */
export const SERVICES_PER_PAGE = 20;

/** Las claves de los servicios (diseño de la F2, "Claves de las consultas"): son de la clínica, no de la sucursal. */
export const serviceKeys = {
  all: ['services'] as const,
  lists: ['services', 'list'] as const,
  list: (filters: ServiceListFilters) => ['services', 'list', filters] as const,
  detail: (id: number) => ['services', 'detail', id] as const,
};

/** GET services: 20 por página; mientras llega otra página o búsqueda, la tabla sigue con la anterior. */
export function serviceListQueryOptions(filters: ServiceListFilters) {
  return queryOptions({
    queryKey: serviceKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<Service>('services', {
        signal,
        query: {
          search: filters.search,
          category: filters.category ?? undefined,
          status: filters.status ?? undefined,
          include_inactive: filters.include_inactive ? 1 : undefined,
          page: filters.page,
          per_page: SERVICES_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET services/{id} (los tres roles): con la descripción y las fechas. */
export function serviceDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: serviceKeys.detail(id),
    queryFn: async ({ signal }) =>
      (await api.get<ServiceDetail>(`services/${id}`, { signal })).data,
  });
}

/** POST services (201, ShowServiceResource): el código `SRV-` lo pone la API. */
export function createService(payload: ServicePayload) {
  return api.post<ServiceDetail>('services', payload);
}

/** PATCH services/{id}: el formulario completo, sin `code` (`prohibited`) ni `status` (su botón). */
export function updateService(id: number, payload: ServicePayload) {
  return api.patch<ServiceDetail>(`services/${id}`, payload);
}

/** PATCH services/{id} con solo `{ status }`: se inactiva aunque esté usado. */
export function setServiceStatus(id: number, status: ServiceStatus) {
  return api.patch<ServiceDetail>(`services/${id}`, { status });
}

/** DELETE services/{id}: solo sin uso; con uso, 409 SERVICE_IN_USE. */
export function deleteService(id: number) {
  return api.delete<null>(`services/${id}`);
}

/**
 * Después de crear, editar, activar o inactivar (diseño de la F2, "Invalidaciones": servicios → `['services']`): los listados
 * quedan viejos y el detalle toma la respuesta, sin otro pedido.
 */
export function afterServiceChanged(queryClient: QueryClient, saved: ServiceDetail) {
  void queryClient.invalidateQueries({ queryKey: serviceKeys.all, refetchType: 'none' });
  queryClient.setQueryData(serviceKeys.detail(saved.id), saved);
}

/**
 * Después de eliminar (o de un 404 en una acción): los listados se vacían (al volver se espera la lista nueva, sin verla un
 * momento). Devuelve lo que se hace después de salir del detalle: sacarlo de la caché.
 */
export function afterServiceGone(queryClient: QueryClient, id: number): () => void {
  void queryClient.resetQueries({ queryKey: serviceKeys.lists });
  void queryClient.invalidateQueries({ queryKey: serviceKeys.all, refetchType: 'none' });

  return () => {
    queryClient.removeQueries({ queryKey: serviceKeys.detail(id), exact: true });
  };
}
