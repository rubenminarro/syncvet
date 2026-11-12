import {
  createMemoryHistory,
  createRootRoute,
  createRouter,
  RouterProvider,
} from '@tanstack/react-router';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ApiError } from '../lib/api/errors';
import { server } from '../test/server';
import { meFor, ROLES } from '../test/roles';
import { meHandler, renderApp, sessionFor } from '../test/session';
import { RouteErrorPage } from './RouteErrorPage';

// Por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
const menuLabels = () =>
  Array.from(document.querySelectorAll('.nav-link-title')).map((node) => node.textContent);

describe('permisos con los roles reales del seeder', () => {
  it.each([
    ['administrador', ['Inicio', 'Configuración']],
    ['recepcionista', ['Inicio']],
    ['veterinario', ['Inicio']],
  ] as const)('el menú de %s: %j', async (role, expected) => {
    server.use(meHandler(meFor(role)));
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    expect(menuLabels()).toEqual(expected);
  });

  it('el administrador entra a /configuracion', async () => {
    server.use(meHandler(meFor('administrador')));
    const { history } = renderApp('/configuracion', { session: sessionFor() });

    expect(await screen.findByText('Configuración', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/configuracion');
  });

  it.each(['recepcionista', 'veterinario'] as const)(
    '%s en /configuracion va a /sin-permiso, dentro del layout',
    async (role) => {
      server.use(meHandler(meFor(role)));
      const { history } = renderApp('/configuracion', { session: sessionFor() });

      expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
      expect(history.location.pathname).toBe('/sin-permiso');
      // El layout con sesión (desvío 9 de la 0.1): la barra y el menú siguen a la vista.
      expect(screen.getByText('Ana Gómez', { selector: '.session-user' })).toBeInTheDocument();
      expect(screen.queryByText('Configuración', { selector: 'h2' })).not.toBeInTheDocument();
    },
  );

  it.each(ROLES)('%s ve /perfil', async (role) => {
    server.use(meHandler(meFor(role)));
    renderApp('/perfil', { session: sessionFor() });

    expect(await screen.findByText('Tu perfil', { selector: 'h2' })).toBeInTheDocument();
    expect(screen.getByText(role, { selector: 'dd' })).toBeInTheDocument();
  });

  it.each([
    ['administrador', true],
    ['recepcionista', false],
    ['veterinario', false],
  ] as const)('el botón de Configuración del inicio, con %s: %s', async (role, shown) => {
    server.use(meHandler(meFor(role)));
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    expect(document.querySelector('a.home-settings') !== null).toBe(shown);
  });
});

/** RouteErrorPage con un error, dentro de un router mínimo (la página de sin permiso tiene un Link). */
function renderError(error: unknown) {
  const router = createRouter({
    routeTree: createRootRoute({
      component: () => (
        <RouteErrorPage
          error={error as Error}
          reset={() => undefined}
          info={{ componentStack: '' }}
        />
      ),
    }),
    history: createMemoryHistory({ initialEntries: ['/'] }),
  });
  render(<RouterProvider router={router} />);
}

describe('RouteErrorPage', () => {
  it('un 403 FORBIDDEN de la API muestra la página de sin permiso con el mensaje de la API', async () => {
    const error = new ApiError({
      status: 403,
      errorCode: 'FORBIDDEN',
      message: 'No tenés permiso para ver la auditoría.',
    });
    renderError(error);

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(screen.getByText('No tenés permiso para ver la auditoría.')).toBeInTheDocument();
  });

  it('otro error muestra su mensaje y "Reintentar"', async () => {
    const error = new ApiError({ status: 500, errorCode: 'INTERNAL_SERVER_ERROR', message: 'x' });
    renderError(error);

    expect(
      await screen.findByText('No se pudo completar. Probá de nuevo en unos minutos.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Reintentar')).toBeInTheDocument();
  });
});
