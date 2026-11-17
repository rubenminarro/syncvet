import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import { authKeys } from '../auth/api';
import type { Me } from '../auth/types';
import { userKeys } from '../users/api';
import type {
  CreateVeterinarianPayload,
  Veterinarian,
  VeterinarianDetail,
  VeterinarianListFilters,
  VeterinarianPayload,
  VeterinarianStatus,
} from './types';

/** Veterinarios por página: fijo, como los demás listados. */
export const VETERINARIANS_PER_PAGE = 20;

/** Las claves de los veterinarios (diseño de la F2, "Claves de las consultas"): son de la clínica, no de la sucursal. */
export const veterinarianKeys = {
  all: ['veterinarians'] as const,
  lists: ['veterinarians', 'list'] as const,
  list: (filters: VeterinarianListFilters) => ['veterinarians', 'list', filters] as const,
  detail: (id: number) => ['veterinarians', 'detail', id] as const,
};

/** GET veterinarians: 20 por página; mientras llega otra página o búsqueda, la tabla sigue con la anterior. */
export function veterinarianListQueryOptions(filters: VeterinarianListFilters) {
  return queryOptions({
    queryKey: veterinarianKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<Veterinarian>('veterinarians', {
        signal,
        query: {
          search: filters.search,
          status: filters.status ?? undefined,
          include_inactive: filters.include_inactive ? 1 : undefined,
          branch_id: filters.branch_id ?? undefined,
          page: filters.page,
          per_page: VETERINARIANS_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET veterinarians/{id} (solo el administrador): con la comisión, las notas y las fechas. */
export function veterinarianDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: veterinarianKeys.detail(id),
    queryFn: async ({ signal }) =>
      (await api.get<VeterinarianDetail>(`veterinarians/${id}`, { signal })).data,
  });
}

/**
 * POST veterinarians (201, ShowVeterinarianResource): el código `VET-` lo pone la API. Si el usuario tenía una ficha
 * eliminada, la restaura con el mismo id y el mismo código (P6), también con 201.
 */
export function createVeterinarian(payload: CreateVeterinarianPayload) {
  return api.post<VeterinarianDetail>('veterinarians', payload);
}

/** PATCH veterinarians/{id}: los datos de la ficha, sin `user_id` ni `code` (los dos son `prohibited`). */
export function updateVeterinarian(id: number, payload: VeterinarianPayload) {
  return api.patch<VeterinarianDetail>(`veterinarians/${id}`, payload);
}

/** PATCH veterinarians/{id} con solo `{ status }`: se inactiva aunque esté en uso. */
export function setVeterinarianStatus(id: number, status: VeterinarianStatus) {
  return api.patch<VeterinarianDetail>(`veterinarians/${id}`, { status });
}

/** DELETE veterinarians/{id}: solo sin uso; con uso, 409 VETERINARIAN_IN_USE. */
export function deleteVeterinarian(id: number) {
  return api.delete<null>(`veterinarians/${id}`);
}

/** Si la ficha es del usuario de la sesión, `me` (con su `veterinarian`) se vuelve a pedir. */
function refreshMeIfOwn(queryClient: QueryClient, userId: number) {
  if (queryClient.getQueryData<Me>(authKeys.me)?.id === userId) {
    void queryClient.invalidateQueries({ queryKey: authKeys.me });
  }
}

/**
 * Después de crear, editar, activar o inactivar una ficha (diseño de la F2, "Invalidaciones"): los veterinarios quedan
 * viejos y el detalle toma la respuesta, sin otro pedido; los usuarios también (la ficha está en su detalle y, desde
 * front-02, en el listado y en el selector del alta), y `me` si la ficha es del usuario de la sesión.
 */
export function afterVeterinarianChanged(queryClient: QueryClient, saved: VeterinarianDetail) {
  void queryClient.invalidateQueries({ queryKey: veterinarianKeys.all, refetchType: 'none' });
  queryClient.setQueryData(veterinarianKeys.detail(saved.id), saved);
  void queryClient.invalidateQueries({ queryKey: userKeys.all });
  refreshMeIfOwn(queryClient, saved.user.id);
}

/**
 * Después de eliminar una ficha: los listados se vacían (al volver se espera la lista nueva, sin verla un momento), los
 * usuarios quedan viejos (su ficha pasa a `null`) y `me` si es la propia. Devuelve lo que se hace después de salir del
 * detalle: sacarlo de la caché.
 */
export function afterVeterinarianGone(
  queryClient: QueryClient,
  veterinarian: Pick<Veterinarian, 'id' | 'user'>,
): () => void {
  void queryClient.resetQueries({ queryKey: veterinarianKeys.lists });
  void queryClient.invalidateQueries({ queryKey: veterinarianKeys.all, refetchType: 'none' });
  void queryClient.invalidateQueries({ queryKey: userKeys.all });
  refreshMeIfOwn(queryClient, veterinarian.user.id);

  return () => {
    queryClient.removeQueries({ queryKey: veterinarianKeys.detail(veterinarian.id), exact: true });
  };
}
