import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import type { Client, ClientDetail, ClientListFilters, ClientPayload, SavedClient } from './types';

/** Clientes por página: fijo, sin selector ni lugar en la URL (pregunta 13 del diseño de la F1). */
export const CLIENTS_PER_PAGE = 20;

/**
 * Las claves de los clientes (frontend/CLAUDE.md §5, query-keys.ts): son de la clínica, no de la sucursal, así que empiezan
 * con el módulo y el cambio de sucursal no las toca.
 */
export const clientKeys = {
  all: ['clients'] as const,
  lists: ['clients', 'list'] as const,
  list: (filters: ClientListFilters) => ['clients', 'list', filters] as const,
  detail: (id: number) => ['clients', 'detail', id] as const,
};

/** GET clients: 20 por página; mientras llega otra página o búsqueda, la tabla sigue con la anterior. */
export function clientListQueryOptions(filters: ClientListFilters) {
  return queryOptions({
    queryKey: clientKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<Client>('clients', {
        signal,
        query: {
          search: filters.search,
          kind: filters.kind,
          page: filters.page,
          per_page: CLIENTS_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET clients/{id}: los datos y todos sus pacientes (también los fallecidos). */
export function clientDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: clientKeys.detail(id),
    queryFn: async ({ signal }) => (await api.get<ClientDetail>(`clients/${id}`, { signal })).data,
  });
}

/** POST clients (201, ShowClientResource sin los pacientes: un cliente nuevo no tiene). */
export function createClient(payload: ClientPayload) {
  return api.post<SavedClient>('clients', payload);
}

/** PATCH clients/{id} con el formulario completo (200, ShowClientResource con sus pacientes). */
export function updateClient(id: number, payload: ClientPayload) {
  return api.patch<SavedClient>(`clients/${id}`, payload);
}

/** DELETE clients/{id} (200, sin datos). */
export function deleteClient(id: number) {
  return api.delete<null>(`clients/${id}`);
}

/**
 * Después del alta o la edición: los listados y las demás consultas de clientes quedan viejas (la búsqueda también
 * encuentra por los datos cambiados) y el detalle toma la respuesta, sin otro pedido. Se invalidan sin volver a pedir
 * enseguida (refetchType 'none'): se piden al mostrarse. La edición invalida además el detalle de los pacientes (muestra el
 * nombre del dueño).
 */
export function afterClientSaved(
  queryClient: QueryClient,
  client: SavedClient,
  { edited }: { edited: boolean },
) {
  void queryClient.invalidateQueries({ queryKey: clientKeys.all, refetchType: 'none' });

  if (edited) {
    void queryClient.invalidateQueries({ queryKey: ['patients', 'detail'] });
  }

  queryClient.setQueryData<ClientDetail>(clientKeys.detail(client.id), {
    ...client,
    patients: client.patients ?? [],
  });
}

/**
 * Después de la baja (o de un 404 en una acción: el cliente ya no está), antes de salir de la página: los listados se
 * vacían (resetQueries; no están a la vista, así que no se vuelven a pedir hasta mostrarse) para que al volver no se vea un
 * momento el cliente borrado, y las demás consultas de clientes quedan viejas. Devuelve lo que se hace después de salir:
 * sacar el detalle de la caché (antes, su consulta todavía estaba activa y se habría vuelto a pedir).
 */
export function afterClientGone(queryClient: QueryClient, id: number): () => void {
  void queryClient.resetQueries({ queryKey: clientKeys.lists });
  void queryClient.invalidateQueries({ queryKey: clientKeys.all, refetchType: 'none' });

  return () => {
    queryClient.removeQueries({ queryKey: clientKeys.detail(id), exact: true });
  };
}
