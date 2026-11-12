import { queryOptions } from '@tanstack/react-query';
import { api } from '../../lib/api/client';
import type { LoginForm } from './schemas';
import type { LoginResponse, Me } from './types';

export const authKeys = {
  me: ['me'] as const,
};

/** GET /api/me. Queda en la caché hasta que algo lo cambie (el login, la sucursal en la 0.2b) o se cierre la sesión. */
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
