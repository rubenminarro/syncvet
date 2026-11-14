import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { pagination } from '../../test/clients';
import { listPatient, SPECIES } from '../../test/patients';
import { meFor, ROLE_PERMISSIONS, type Role } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { PATIENTS_PER_PAGE } from './api';
import type { Patient } from './types';

afterEach(() => {
  vi.useRealTimers();
});

const TOBY = listPatient({
  id: 7,
  code: 'PAC-000007',
  name: 'Toby',
  breed: null,
  sex: { value: 'unknown', label: 'Sin determinar' },
  age: null,
  client: { id: 3, display_name: 'Granja San Jorge S.R.L.' },
});
const FIRULAIS = listPatient();
const MISHI = listPatient({
  id: 2,
  code: 'PAC-000002',
  name: 'Mishi',
  species: { id: 4, name: 'Felino' },
  breed: { id: 5, name: 'Siamés' },
  sex: { value: 'female', label: 'Hembra' },
  is_deceased: true,
});

/** Los pedidos a GET /api/patients, con sus parámetros. */
function patientsHandler(
  respond: (params: URLSearchParams) => {
    items: Patient[];
    total?: number;
    last?: number;
  } = () => ({
    items: [FIRULAIS, TOBY],
  }),
) {
  const calls: URLSearchParams[] = [];
  const handler = http.get('/api/patients', ({ request }) => {
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

const speciesHandler = http.get('/api/species', () => success(SPECIES));

const cells = (column: number) =>
  Array.from(document.querySelectorAll(`tbody tr td:nth-child(${String(column)})`)).map(
    (cell) => cell.textContent,
  );

const searchInput = () => screen.getByLabelText<HTMLInputElement>('Buscar pacientes');
const speciesSelect = () => screen.getByLabelText<HTMLSelectElement>('Especie');
const deceasedBox = () => screen.getByLabelText<HTMLInputElement>('Incluir los fallecidos');

async function openList(
  url = '/pacientes',
  options: { role?: Role; api?: ReturnType<typeof patientsHandler> } = {},
) {
  const api = options.api ?? patientsHandler();
  server.use(meHandler(meFor(options.role ?? 'recepcionista')), api.handler, speciesHandler);
  const rendered = renderApp(url, { session: sessionFor() });
  await screen.findByText('Pacientes', { selector: 'h2' });
  await screen.findByText('Firulais', { selector: 'a' });

  return { ...rendered, api };
}

describe('/pacientes: listado', () => {
  it('la tabla con código, nombre (enlace), especie, raza, sexo, edad y dueño (enlace al cliente), 20 por página', async () => {
    const { api } = await openList();

    expect(cells(1)).toEqual(['P-000001', 'PAC-000007']);
    expect(cells(2)).toEqual(['Firulais', 'Toby']);
    expect(cells(3)).toEqual(['Canino', 'Canino']);
    expect(cells(4)).toEqual(['Mestizo', '']);
    expect(cells(5)).toEqual(['Macho', 'Sin determinar']);
    expect(cells(6)).toEqual(['4 años', '']);
    expect(cells(7)).toEqual(['Laura Martínez', 'Granja San Jorge S.R.L.']);
    expect(screen.getByText('Firulais', { selector: 'a' })).toHaveAttribute('href', '/pacientes/1');
    expect(screen.getByText('Granja San Jorge S.R.L.', { selector: 'a' })).toHaveAttribute(
      'href',
      '/clientes/3',
    );
    expect(api.calls.at(-1)?.get('per_page')).toBe(String(PATIENTS_PER_PAGE));
    expect(PATIENTS_PER_PAGE).toBe(20);
    // Sin filtros: ni la especie ni los fallecidos van a la API (la API oculta los fallecidos por defecto).
    expect(api.calls.at(-1)?.has('species_id')).toBe(false);
    expect(api.calls.at(-1)?.has('include_deceased')).toBe(false);
    // Sin alta desde acá: los pacientes nuevos se cargan desde su dueño.
    expect(screen.queryByText('Nuevo paciente')).not.toBeInTheDocument();
  });

  it('un fallecido lleva la marca "Fallecido"', async () => {
    await openList('/pacientes?fallecidos=1', {
      api: patientsHandler(() => ({ items: [FIRULAIS, MISHI] })),
    });

    expect(screen.getByText('Fallecido', { selector: '.patient-deceased' })).toBeInTheDocument();
  });

  it('lee la búsqueda, la especie, los fallecidos y la página de la URL al entrar (recargar muestra lo mismo)', async () => {
    const { api } = await openList('/pacientes?buscar=laura&especie=4&fallecidos=1&pagina=2', {
      api: patientsHandler(() => ({ items: [FIRULAIS], total: 21, last: 2 })),
    });

    const params = api.calls.at(-1);
    expect(params?.get('search')).toBe('laura');
    expect(params?.get('species_id')).toBe('4');
    expect(params?.get('include_deceased')).toBe('1');
    expect(params?.get('page')).toBe('2');
    expect(searchInput().value).toBe('laura');
    await waitFor(() => {
      expect(speciesSelect().value).toBe('4');
    });
    expect(deceasedBox()).toBeChecked();
  });

  it('un valor inválido en la URL vuelve al valor por defecto, sin romper la página', async () => {
    const { api, history } = await openList('/pacientes?especie=perro&fallecidos=2&pagina=-4');

    const params = api.calls.at(-1);
    expect(params?.has('species_id')).toBe(false);
    expect(params?.has('include_deceased')).toBe(false);
    expect(params?.get('page')).toBe('1');
    expect(history.location.search).toBe('');
  });

  it('la búsqueda espera 300 ms, manda un solo pedido, vuelve a la página 1 y no llena el historial', async () => {
    const { api, history } = await openList('/pacientes?pagina=2', {
      api: patientsHandler(() => ({ items: [FIRULAIS, TOBY], total: 21, last: 2 })),
    });
    const before = api.calls.length;
    const entries = history.length;

    vi.useFakeTimers();
    fireEvent.change(searchInput(), { target: { value: 'L' } });
    fireEvent.change(searchInput(), { target: { value: 'La' } });
    fireEvent.change(searchInput(), { target: { value: 'Laura ' } });
    act(() => {
      vi.advanceTimersByTime(299);
    });
    expect(history.location.search).toBe('?pagina=2');
    act(() => {
      vi.advanceTimersByTime(1);
    });
    vi.useRealTimers();

    await waitFor(() => {
      expect(history.location.search).toBe('?buscar=Laura');
    });
    await waitFor(() => {
      expect(api.calls.length).toBe(before + 1);
    });
    expect(api.calls.at(-1)?.get('search')).toBe('Laura');
    expect(api.calls.at(-1)?.get('page')).toBe('1');
    expect(history.length).toBe(entries);
    // El espacio escrito al final sigue en el campo.
    expect(searchInput().value).toBe('Laura ');
  });

  it('la especie va a la URL y a la API, vuelve a la página 1 y "Atrás" la saca', async () => {
    const { api, history } = await openList('/pacientes?pagina=2', {
      api: patientsHandler(() => ({ items: [FIRULAIS, TOBY], total: 21, last: 2 })),
    });
    await waitFor(() => {
      expect(speciesSelect().options).toHaveLength(SPECIES.length + 1);
    });
    expect(Array.from(speciesSelect().options).map((option) => option.text)).toEqual([
      'Todas las especies',
      'Canino',
      'Felino',
      'Ave',
    ]);

    fireEvent.change(speciesSelect(), { target: { value: '4' } });

    await waitFor(() => {
      expect(history.location.search).toBe('?especie=4');
    });
    await waitFor(() => {
      expect(api.calls.at(-1)?.get('species_id')).toBe('4');
    });

    act(() => {
      history.back();
    });
    await waitFor(() => {
      expect(history.location.search).toBe('?pagina=2');
    });
  });

  it('"Incluir los fallecidos" va a la URL (fallecidos=1) y a la API (include_deceased=1); desmarcarlo lo saca', async () => {
    const { api, history } = await openList();

    fireEvent.click(deceasedBox());
    await waitFor(() => {
      expect(history.location.search).toBe('?fallecidos=1');
    });
    await waitFor(() => {
      expect(api.calls.at(-1)?.get('include_deceased')).toBe('1');
    });

    fireEvent.click(deceasedBox());
    await waitFor(() => {
      expect(history.location.search).toBe('');
    });
  });

  it('la paginación cambia la página de la URL', async () => {
    const { api, history } = await openList('/pacientes', {
      api: patientsHandler(() => ({ items: [FIRULAIS, TOBY], total: 41, last: 3 })),
    });

    fireEvent.click(screen.getByText('Siguiente', { selector: 'button' }));

    await waitFor(() => {
      expect(history.location.search).toBe('?pagina=2');
    });
    expect(api.calls.at(-1)?.get('page')).toBe('2');
  });

  it.each([
    ['/pacientes', 'Todavía no hay pacientes.'],
    ['/pacientes?buscar=zzz', 'No hay pacientes que coincidan con la búsqueda.'],
    ['/pacientes?especie=6', 'No hay pacientes que coincidan con la búsqueda.'],
  ])('vacío en %s: "%s"', async (url, message) => {
    server.use(
      meHandler(meFor('veterinario')),
      patientsHandler(() => ({ items: [] })).handler,
      speciesHandler,
    );
    renderApp(url, { session: sessionFor() });

    expect(await screen.findByText(message)).toBeInTheDocument();
  });

  it('un 403 FORBIDDEN de la API muestra la página de sin permiso con su mensaje', async () => {
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/patients', () =>
        failure(403, 'FORBIDDEN', 'No tenés permiso para ver los pacientes.'),
      ),
      speciesHandler,
    );
    renderApp('/pacientes', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver los pacientes.')).toBeInTheDocument();
  });

  it('si GET species falla, el listado sigue (el filtro, solo con "Todas las especies")', async () => {
    server.use(
      meHandler(meFor('recepcionista')),
      patientsHandler().handler,
      http.get('/api/species', () => failure(500, 'INTERNAL_SERVER_ERROR', 'Error.')),
    );
    renderApp('/pacientes', { session: sessionFor() });

    expect(await screen.findByText('Firulais', { selector: 'a' })).toBeInTheDocument();
    expect(speciesSelect().options).toHaveLength(1);
  });

  it('sin clients.show, el dueño no es un enlace; sin patients.show, el nombre tampoco', async () => {
    const permissions = ROLE_PERMISSIONS.recepcionista.filter(
      (permission) => permission !== 'clients.show' && permission !== 'patients.show',
    );
    server.use(
      meHandler(meFor('recepcionista', { permissions })),
      patientsHandler().handler,
      speciesHandler,
    );
    renderApp('/pacientes', { session: sessionFor() });

    expect(await screen.findByText('Laura Martínez', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Firulais', { selector: 'td' })).toBeInTheDocument();
  });

  it('sin patients.index, /pacientes va a /sin-permiso sin pedir el listado', async () => {
    const api = patientsHandler();
    const permissions = ROLE_PERMISSIONS.veterinario.filter(
      (permission) => permission !== 'patients.index',
    );
    server.use(meHandler(meFor('veterinario', { permissions })), api.handler, speciesHandler);
    const { history } = renderApp('/pacientes', { session: sessionFor() });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/sin-permiso');
    });
    expect(api.calls).toHaveLength(0);
  });
});
