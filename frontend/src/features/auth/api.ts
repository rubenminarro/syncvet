import { queryOptions, type QueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api/client';
import type { LoginForm, PasswordForm } from './schemas';
import type { LoginResponse, Me } from './types';

export const authKeys = {
  me: ['me'] as const,
};

/** GET /api/me. Queda en la caché hasta que algo lo cambie (el login, la sucursal) o se cierre la sesión. */
export function meQueryOptions() {
  return queryOptions({
    queryKey: authKeys.me,
    queryFn: async ({ signal }) => (await api.get<Me>('me', { signal })).data,
    staleTime: Infinity,
  });
}

/** POST /api/login, sin token: sus errores van al formulario, no cierran ninguna sesión. */
export async function login(form: LoginForm): Promise<LoginResponse> {
  return (await api.post<LoginResponse>('login', form, { token: null })).data;
}

/** PATCH /api/me/current-branch: devuelve `me` con la sucursal nueva. */
export async function updateCurrentBranch(branchId: number): Promise<Me> {
  return (await api.patch<Me>('me/current-branch', { branch_id: branchId })).data;
}

/**
 * Después de cambiar la sucursal: `me` con la respuesta y todas las demás consultas invalidadas (los datos dependen de la
 * sucursal; las que están a la vista se vuelven a pedir).
 */
export async function applyBranchChange(queryClient: QueryClient, me: Me): Promise<void> {
  queryClient.setQueryData(authKeys.me, me);
  await queryClient.invalidateQueries({
    predicate: (query) => query.queryKey[0] !== authKeys.me[0],
  });
}

/** PATCH /api/me/password: la API conserva el token del pedido y revoca los demás del usuario. */
export async function updateOwnPassword(form: PasswordForm): Promise<string> {
  return (await api.patch<null>('me/password', form)).message;
}
