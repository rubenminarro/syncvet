// @vitest-environment jsdom
// El cliente de la API arma la URL con window.location.origin y la sesión escucha visibilitychange: necesita el DOM.
import { http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { server } from '../../test/server';
import { createTestSession, memoryStorage, sessionFor, TOKEN } from '../../test/session';
import { api } from '../api/client';
import { MESSAGES } from '../api/errors';
import { createMemoryChannelHub } from './channel';
import { readSession, saveSession, SESSION_KEY } from './storage';

/** Deja pasar los mensajes del canal en memoria (microtareas), también con los temporizadores falsos. */
async function flush(): Promise<void> {
  for (let i = 0; i < 5; i++) {
    await Promise.resolve();
  }
}

/** Dos pestañas con el mismo canal; la primera con `first` guardada, la segunda con `second`. */
function twoTabs(
  first: ReturnType<typeof sessionFor> | null,
  second: ReturnType<typeof sessionFor> | null,
) {
  const hub = createMemoryChannelHub();
  const storageA = memoryStorage();
  const storageB = memoryStorage();

  if (first !== null) {
    saveSession(storageA, first);
  }

  if (second !== null) {
    saveSession(storageB, second);
  }

  const a = createTestSession({ storage: storageA, hub });
  const b = createTestSession({ storage: storageB, hub });

  return { a: a.manager, b: b.manager, storageA, storageB };
}

/** Cuenta los POST /api/logout y guarda el token de cada uno. */
function logoutSpy(status = 200) {
  const tokens: (string | null)[] = [];
  server.use(
    http.post('/api/logout', ({ request }) => {
      tokens.push(request.headers.get('Authorization'));
      return status === 200 ? success(null) : failure(status, 'INTERNAL_SERVER_ERROR', 'Error.');
    }),
  );

  return tokens;
}

describe('SessionManager', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('al abrir, toma la sesión guardada y el cliente manda su token', async () => {
    const storage = memoryStorage();
    saveSession(storage, sessionFor());
    let authorization: string | null = null;
    server.use(
      http.get('/api/me', ({ request }) => {
        authorization = request.headers.get('Authorization');
        return success({ id: 1 });
      }),
    );

    const { manager } = createTestSession({ storage });
    await api.get('me');

    expect(manager.getSnapshot().session?.token).toBe(TOKEN);
    expect(authorization).toBe(`Bearer ${TOKEN}`);
  });

  it('al abrir, una sesión vencida se borra sin llamar a la API y deja el aviso', async () => {
    const storage = memoryStorage();
    saveSession(storage, sessionFor(-1));

    const { manager } = createTestSession({ storage });

    expect(manager.getSnapshot()).toEqual({
      session: null,
      warningMinutes: null,
      notice: MESSAGES.sessionExpired,
    });
    expect(storage.data.has(SESSION_KEY)).toBe(false);
    // Sin handlers de MSW: un pedido haría fallar el test.
    expect(await manager.ensureSession()).toBe(false);
  });

  it('start guarda la sesión y borra el aviso anterior', () => {
    const storage = memoryStorage();
    const { manager } = createTestSession({ storage });
    const session = sessionFor();

    manager.start(session);

    expect(readSession(storage)).toEqual(session);
    expect(manager.getSnapshot().notice).toBeNull();
  });

  it('enciende el aviso previo 10 minutos antes del vencimiento', () => {
    vi.useFakeTimers();
    const { manager } = createTestSession({ storage: memoryStorage() });
    manager.start(sessionFor(60));

    vi.advanceTimersByTime(49 * 60_000);
    expect(manager.getSnapshot().warningMinutes).toBeNull();

    vi.advanceTimersByTime(60_000);
    expect(manager.getSnapshot().warningMinutes).toBe(10);
  });

  it('con menos de 10 minutos al abrir, el aviso sale enseguida con los minutos que quedan', () => {
    const storage = memoryStorage();
    saveSession(storage, sessionFor(4));

    const { manager } = createTestSession({ storage });

    expect(manager.getSnapshot().warningMinutes).toBe(4);
  });

  it('al vencer, cierra la sesión en todas las pestañas sin POST logout', async () => {
    vi.useFakeTimers();
    const session = sessionFor(30);
    const { a, b, storageA, storageB } = twoTabs(session, session);

    vi.advanceTimersByTime(30 * 60_000);
    await flush();

    expect(a.getSnapshot()).toMatchObject({ session: null, notice: MESSAGES.sessionExpired });
    expect(b.getSnapshot()).toMatchObject({ session: null, notice: MESSAGES.sessionExpired });
    expect(storageA.data.has(SESSION_KEY)).toBe(false);
    expect(storageB.data.has(SESSION_KEY)).toBe(false);
  });

  it('al volver a la pestaña con la hora pasada (PC suspendida), cierra la sesión', () => {
    vi.useFakeTimers();
    const { manager } = createTestSession({ storage: memoryStorage() });
    manager.start(sessionFor(30));

    // La hora avanza sin que corran los temporizadores, como con la PC suspendida.
    vi.setSystemTime(Date.now() + 31 * 60_000);
    document.dispatchEvent(new Event('visibilitychange'));

    expect(manager.getSnapshot()).toMatchObject({ session: null, notice: MESSAGES.sessionExpired });
  });

  it('al volver a la pestaña, toma el expires_at cambiado en el almacenamiento', () => {
    const storage = memoryStorage();
    const { manager } = createTestSession({ storage });
    manager.start(sessionFor());

    saveSession(storage, sessionFor(5));
    document.dispatchEvent(new Event('visibilitychange'));

    expect(manager.getSnapshot().warningMinutes).toBe(5);
  });

  it('una pestaña nueva recibe la sesión de una abierta', async () => {
    const session = sessionFor();
    const { b, storageB } = twoTabs(session, null);

    expect(await b.ensureSession()).toBe(true);
    expect(b.getSnapshot().session).toEqual(session);
    expect(readSession(storageB)).toEqual(session);
  });

  it('una pestaña nueva sin respuesta queda sin sesión', async () => {
    const { b } = twoTabs(null, null);

    expect(await b.ensureSession()).toBe(false);
    expect(b.getSnapshot().session).toBeNull();
  });

  it('salir hace POST logout, borra la sesión y la cierra en la otra pestaña sin otro POST', async () => {
    const tokens = logoutSpy();
    const session = sessionFor();
    const { a, b, storageA, storageB } = twoTabs(session, session);

    await a.logout();
    await flush();

    expect(tokens).toEqual([`Bearer ${TOKEN}`]);
    expect(a.getSnapshot()).toMatchObject({ session: null, notice: null });
    expect(b.getSnapshot()).toMatchObject({ session: null, notice: null });
    expect(storageA.data.has(SESSION_KEY)).toBe(false);
    expect(storageB.data.has(SESSION_KEY)).toBe(false);
  });

  it('si POST logout falla, la sesión se borra igual', async () => {
    logoutSpy(500);
    const storage = memoryStorage();
    const { manager } = createTestSession({ storage });
    manager.start(sessionFor());

    await manager.logout();

    expect(manager.getSnapshot().session).toBeNull();
    expect(storage.data.has(SESSION_KEY)).toBe(false);
  });

  it('una pestaña con otro token también hace su propio POST logout', async () => {
    const tokens = logoutSpy();
    const { a, b } = twoTabs(sessionFor(), sessionFor(12 * 60, '8|otro-token'));

    await a.logout();
    await vi.waitFor(() => {
      expect(tokens).toHaveLength(2);
    });

    expect(tokens).toEqual([`Bearer ${TOKEN}`, 'Bearer 8|otro-token']);
    expect(b.getSnapshot().session).toBeNull();
  });

  it('un 401 en un pedido con la sesión la cierra en todas las pestañas con el aviso de sesión vencida', async () => {
    server.use(http.get('/api/clientes', () => failure(401, 'UNAUTHENTICATED', 'No autenticado.')));
    const session = sessionFor();
    const { a, b } = twoTabs(session, session);

    await expect(api.get('clientes')).rejects.toMatchObject({ status: 401 });
    await flush();

    expect(a.getSnapshot()).toMatchObject({ session: null, notice: MESSAGES.sessionExpired });
    expect(b.getSnapshot()).toMatchObject({ session: null, notice: MESSAGES.sessionExpired });
  });

  it('un 403 USER_INACTIVE con sesión la cierra con el mensaje de la API', async () => {
    const message = 'Tu usuario está inactivo: consultá con el administrador de la clínica.';
    server.use(http.get('/api/clientes', () => failure(403, 'USER_INACTIVE', message)));
    const storage = memoryStorage();
    saveSession(storage, sessionFor());
    const { manager } = createTestSession({ storage });

    await expect(api.get('clientes')).rejects.toMatchObject({ status: 403 });

    expect(manager.getSnapshot()).toMatchObject({ session: null, notice: message });
  });

  it('un 401 de un pedido sin el token de la sesión (el login) no cierra nada', async () => {
    server.use(http.post('/api/login', () => failure(401, 'UNAUTHENTICATED', 'No autenticado.')));
    const storage = memoryStorage();
    saveSession(storage, sessionFor());
    const { manager } = createTestSession({ storage });

    await expect(api.post('login', {}, { token: null })).rejects.toMatchObject({ status: 401 });

    expect(manager.getSnapshot().session).not.toBeNull();
  });
});
