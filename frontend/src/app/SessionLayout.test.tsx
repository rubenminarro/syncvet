import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { delay, http } from 'msw';
import { describe, expect, it } from 'vitest';
import { api } from '../lib/api/client';
import { MESSAGES } from '../lib/api/errors';
import { createMemoryChannelHub } from '../lib/auth/channel';
import { saveSession, SESSION_KEY } from '../lib/auth/storage';
import { failure, success } from '../test/api';
import { server } from '../test/server';
import {
  createTestSession,
  ME,
  meHandler,
  memoryStorage,
  renderApp,
  sessionFor,
  TOKEN,
} from '../test/session';

const INACTIVE = 'Tu usuario está inactivo: consultá con el administrador de la clínica.';

async function homeShown() {
  return screen.findByText('Inicio', { selector: 'h2' });
}

async function loginShown() {
  return screen.findByText('Ingresá a tu cuenta', { selector: 'h2' });
}

describe('layout con sesión', () => {
  it('al recargar, muestra la carga mientras pide me (no el formulario de ingreso)', async () => {
    server.use(
      http.get('/api/me', async () => {
        await delay(100);
        return success(ME);
      }),
    );
    renderApp('/', { session: sessionFor() });

    expect(await screen.findByText('Cargando tu sesión…')).toBeInTheDocument();
    expect(screen.queryByText('Ingresá a tu cuenta')).not.toBeInTheDocument();
    expect(await homeShown()).toBeInTheDocument();
  });

  it('la barra muestra el usuario, la clínica y la sucursal actual', async () => {
    server.use(meHandler());
    renderApp('/', { session: sessionFor() });

    await homeShown();
    expect(screen.getByText('Ana Gómez', { selector: '.session-user' })).toBeInTheDocument();
    expect(
      screen.getByText('Veterinaria Central', { selector: '.session-clinic' }),
    ).toBeInTheDocument();
    expect(screen.getByText('Casa central', { selector: '.session-branch' })).toBeInTheDocument();
  });

  it('sin sucursal actual, la barra lo dice', async () => {
    server.use(meHandler({ ...ME, current_branch: null }));
    renderApp('/', { session: sessionFor() });

    await homeShown();
    expect(
      screen.getByText('Sin sucursal actual', { selector: '.session-branch' }),
    ).toBeInTheDocument();
  });

  it('un 401 en me al recargar lleva a /ingresar con el aviso de sesión vencida', async () => {
    server.use(http.get('/api/me', () => failure(401, 'UNAUTHENTICATED', 'No autenticado.')));
    const { history } = renderApp('/sin-permiso', { session: sessionFor() });

    expect(await screen.findByText(MESSAGES.sessionExpired)).toBeInTheDocument();
    expect(history.location.pathname).toBe('/ingresar');
    expect(history.location.search).toContain('volver=%2Fsin-permiso');
    expect(window.sessionStorage.getItem(SESSION_KEY)).toBeNull();
  });

  it('una falla de red al cargar me muestra el error con "Reintentar", sin cerrar la sesión', async () => {
    server.use(http.get('/api/me', () => Response.error()));
    renderApp('/', { session: sessionFor() });

    expect(await screen.findByText('No se pudo cargar tu sesión')).toBeInTheDocument();
    expect(screen.getByText(MESSAGES.network)).toBeInTheDocument();
    expect(window.sessionStorage.getItem(SESSION_KEY)).not.toBeNull();
  });

  it.each([
    ['con el 200', 200],
    ['aunque falle', 500],
  ])('Salir hace POST logout y va a /ingresar sin volver (%s)', async (_, status) => {
    const tokens: (string | null)[] = [];
    server.use(
      meHandler(),
      http.post('/api/logout', ({ request }) => {
        tokens.push(request.headers.get('Authorization'));
        return status === 200 ? success(null) : failure(500, 'INTERNAL_SERVER_ERROR', 'Error.');
      }),
    );
    const { history } = renderApp('/sin-permiso', { session: sessionFor() });
    await screen.findByText('No tenés permiso para ver esta página');

    await userEvent.click(screen.getByText('Salir', { selector: 'button' }));

    expect(await loginShown()).toBeInTheDocument();
    expect(tokens).toEqual([`Bearer ${TOKEN}`]);
    expect(history.location.pathname).toBe('/ingresar');
    expect(history.location.search).toBe('');
    expect(screen.queryByText(MESSAGES.sessionExpired)).not.toBeInTheDocument();
    expect(window.sessionStorage.getItem(SESSION_KEY)).toBeNull();
  });

  it.each([
    ['401', 401, 'UNAUTHENTICATED', 'No autenticado.', MESSAGES.sessionExpired],
    ['403 USER_INACTIVE', 403, 'USER_INACTIVE', INACTIVE, INACTIVE],
    [
      '403 CLINIC_NOT_AVAILABLE',
      403,
      'CLINIC_NOT_AVAILABLE',
      'La clínica no está disponible.',
      'La clínica no está disponible.',
    ],
  ])(
    'un %s en cualquier pedido con sesión lleva a /ingresar?volver=… con su aviso',
    async (_, status, code, message, shown) => {
      server.use(
        meHandler(),
        http.get('/api/clientes', () => failure(status, code, message)),
      );
      const { history } = renderApp('/sin-permiso', { session: sessionFor() });
      await screen.findByText('No tenés permiso para ver esta página');

      await act(async () => {
        await api.get('clientes').catch(() => undefined);
      });

      expect(await screen.findByText(shown)).toBeInTheDocument();
      expect(history.location.pathname).toBe('/ingresar');
      expect(history.location.search).toContain('volver=%2Fsin-permiso');
      expect(window.sessionStorage.getItem(SESSION_KEY)).toBeNull();
    },
  );

  it('con menos de 10 minutos, el aviso previo queda visible', async () => {
    server.use(meHandler());
    renderApp('/', { session: sessionFor(9) });

    await homeShown();
    expect(
      screen.getByText('Tu sesión vence en 9 minutos. Guardá los cambios pendientes.'),
    ).toBeInTheDocument();
  });

  it('una pestaña nueva entra con la sesión de una abierta, sin ingresar', async () => {
    server.use(meHandler());
    const hub = createMemoryChannelHub();
    const otherTab = memoryStorage();
    saveSession(otherTab, sessionFor());
    createTestSession({ storage: otherTab, hub });

    renderApp('/', { hub });

    expect(await homeShown()).toBeInTheDocument();
    expect(window.sessionStorage.getItem(SESSION_KEY)).toContain(TOKEN);
  });

  it('una pestaña nueva sin respuesta va a /ingresar', async () => {
    const { history } = renderApp('/');

    expect(await loginShown()).toBeInTheDocument();
    expect(history.location.search).toBe('');
  });

  it('salir en otra pestaña cierra esta', async () => {
    server.use(
      meHandler(),
      http.post('/api/logout', () => success(null)),
    );
    const hub = createMemoryChannelHub();
    const otherTab = memoryStorage();
    saveSession(otherTab, sessionFor());
    const { manager: other } = createTestSession({ storage: otherTab, hub });
    renderApp('/', { session: sessionFor(), hub });
    await homeShown();

    await act(async () => {
      await other.logout();
    });

    expect(await loginShown()).toBeInTheDocument();
    await waitFor(() => {
      expect(window.sessionStorage.getItem(SESSION_KEY)).toBeNull();
    });
  });
});
