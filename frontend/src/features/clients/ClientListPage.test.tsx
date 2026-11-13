import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { COMPANY, pagination, PERSON } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { CLIENTS_PER_PAGE } from './api';
import type { Client } from './types';

/** Los pedidos a GET /api/clients, con sus parámetros. */
function clientsHandler(
  respond: (params: URLSearchParams) => {
    items: Client[];
    total?: number;
    last?: number;
  } = () => ({
    items: [PERSON, COMPANY],
  }),
) {
  const calls: URLSearchParams[] = [];
  const handler = http.get('/api/clients', ({ request }) => {
    const params = new URL(request.url).searchParams;
    calls.push(params);
    const { items, total, last } = respond(params);

    return success(items, {
      meta: pagination({
        total: total ?? items.length,
        current_page: Number(params.get('page') ?? '1'),
        last_page: last ?? 1,
      }),
    });
  });

  return { handler, calls };
}

function searchInput(): HTMLInputElement {
  return screen.getByLabelText<HTMLInputElement>('Buscar clientes');
}

function kindSelect(): HTMLSelectElement {
  return screen.getByLabelText<HTMLSelectElement>('Tipo de cliente');
}

const rowNames = () =>
  Array.from(document.querySelectorAll('tbody tr td:first-child')).map((cell) => cell.textContent);

async function openList(url = '/clientes', role: Parameters<typeof meFor>[0] = 'administrador') {
  const api = clientsHandler();
  server.use(meHandler(meFor(role)), api.handler);
  const rendered = renderApp(url, { session: sessionFor() });
  await screen.findByText('Clientes', { selector: 'h2' });

  return { ...rendered, api };
}

