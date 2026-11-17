import { request, type APIRequestContext } from '@playwright/test';
import { BACKEND_URL } from './env';
import { demoPassword, USERS, type User } from './session';

interface Envelope<T> {
  success: boolean;
  message: string;
  data: T;
}

/**
 * La API del backend de las pruebas (syncvet_e2e, nunca la demo), con la sesión de un usuario del DemoSeeder: lo que un
 * spec crea por la API o tiene que limpiar si un paso falló (pregunta 13 del diseño de la F3). `dispose()` cierra la sesión
 * (revoca el token) y el contexto.
 */
export async function apiAs(user: User = USERS.admin) {
  const context: APIRequestContext = await request.newContext({
    baseURL: `${BACKEND_URL}/api/`,
    extraHTTPHeaders: { Accept: 'application/json' },
  });

  async function call<T>(method: string, path: string, data?: unknown, token?: string): Promise<T> {
    const response = await context.fetch(path, {
      method,
      ...(data === undefined ? {} : { data }),
      ...(token === undefined ? {} : { headers: { Authorization: `Bearer ${token}` } }),
    });
    const json = (await response.json()) as Envelope<T>;

    if (!response.ok() || !json.success) {
      throw new Error(
        `${method} /api/${path} respondió ${String(response.status())}: ${json.message}`,
      );
    }

    return json.data;
  }

  const { token } = await call<{ token: string }>('POST', 'login', {
    email: user.email,
    password: demoPassword(),
  });

  return {
    get: <T>(path: string) => call<T>('GET', path, undefined, token),
    delete: (path: string) => call<null>('DELETE', path, undefined, token),
    async dispose() {
      await call<null>('POST', 'logout', undefined, token).catch(() => undefined);
      await context.dispose();
    },
  };
}

/** Elimina las agendas cuyo nombre empieza con `prefix` (también las inactivas): la limpieza de un spec que se cortó. */
export async function deleteSchedulesNamed(prefix: string): Promise<void> {
  const api = await apiAs(USERS.admin);

  try {
    const schedules = await api.get<{ id: number; name: string }[]>(
      `schedules?include_inactive=1&per_page=100&search=${encodeURIComponent(prefix)}`,
    );

    for (const schedule of schedules.filter((item) => item.name.startsWith(prefix))) {
      await api.delete(`schedules/${String(schedule.id)}`);
    }
  } finally {
    await api.dispose();
  }
}
