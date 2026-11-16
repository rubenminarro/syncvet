import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import { invalidateBranchQueries } from '../../lib/api/query-keys';
import { authKeys } from '../auth/api';
import type { Me } from '../auth/types';
import type {
  Branch,
  BranchDetail,
  BranchListFilters,
  BranchPayload,
  CreateBranchPayload,
  Warehouse,
  WarehousePayload,
} from './types';

/** Sucursales por página: fijo, como los demás listados. */
export const BRANCHES_PER_PAGE = 20;

/**
 * Las claves de las sucursales (diseño de la F2, "Claves de las consultas"): son de la clínica, no dependen de la sucursal
 * actual, así que empiezan con el módulo. Los depósitos no tienen clave propia: vienen en el detalle de su sucursal.
 */
export const branchKeys = {
  all: ['branches'] as const,
  lists: ['branches', 'list'] as const,
  list: (filters: BranchListFilters) => ['branches', 'list', filters] as const,
  detail: (id: number) => ['branches', 'detail', id] as const,
};

/** GET branches: 20 por página; mientras llega otra página o búsqueda, la tabla sigue con la anterior. */
export function branchListQueryOptions(filters: BranchListFilters) {
  return queryOptions({
    queryKey: branchKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<Branch>('branches', {
        signal,
        query: {
          search: filters.search,
          include_inactive: filters.include_inactive ? 1 : undefined,
          page: filters.page,
          per_page: BRANCHES_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET branches/{id}: los datos y todos sus depósitos (el por defecto primero). */
export function branchDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: branchKeys.detail(id),
    queryFn: async ({ signal }) => (await api.get<BranchDetail>(`branches/${id}`, { signal })).data,
  });
}

/** POST branches (201, ShowBranchResource con el depósito por defecto que crea la API). */
export function createBranch(payload: CreateBranchPayload) {
  return api.post<BranchDetail>('branches', payload);
}

/** PATCH branches/{id}: el formulario completo, `{ is_default: true }` o `{ is_active }` (200, con sus depósitos). */
export function updateBranch(
  id: number,
  payload: BranchPayload | { is_default: true } | { is_active: boolean },
) {
  return api.patch<BranchDetail>(`branches/${id}`, payload);
}

/** POST branches/{id}/warehouses (201). Una sucursal inactiva no suma depósitos: 422 en `branch`. */
export function createWarehouse(branchId: number, payload: WarehousePayload) {
  return api.post<Warehouse>(`branches/${branchId}/warehouses`, payload);
}

/** PATCH warehouses/{id}: el formulario completo, `{ is_default: true }` o `{ is_active }` (200). */
export function updateWarehouse(
  id: number,
  payload: WarehousePayload | { is_default: true } | { is_active: boolean },
) {
  return api.patch<Warehouse>(`warehouses/${id}`, payload);
}

/**
 * Después de cualquier cambio de una sucursal o un depósito (diseño de la F2, H5): las sucursales, los usuarios y los
 * veterinarios (muestran las sucursales) quedan viejos, y `me` se vuelve a pedir: el selector de la barra usa
 * `me.branches` y, si se inactivó la sucursal actual, `me` vuelve sin ella y el layout pide elegir otra. Si cambió la
 * sucursal actual, también las consultas que dependen de ella. El detalle toma la respuesta, si la hay, sin otro pedido.
 */
export async function afterBranchChanged(queryClient: QueryClient, saved?: BranchDetail) {
  const before = queryClient.getQueryData<Me>(authKeys.me)?.current_branch?.id ?? null;

  if (saved === undefined) {
    // Un depósito: su respuesta no trae la sucursal, así que el detalle a la vista se vuelve a pedir.
    void queryClient.invalidateQueries({ queryKey: branchKeys.all });
  } else {
    // La sucursal: el detalle toma la respuesta, sin otro pedido; los listados se piden al mostrarse.
    void queryClient.invalidateQueries({ queryKey: branchKeys.all, refetchType: 'none' });
    queryClient.setQueryData(branchKeys.detail(saved.id), saved);
  }

  void queryClient.invalidateQueries({ queryKey: ['users'] });
  void queryClient.invalidateQueries({ queryKey: ['veterinarians'] });

  await queryClient.invalidateQueries({ queryKey: authKeys.me });
  const after = queryClient.getQueryData<Me>(authKeys.me)?.current_branch?.id ?? null;

  if (before !== after) {
    await invalidateBranchQueries(queryClient);
  }
}
