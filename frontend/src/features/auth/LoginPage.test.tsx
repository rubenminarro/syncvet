import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { MESSAGES } from '../../lib/api/errors';
import { SESSION_KEY } from '../../lib/auth/storage';
import { failure, success } from '../../test/api';
import { server } from '../../test/server';
import { apiDateTime, ME, meHandler, renderApp, sessionFor, TOKEN } from '../../test/session';

// Con el formato de la API y relativo a la hora de la corrida (una fecha fija vencería con el tiempo).
const EXPIRES_AT = apiDateTime(Date.now() + 12 * 60 * 60_000);

function loginOk() {
  const requests: { body: unknown; authorization: string | null }[] = [];
  server.use(
    http.post('/api/login', async ({ request }) => {
      requests.push({
        body: await request.json(),
        authorization: request.headers.get('Authorization'),
      });
      return success({ token: TOKEN, token_type: 'Bearer', expires_at: EXPIRES_AT, user: ME });
    }),
  );

  return requests;
}

function loginFails(status: number, code: string, message: string, init = {}) {
  server.use(http.post('/api/login', () => failure(status, code, message, init)));
}

async function fillAndSubmit(email = 'admin-a@example.com', password = 'clave-correcta') {
  await screen.findByText('Ingresá a tu cuenta', { selector: 'h2' });
  await userEvent.type(screen.getByLabelText('Email'), email);
  await userEvent.type(screen.getByLabelText('Contraseña'), password);
  await userEvent.click(screen.getByText('Ingresar', { selector: 'button' }));
}

describe('ingresar', () => {
  it('con el 200 guarda la sesión en sessionStorage (no en localStorage ni en la URL) y va al inicio', async () => {
    const requests = loginOk();
    const { history } = renderApp('/ingresar');

    await fillAndSubmit();

    expect(await screen.findByText('Ana Gómez', { selector: '.session-user' })).toBeInTheDocument();
    expect(requests).toEqual([
      { body: { email: 'admin-a@example.com', password: 'clave-correcta' }, authorization: null },
    ]);
    expect(JSON.parse(window.sessionStorage.getItem(SESSION_KEY) ?? '')).toEqual({
      token: TOKEN,
      expires_at: EXPIRES_AT,
    });
    expect(window.localStorage.length).toBe(0);
    expect(history.location.pathname).toBe('/');
    expect(history.location.href).not.toContain('secreto');
  });

  it('una ruta protegida sin sesión lleva a /ingresar y, después de ingresar, vuelve a ella', async () => {
    loginOk();
    const { history } = renderApp('/sin-permiso');

    await screen.findByText('Ingresá a tu cuenta', { selector: 'h2' });
    expect(history.location.pathname).toBe('/ingresar');
    expect(history.location.search).toContain('volver=%2Fsin-permiso');

    await fillAndSubmit();

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
  });

  it.each(['https://otro.sitio/robar', '//otro.sitio/robar'])(
    'ignora un volver externo (%s) y va al inicio',
    async (volver) => {
      loginOk();
      const { history } = renderApp(`/ingresar?volver=${encodeURIComponent(volver)}`);

      await fillAndSubmit();

      expect(await screen.findByText('Inicio', { selector: 'h2' })).toBeInTheDocument();
      expect(history.location.pathname).toBe('/');
    },
  );

  it('INVALID_CREDENTIALS: el mensaje de la API y se borra la contraseña', async () => {
    loginFails(422, 'INVALID_CREDENTIALS', 'El email o la contraseña no son correctos.');
    renderApp('/ingresar');

    await fillAndSubmit();

    expect(
      await screen.findByText('El email o la contraseña no son correctos.'),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Contraseña')).toHaveValue('');
    expect(window.sessionStorage.getItem(SESSION_KEY)).toBeNull();
  });

  it('VALIDATION_ERROR: cada error a su campo', async () => {
    loginFails(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
      errors: { email: ['El email no tiene un formato válido.'] },
    });
    renderApp('/ingresar');

    await fillAndSubmit();

    expect(await screen.findByText('El email no tiene un formato válido.')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
  });

  it('429: el mensaje y el botón deshabilitado con la cuenta regresiva de Retry-After', async () => {
    loginFails(
      429,
      'AUTH_TOO_MANY_ATTEMPTS',
      'Demasiados intentos. Probá de nuevo en 1 segundos.',
      {
        headers: { 'Retry-After': '1' },
      },
    );
    renderApp('/ingresar');

    await fillAndSubmit();

    const button = await screen.findByText('Esperá 1 segundo', { selector: 'button' });
    expect(button).toBeDisabled();
    expect(
      screen.getByText('Demasiados intentos. Probá de nuevo en 1 segundos.'),
    ).toBeInTheDocument();

    await waitFor(
      () => {
        expect(screen.getByText('Ingresar', { selector: 'button' })).toBeEnabled();
      },
      { timeout: 2500 },
    );
  });

  it('429 sin Retry-After: el botón queda deshabilitado 60 segundos', async () => {
    loginFails(429, 'AUTH_TOO_MANY_ATTEMPTS', 'Demasiados intentos.');
    renderApp('/ingresar');

    await fillAndSubmit();

    const button = await screen.findByText('Esperá 60 segundos', { selector: 'button' });
    expect(button).toBeDisabled();
  });

  it.each([
    ['USER_INACTIVE', 'Tu usuario está inactivo: consultá con el administrador de la clínica.'],
    ['CLINIC_NOT_AVAILABLE', 'La clínica no está disponible.'],
  ])('403 %s: el mensaje de la API en el formulario', async (code, message) => {
    loginFails(403, code, message);
    const { history } = renderApp('/ingresar');

    await fillAndSubmit();

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(history.location.pathname).toBe('/ingresar');
  });

  it('el botón muestra y oculta la contraseña', async () => {
    renderApp('/ingresar');
    await screen.findByText('Ingresá a tu cuenta', { selector: 'h2' });
    const input = screen.getByLabelText('Contraseña');

    expect(input).toHaveAttribute('type', 'password');

    await userEvent.click(screen.getByLabelText('Mostrar la contraseña'));
    expect(input).toHaveAttribute('type', 'text');
    expect(screen.getByLabelText('Ocultar la contraseña')).toHaveAttribute('aria-pressed', 'true');

    await userEvent.click(screen.getByLabelText('Ocultar la contraseña'));
    expect(input).toHaveAttribute('type', 'password');
  });

  it('con una sesión vencida guardada, muestra el aviso sin llamar a la API', async () => {
    renderApp('/', { session: sessionFor(-1) });

    expect(await screen.findByText(MESSAGES.sessionExpired)).toBeInTheDocument();
    expect(window.sessionStorage.getItem(SESSION_KEY)).toBeNull();
  });

  it('con una sesión abierta, /ingresar va al inicio', async () => {
    server.use(meHandler());
    const { history } = renderApp('/ingresar', { session: sessionFor() });

    expect(await screen.findByText('Inicio', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/');
  });
});
