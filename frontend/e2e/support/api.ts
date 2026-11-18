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
    post: <T>(path: string, data: unknown) => call<T>('POST', path, data, token),
    delete: (path: string) => call<null>('DELETE', path, undefined, token),
    async dispose() {
      await call<null>('POST', 'logout', undefined, token).catch(() => undefined);
      await context.dispose();
    },
  };
}

/**
 * Crea por la API (como el administrador) una agenda de `vet-a` en la Casa central, con el horario de lunes a domingo de
 * 08:00 a 16:00, y devuelve su id (desde la 3.1b: los specs que necesitan una agenda sin pasar por su formulario).
 */
export async function createScheduleFor(name: string): Promise<number> {
  const api = await apiAs(USERS.admin);

  try {
    const branches = await api.get<{ id: number; name: string }[]>('branches?per_page=100');
    const branch = branches.find((item) => item.name === 'Casa central');
    const vets = await api.get<{ id: number; user: { name: string } }[]>(
      'veterinarians?per_page=100',
    );
    const vet = vets.find((item) => item.user.name === USERS.vet.name);

    if (branch === undefined || vet === undefined) {
      throw new Error('Sin la Casa central o sin la ficha de vet-a en syncvet_e2e.');
    }

    const schedule = await api.post<{ id: number }>('schedules', {
      branch_id: branch.id,
      name,
      veterinarian_id: vet.id,
      slot_minutes: 30,
      hours: [1, 2, 3, 4, 5, 6, 7].map((weekday) => ({
        weekday,
        starts_at: '08:00',
        ends_at: '16:00',
      })),
    });

    return schedule.id;
  } finally {
    await api.dispose();
  }
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
