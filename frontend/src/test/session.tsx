import { createMemoryHistory } from '@tanstack/react-router';
import { render } from '@testing-library/react';
import { http } from 'msw';
import { afterEach } from 'vitest';
import { App } from '../app/App';
import type { Me } from '../features/auth/types';
import { createQueryClient } from '../lib/api/query';
import { createMemoryChannelHub } from '../lib/auth/channel';
import { SessionManager } from '../lib/auth/session';
import { saveSession, type SessionStorageLike, type StoredSession } from '../lib/auth/storage';
import { success } from './api';
import { server } from './server';

/** Lo que devuelve GET /api/me (MeResource) para los tests. */
export const ME: Me = {
  id: 3,
  first_name: 'Ana',
  last_name: 'Gómez',
  name: 'Ana Gómez',
  email: 'admin-a@example.com',
  clinic: { id: 1, name: 'Veterinaria Central', has_logo: false },
  current_branch: { id: 10, code: 'CEN', name: 'Casa central' },
  branches: [{ id: 10, code: 'CEN', name: 'Casa central' }],
  roles: ['administrador'],
  permissions: ['users.view'],
  veterinarian: null,
};

export const TOKEN = '7|secreto-de-prueba';

/** Una sesión que vence en `minutes` minutos. */
export function sessionFor(minutes = 12 * 60, token = TOKEN): StoredSession {
  return { token, expires_at: new Date(Date.now() + minutes * 60_000).toISOString() };
}

/** Un sessionStorage propio: cada "pestaña" de un test tiene el suyo. */
export function memoryStorage(): SessionStorageLike & { data: Map<string, string> } {
  const data = new Map<string, string>();

  return {
    data,
    getItem: (key) => data.get(key) ?? null,
    setItem: (key, value) => {
      data.set(key, value);
    },
    removeItem: (key) => {
      data.delete(key);
    },
  };
}

/** GET /api/me con éxito. */
export function meHandler(me: Me = ME) {
  return http.get('/api/me', () => success(me));
}

const managers: SessionManager[] = [];

/** Un SessionManager de prueba (se descarta después de cada test). */
export function createTestSession(
  options: {
    storage?: SessionStorageLike;
    hub?: ReturnType<typeof createMemoryChannelHub>;
    requestTimeoutMs?: number;
  } = {},
) {
  const queryClient = createQueryClient({ retryDelay: 0 });
  const hub = options.hub ?? createMemoryChannelHub();
  const manager = new SessionManager({
    queryClient,
    storage: options.storage ?? window.sessionStorage,
    channel: hub.connect(),
    requestTimeoutMs: options.requestTimeoutMs ?? 50,
  });
  managers.push(manager);

  return { manager, queryClient, hub };
}

/**
 * La aplicación entera en `url`, con sessionStorage de jsdom (con `session` guardada, si se pasa) y un canal en memoria.
 */
export function renderApp(
  url: string,
  options: { session?: StoredSession; hub?: ReturnType<typeof createMemoryChannelHub> } = {},
) {
  if (options.session !== undefined) {
    saveSession(window.sessionStorage, options.session);
  }

  const { manager, queryClient, hub } = createTestSession(
    options.hub === undefined ? {} : { hub: options.hub },
  );
  const history = createMemoryHistory({ initialEntries: [url] });

  render(<App queryClient={queryClient} session={manager} history={history} />);

  return { manager, queryClient, hub, history, server };
}

afterEach(() => {
  for (const manager of managers.splice(0)) {
    manager.dispose();
  }

  if (typeof window !== 'undefined') {
    window.sessionStorage.clear();
    window.localStorage.clear();
  }
});