describe('/clientes: listado', () => {
  it('muestra la tabla con 20 por página y el enlace al detalle', async () => {
    const { api } = await openList();

    expect(await screen.findByText('Laura Martínez', { selector: 'a' })).toHaveAttribute(
      'href',
      '/clientes/1',
    );
    expect(rowNames()).toEqual(['Laura Martínez', 'Granja San Jorge S.R.L.']);
    expect(screen.getByText('99999903-6', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Empresa', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Mostrando 1 a 2 de 2')).toBeInTheDocument();
    expect(api.calls).toHaveLength(1);
    expect(Object.fromEntries(api.calls[0] ?? [])).toEqual({
      page: '1',
      per_page: String(CLIENTS_PER_PAGE),
    });
  });

  it('lee la búsqueda, el tipo y la página de la URL al entrar (recargar muestra lo mismo)', async () => {
    const api = clientsHandler(() => ({ items: [PERSON], total: 41, last: 3 }));
    server.use(meHandler(meFor('recepcionista')), api.handler);
    renderApp('/clientes?buscar=Firulais&tipo=persona&pagina=2', { session: sessionFor() });

    await screen.findByText('Laura Martínez', { selector: 'a' });
    expect(Object.fromEntries(api.calls[0] ?? [])).toEqual({
      search: 'Firulais',
      kind: 'person',
      page: '2',
      per_page: '20',
    });
    expect(searchInput().value).toBe('Firulais');
    expect(kindSelect().value).toBe('persona');
    expect(screen.getByText('Página 2 de 3')).toBeInTheDocument();
  });

  // Las dos URL: sin un parámetro válido, el router deja pasar el valor crudo de la URL si validateSearch no devuelve la
  // clave (lo prueba la mutación de la entrega 1.1).
  it.each(['/clientes?pagina=-4&tipo=otro', '/clientes?pagina=0&tipo=otro&buscar=%20%20'])(
    'una URL con valores inválidos (%s) muestra el listado por defecto, sin romper la página',
    async (url) => {
      const { api } = await openList(url);

      await screen.findByText('Laura Martínez', { selector: 'a' });
      expect(Object.fromEntries(api.calls[0] ?? [])).toEqual({ page: '1', per_page: '20' });
      expect(kindSelect().value).toBe('');
      expect(searchInput().value).toBe('');
    },
  );

  it('la búsqueda espera 300 ms, manda un solo pedido, vuelve a la página 1 y no llena el historial', async () => {
    const { api, history } = await openList('/clientes?pagina=2');
    await screen.findByText('Laura Martínez', { selector: 'a' });
    const entries = history.length;
    const user = userEvent.setup();

    await user.type(searchInput(), 'Firu');
    // Escribir no busca: todavía ningún pedido con lo escrito.
    expect(api.calls.filter((params) => params.has('search'))).toHaveLength(0);

    await waitFor(() => {
      expect(history.location.search).toBe('?buscar=Firu');
    });
    await waitFor(() => {
      expect(api.calls.filter((params) => params.has('search'))).toHaveLength(1);
    });
    expect(Object.fromEntries(api.calls.at(-1) ?? [])).toEqual({
      search: 'Firu',
      page: '1',
      per_page: '20',
    });
    // replace: la búsqueda no suma entradas al historial.
    expect(history.length).toBe(entries);

    // Sin más teclas, no hay más pedidos.
    await new Promise((resolve) => setTimeout(resolve, 400));
    expect(api.calls.filter((params) => params.has('search'))).toHaveLength(1);
  });

  it('el campo se recorta a 100 caracteres (el máximo de la API)', async () => {
    await openList();

    expect(searchInput()).toHaveAttribute('maxLength', '100');
  });

  it('borrar la búsqueda la saca de la URL', async () => {
    const { history } = await openList('/clientes?buscar=Laura');
    await screen.findByText('Laura Martínez', { selector: 'a' });

    fireEvent.change(searchInput(), { target: { value: '' } });
    await waitFor(() => {
      expect(history.location.search).toBe('');
    });
  });

  it('"Atrás" a otra búsqueda muestra en el campo la de la URL', async () => {
    const { history } = await openList('/clientes?buscar=Laura');
    await screen.findByText('Laura Martínez', { selector: 'a' });

    act(() => {
      history.push('/clientes?buscar=Granja');
    });
    await waitFor(() => {
      expect(searchInput().value).toBe('Granja');
    });
    act(() => {
      history.back();
    });
    await waitFor(() => {
      expect(searchInput().value).toBe('Laura');
    });
    // El campo no vuelve a escribir la URL que dejó "Atrás".
    await new Promise((resolve) => setTimeout(resolve, 400));
    expect(history.location.search).toBe('?buscar=Laura');
  });

  it('el filtro de tipo va a la URL y a la API, y vuelve a la página 1', async () => {
    const { api, history } = await openList('/clientes?pagina=3');
    await screen.findByText('Laura Martínez', { selector: 'a' });

    fireEvent.change(kindSelect(), { target: { value: 'empresa' } });

    await waitFor(() => {
      expect(history.location.search).toBe('?tipo=empresa');
    });
    await waitFor(() => {
      expect(Object.fromEntries(api.calls.at(-1) ?? [])).toEqual({
        kind: 'company',
        page: '1',
        per_page: '20',
      });
    });

    fireEvent.change(kindSelect(), { target: { value: '' } });
    await waitFor(() => {
      expect(history.location.search).toBe('');
    });
  });

  it('la paginación cambia la página de la URL y "Atrás" vuelve a la anterior', async () => {
    const api = clientsHandler(() => ({ items: [PERSON], total: 45, last: 3 }));
    server.use(meHandler(meFor('administrador')), api.handler);
    const { history } = renderApp('/clientes?buscar=a', { session: sessionFor() });
    await screen.findByText('Página 1 de 3');

    fireEvent.click(screen.getByText('Siguiente', { selector: 'button' }));
    await screen.findByText('Página 2 de 3');
    expect(history.location.search).toBe('?buscar=a&pagina=2');
    expect(api.calls.at(-1)?.get('page')).toBe('2');
    expect(screen.getByText('Mostrando 21 a 40 de 45')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Anterior', { selector: 'button' }));
    await screen.findByText('Página 1 de 3');
    expect(history.location.search).toBe('?buscar=a');

    act(() => {
      history.back();
    });
    await screen.findByText('Página 2 de 3');
  });

  it.each([
    ['/clientes', 'Todavía no hay clientes.'],
    ['/clientes?buscar=nadie', 'No hay clientes que coincidan con la búsqueda.'],
    ['/clientes?tipo=empresa', 'No hay clientes que coincidan con la búsqueda.'],
  ])('vacío en %s: "%s"', async (url, message) => {
    server.use(meHandler(meFor('administrador')), clientsHandler(() => ({ items: [] })).handler);
    renderApp(url, { session: sessionFor() });

    expect(await screen.findByText(message)).toBeInTheDocument();
  });

  it('mientras carga, el estado de carga', async () => {
    server.use(
      meHandler(meFor('administrador')),
      http.get('/api/clients', () => new Promise<never>(() => undefined)),
    );
    renderApp('/clientes', { session: sessionFor() });

    expect(await screen.findByText('Cargando los clientes…')).toBeInTheDocument();
  });

  it('un 5xx (ya reintentado una vez) muestra el error; "Probá de nuevo" lo vuelve a pedir', async () => {
    let attempts = 0;
    server.use(
      meHandler(meFor('administrador')),
      http.get('/api/clients', () => {
        attempts++;

        return attempts <= 2
          ? failure(503, 'SERVICE_UNAVAILABLE', 'El servicio no está disponible.')
          : success([PERSON], { meta: pagination({ total: 1 }) });
      }),
    );
    renderApp('/clientes', { session: sessionFor() });

    expect(await screen.findByText('No se pudieron cargar los clientes')).toBeInTheDocument();
    expect(attempts).toBe(2);

    fireEvent.click(screen.getByText('Probá de nuevo', { selector: 'button' }));
    expect(await screen.findByText('Laura Martínez', { selector: 'a' })).toBeInTheDocument();
  });

  it('un 403 FORBIDDEN de la API muestra la página de sin permiso con su mensaje', async () => {
    server.use(
      meHandler(meFor('administrador')),
      http.get('/api/clients', () =>
        failure(403, 'FORBIDDEN', 'No tenés permisos para realizar esta acción.'),
      ),
    );
    renderApp('/clientes', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(screen.getByText('No tenés permisos para realizar esta acción.')).toBeInTheDocument();
  });

  it('sin clients.show, el nombre no es un enlace', async () => {
    const me = meFor('administrador');
    server.use(
      meHandler({
        ...me,
        permissions: me.permissions.filter((permission) => permission !== 'clients.show'),
      }),
      clientsHandler().handler,
    );
    renderApp('/clientes', { session: sessionFor() });

    expect(await screen.findByText('Laura Martínez', { selector: 'td' })).toBeInTheDocument();
    expect(document.querySelector('a.client-link')).toBeNull();
  });

  it('sin clients.index, /clientes va a /sin-permiso sin pedir el listado', async () => {
    const me = meFor('administrador');
    const api = clientsHandler();
    server.use(
      meHandler({
        ...me,
        permissions: me.permissions.filter((permission) => permission !== 'clients.index'),
      }),
      api.handler,
    );
    const { history } = renderApp('/clientes', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
    expect(api.calls).toHaveLength(0);
  });
});
