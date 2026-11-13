import { keepPreviousData, queryOptions } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import type { Client, ClientDetail, ClientListFilters } from './types';

/** Clientes por página: fijo, sin selector ni lugar en la URL (pregunta 13 del diseño de la F1). */
export const CLIENTS_PER_PAGE = 20;

/**
 * Las claves de los clientes (frontend/CLAUDE.md §5, query-keys.ts): son de la clínica, no de la sucursal, así que empiezan
 * con el módulo y el cambio de sucursal no las toca.
 */
export const clientKeys = {
  all: ['clients'] as const,
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
