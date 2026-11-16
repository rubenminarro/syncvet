import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import { invalidateBranchQueries } from '../../lib/api/query-keys';
import { authKeys } from '../auth/api';
import type { Me } from '../auth/types';
import type {
  CreateUserPayload,
  Role,
  User,
  UserDetail,
  UserListFilters,
  UserPayload,
} from './types';

/** Usuarios por página: fijo, como los demás listados. */
export const USERS_PER_PAGE = 20;

/**
 * Las claves de los usuarios (diseño de la F2, "Claves de las consultas"): son de la clínica, no dependen de la sucursal
 * actual. `['roles']` es una lista fija.
 */
export const userKeys = {
  all: ['users'] as const,
  lists: ['users', 'list'] as const,
  list: (filters: UserListFilters) => ['users', 'list', filters] as const,
  detail: (id: number) => ['users', 'detail', id] as const,
  roles: ['roles'] as const,
};

/** GET users: 20 por página; mientras llega otra página o búsqueda, la tabla sigue con la anterior. */
export function userListQueryOptions(filters: UserListFilters) {
  return queryOptions({
    queryKey: userKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<User>('users', {
        signal,
        query: {
          search: filters.search,
          role: filters.role ?? undefined,
          branch_id: filters.branch_id ?? undefined,
          include_inactive: filters.include_inactive ? 1 : undefined,
          page: filters.page,
          per_page: USERS_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET users/{id}: los datos, los roles, las sucursales y la ficha de veterinario. */
export function userDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: userKeys.detail(id),
    queryFn: async ({ signal }) => (await api.get<UserDetail>(`users/${id}`, { signal })).data,
  });
}

/** GET roles (sin paginar): los tres roles con su etiqueta; una lista fija, no se vuelve a pedir. */
export function rolesQueryOptions() {
  return queryOptions({
    queryKey: userKeys.roles,
    queryFn: async ({ signal }) => (await api.get<Role[]>('roles', { signal })).data,
    staleTime: Infinity,
  });
}

/** POST users (201, ShowUserResource). */
export function createUser(payload: CreateUserPayload) {
  return api.post<UserDetail>('users', payload);
}

/** PATCH users/{id}: el formulario completo, sin `password` si no se cambia (200, ShowUserResource). */
export function updateUser(id: number, payload: UserPayload) {
  return api.patch<UserDetail>(`users/${id}`, payload);
}

/**
 * PATCH users/{id} con solo `{ is_active }` (2.2b): inactivar revoca todos sus tokens; reactivar no los devuelve
 * (backend/CLAUDE.md §6.5).
 */
export function setUserActive(id: number, isActive: boolean) {
  return api.patch<UserDetail>(`users/${id}`, { is_active: isActive });
}

/** DELETE users/{id} (2.2b): solo sin uso; con uso, 409 USER_IN_USE. */
export function deleteUser(id: number) {
  return api.delete<null>(`users/${id}`);
}

/**
 * Después de eliminar un usuario (2.2b): los listados se vacían (al volver se espera la lista nueva, sin verlo un momento),
 * el resto de los usuarios y los veterinarios quedan viejos. Devuelve lo que se hace después de salir del detalle: sacarlo
 * de la caché.
 */
export function afterUserGone(queryClient: QueryClient, id: number): () => void {
  void queryClient.resetQueries({ queryKey: userKeys.lists });
  void queryClient.invalidateQueries({ queryKey: userKeys.all, refetchType: 'none' });
  void queryClient.invalidateQueries({ queryKey: ['veterinarians'] });

  return () => {
    queryClient.removeQueries({ queryKey: userKeys.detail(id), exact: true });
  };
}

/**
 * Después de guardar un usuario (diseño de la F2, H5): los usuarios y los veterinarios (muestran el nombre) quedan viejos;
 * el detalle toma la respuesta, sin otro pedido. Si es el usuario de la sesión, `me` se vuelve a pedir (sus roles cambian
 * el menú y los permisos, y sus sucursales, el selector de la barra) y, si cambió su sucursal actual, también las consultas
 * que dependen de ella.
 */
export async function afterUserChanged(queryClient: QueryClient, saved: UserDetail) {
  void queryClient.invalidateQueries({ queryKey: userKeys.all, refetchType: 'none' });
  queryClient.setQueryData(userKeys.detail(saved.id), saved);
  void queryClient.invalidateQueries({ queryKey: ['veterinarians'] });

  const me = queryClient.getQueryData<Me>(authKeys.me);

  if (me?.id !== saved.id) {
    return;
  }

  const before = me.current_branch?.id ?? null;
  await queryClient.invalidateQueries({ queryKey: authKeys.me });
  const after = queryClient.getQueryData<Me>(authKeys.me)?.current_branch?.id ?? null;

  if (before !== after) {
    await invalidateBranchQueries(queryClient);
  }
}
