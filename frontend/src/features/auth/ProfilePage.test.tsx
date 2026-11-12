import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { SESSION_KEY } from '../../lib/auth/storage';
import { failure, success } from '../../test/api';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor, TOKEN } from '../../test/session';

const CHANGED =
  'Cambiaste tu contraseña. Esta sesión sigue abierta; las de otros dispositivos se cerraron.';
const WRONG_CURRENT = 'La contraseña actual no es correcta.';
const WEAK = 'La contraseña debe tener al menos 12 caracteres.';
const DIFFERENT = 'La contraseña nueva tiene que ser distinta de la actual.';

const current = () => screen.getByLabelText<HTMLInputElement>('Contraseña actual');
const fresh = () => screen.getByLabelText<HTMLInputElement>('Contraseña nueva');
const submit = () => screen.getByText('Cambiar la contraseña', { selector: 'button' });

/** El elemento `index` de la lista (falla el test si no está). */
function nth(list: HTMLElement[], index: number): HTMLElement {
  const element = list[index];

  if (element === undefined) {
    throw new Error(`Falta el elemento ${String(index)}.`);
  }

  return element;
}

async function openProfile() {
  renderApp('/perfil', { session: sessionFor() });
  await screen.findByText('Tu perfil', { selector: 'h2' });
}

async function fill(currentValue: string, newValue: string) {
  await userEvent.type(current(), currentValue);
  await userEvent.type(fresh(), newValue);
  await userEvent.click(submit());
}

describe('/perfil', () => {
  it('muestra los datos del usuario', async () => {
    server.use(meHandler(meFor('veterinario', { veterinarian: { id: 4, code: 'VET-001' } })));
    await openProfile();

    for (const text of [
      'Ana Gómez',
      'admin-a@example.com',
      'Veterinaria Central',
      'veterinario',
      'VET-001',
    ]) {
      expect(screen.getByText(text, { selector: 'dd' })).toBeInTheDocument();
    }
  });

  it('cambia la contraseña con PATCH me/password: el formulario se vacía y la sesión sigue', async () => {
    const requests: { body: unknown; auth: string | null }[] = [];
    server.use(
      meHandler(),
      http.patch('/api/me/password', async ({ request }) => {
        requests.push({ body: await request.json(), auth: request.headers.get('Authorization') });
        return success(null);
      }),
    );
    await openProfile();
    await fill('Vieja-Clave#2026', 'Nueva-Clave#2027');

    expect(await screen.findByText(CHANGED)).toBeInTheDocument();
    expect(requests).toEqual([
      {
        body: { current_password: 'Vieja-Clave#2026', password: 'Nueva-Clave#2027' },
        auth: `Bearer ${TOKEN}`,
      },
    ]);
    expect(current().value).toBe('');
    expect(fresh().value).toBe('');
    expect(window.sessionStorage.getItem(SESSION_KEY)).toContain(TOKEN);
    expect(screen.getByText('Ana Gómez', { selector: '.session-user' })).toBeInTheDocument();
  });

  it('los campos vacíos se marcan sin llamar a la API', async () => {
    server.use(meHandler());
    await openProfile();
    await userEvent.click(submit());

    expect(await screen.findByText('Ingresá tu contraseña actual.')).toBeInTheDocument();
    expect(screen.getByText('Ingresá la contraseña nueva.')).toBeInTheDocument();
  });

  it('un 422 en current_password va a su campo', async () => {
    server.use(
      meHandler(),
      http.patch('/api/me/password', () =>
        failure(422, 'VALIDATION_ERROR', WRONG_CURRENT, {
          errors: { current_password: [WRONG_CURRENT] },
        }),
      ),
    );
    await openProfile();
    await fill('incorrecta', 'Nueva-Clave#2027');

    expect(await screen.findByText(WRONG_CURRENT)).toBeInTheDocument();
    expect(current()).toHaveAttribute('aria-invalid', 'true');
    expect(fresh()).not.toHaveAttribute('aria-invalid');
    await waitFor(() => {
      expect(current()).toHaveFocus();
    });
  });

  it('un 422 en password junta sus mensajes en su campo; otra clave va a la alerta', async () => {
    server.use(
      meHandler(),
      http.patch('/api/me/password', () =>
        failure(422, 'VALIDATION_ERROR', WEAK, {
          errors: { password: [WEAK, DIFFERENT], otro: ['Algo más.'] },
        }),
      ),
    );
    await openProfile();
    await fill('Vieja-Clave#2026', 'corta');

    expect(await screen.findByText(`${WEAK} ${DIFFERENT}`)).toBeInTheDocument();
    expect(fresh()).toHaveAttribute('aria-invalid', 'true');
    expect(current()).not.toHaveAttribute('aria-invalid');
    expect(screen.getByText('Algo más.', { selector: '.alert-title' })).toBeInTheDocument();
  });

  it('un 429 muestra el mensaje y deshabilita el botón con la cuenta regresiva', async () => {
    const message = 'Demasiados intentos. Esperá 1 segundo y probá de nuevo.';
    server.use(
      meHandler(),
      http.patch('/api/me/password', () =>
        failure(429, 'AUTH_TOO_MANY_ATTEMPTS', message, { headers: { 'Retry-After': '1' } }),
      ),
    );
    await openProfile();
    await fill('Vieja-Clave#2026', 'Nueva-Clave#2027');

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(screen.getByText('Esperá 1 segundo', { selector: 'button' })).toBeDisabled();
    expect(
      await screen.findByText('Cambiar la contraseña', { selector: 'button' }, { timeout: 2500 }),
    ).toBeEnabled();
  });

  it('un 429 sin Retry-After espera 60 segundos', async () => {
    server.use(
      meHandler(),
      http.patch('/api/me/password', () =>
        failure(429, 'TOO_MANY_REQUESTS', 'Demasiados pedidos.'),
      ),
    );
    await openProfile();
    await fill('Vieja-Clave#2026', 'Nueva-Clave#2027');

    expect(await screen.findByText('Esperá 60 segundos', { selector: 'button' })).toBeDisabled();
  });

  it('un 403 FORBIDDEN muestra el mensaje de la API y deja el formulario como estaba', async () => {
    server.use(
      meHandler(),
      http.patch('/api/me/password', () =>
        failure(403, 'FORBIDDEN', 'No tenés permiso para realizar esta acción.'),
      ),
    );
    await openProfile();
    await fill('Vieja-Clave#2026', 'Nueva-Clave#2027');

    expect(
      await screen.findByText('No tenés permiso para realizar esta acción.'),
    ).toBeInTheDocument();
    expect(current().value).toBe('Vieja-Clave#2026');
    expect(fresh().value).toBe('Nueva-Clave#2027');
  });

  it('el botón muestra y oculta cada campo', async () => {
    server.use(meHandler());
    await openProfile();
    const toggles = screen.getAllByLabelText('Mostrar la contraseña');

    expect(toggles).toHaveLength(2);
    expect(current()).toHaveAttribute('type', 'password');
    expect(fresh()).toHaveAttribute('type', 'password');

    await userEvent.click(nth(toggles, 1));
    expect(fresh()).toHaveAttribute('type', 'text');
    expect(current()).toHaveAttribute('type', 'password');

    await userEvent.click(nth(toggles, 0));
    expect(current()).toHaveAttribute('type', 'text');

    await userEvent.click(nth(screen.getAllByLabelText('Ocultar la contraseña'), 0));
    expect(current()).toHaveAttribute('type', 'password');
  });
});
