import { queryOptions, type QueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api/client';
import { invalidateBranchQueries } from '../../lib/api/query-keys';
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

/** PATCH /api/me/current-branch (su respuesta no se usa: ver applyBranchChange). */
export async function updateCurrentBranch(branchId: number): Promise<void> {
  await api.patch<Me>('me/current-branch', { branch_id: branchId });
}

/**
 * Después de cambiar la sucursal (en esta pestaña o en otra): `me` se vuelve a pedir al servidor, en lugar de tomar la
 * respuesta del PATCH, así una pestaña que cambió a la vez que otra termina con la sucursal que quedó guardada (B5 de la
 * revisión de la 0.2b). Después se invalidan solo las consultas que dependen de la sucursal (query-keys.ts).
 */
export async function applyBranchChange(queryClient: QueryClient): Promise<Me> {
  const me = await queryClient.query({ ...meQueryOptions(), staleTime: 0 });
  await invalidateBranchQueries(queryClient);

  return me;
}

/**
 * PATCH /api/me/password con la actual y la nueva (la repetición se controla solo en el navegador: la API no la pide). La
 * API conserva el token del pedido y revoca los demás del usuario.
 */
export async function updateOwnPassword(form: PasswordForm): Promise<string> {
  const body = { current_password: form.current_password, password: form.password };

  return (await api.patch<null>('me/password', body)).message;
}
