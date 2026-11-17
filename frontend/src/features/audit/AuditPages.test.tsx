import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import {
  auditListItem,
  CLIENT_UPDATED,
  LOGIN,
  PATIENT_DELETED,
  USER_CREATED_LEAKY,
} from '../../test/audit';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { ADMIN_USER, RECEPTION_USER, userListItem } from '../../test/users';
import { SEARCH_DEBOUNCE_MS } from '../../lib/hooks/use-search-text';
import { AUDIT_TO_BEFORE_FROM_MESSAGE } from './dates';
import { AUDIT_TYPE_REQUIRED_MESSAGE } from './filters';
import type { AuditLog } from './types';

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
// Los tests corren con la PC en UTC: las fechas mostradas y mandadas tienen que salir en la hora de Asunción.

const ADMIN = meFor('administrador');

const input = (label: string) => screen.getByLabelText<HTMLInputElement>(label);
const select = (label: string) => screen.getByLabelText<HTMLSelectElement>(label);

function type(label: string, value: string) {
  fireEvent.change(input(label), { target: { value } });
}

const filter = () => {
  fireEvent.click(screen.getByText('Filtrar', { selector: 'button' }));
};

const fieldError = (label: string) =>
  document.getElementById(`${input(label).id}-error`)?.textContent ?? null;

/** GET audit-logs: guarda los parámetros de cada pedido y responde con `rows` (o con lo que diga `respond`). */
function listHandler(rows: AuditLog[], respond?: (url: URL) => Response) {
  const calls: URLSearchParams[] = [];
  const handler = http.get('/api/audit-logs', ({ request }) => {
    const url = new URL(request.url);
    calls.push(url.searchParams);

    return respond?.(url) ?? success(rows, { meta: pagination({ total: rows.length }) });
  });

  return { calls, handler };
}

/** GET users?include_inactive=1&per_page=100: el selector del usuario. */
const usersHandler = () =>
  http.get('/api/users', () =>
    success([userListItem(ADMIN_USER), { ...userListItem(RECEPTION_USER), is_active: false }], {
      meta: pagination({ total: 2 }),
    }),
  );

async function openList(url = '/configuracion/auditoria', rows = [auditListItem(CLIENT_UPDATED)]) {
  const api = listHandler(rows);
  server.use(meHandler(ADMIN), usersHandler(), api.handler);
  const app = renderApp(url, { session: sessionFor() });
  await screen.findByText('Auditoría', { selector: 'h2' });

  return { ...app, api };
}

/** GET audit-logs/{id} con los registros de `logs`; otro id, 404. */
function detailHandler(...logs: (typeof CLIENT_UPDATED)[]) {
  return http.get('/api/audit-logs/:id', ({ params }) => {
    const log = logs.find((item) => String(item.id) === String(params.id));

    return log === undefined
      ? failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.')
      : success(log);
  });
}

async function openDetail(log: typeof CLIENT_UPDATED) {
  server.use(meHandler(ADMIN), detailHandler(log));
  renderApp(`/configuracion/auditoria/${String(log.id)}`, { session: sessionFor() });
  await screen.findByText(`Registro de auditoría n.º ${String(log.id)}`, { selector: 'h2' });
}

/** Las filas de la tabla de los cambios: [campo, ...valores], como texto. */
const changeRows = () =>
  Array.from(document.querySelectorAll('.audit-changes tbody tr')).map((row) =>
    Array.from(row.querySelectorAll('td')).map((cell) => cell.textContent.trim()),
  );

const changeHeaders = () =>
  Array.from(document.querySelectorAll('.audit-changes thead th')).map((cell) =>
    cell.textContent.trim(),
  );

afterEach(() => {
  vi.useRealTimers();
});

describe('/configuracion: la tarjeta de Auditoría', () => {
  it('el administrador la ve y lleva al listado', async () => {
    const api = listHandler([]);
    server.use(meHandler(ADMIN), usersHandler(), api.handler);
    const { history } = renderApp('/configuracion', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Auditoría', { selector: 'span' }));

    expect(await screen.findByText('Auditoría', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/configuracion/auditoria');
  });

  it.each(['recepcionista', 'veterinario'] as const)(
    '%s: sin la ruta del listado ni la del detalle (a /sin-permiso), sin pedir la auditoría',
    async (role) => {
      const api = listHandler([]);
      server.use(meHandler(meFor(role)), api.handler, detailHandler(CLIENT_UPDATED));
      const { history } = renderApp('/configuracion/auditoria?desde=2026-11-09', {
        session: sessionFor(),
      });

      expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
      expect(history.location.pathname).toBe('/sin-permiso');

      act(() => {
        history.push('/configuracion/auditoria/41');
      });
      await waitFor(() => {
        expect(history.location.pathname).toBe('/sin-permiso');
      });
      expect(api.calls).toHaveLength(0);
    },
  );
});

describe('/configuracion/auditoria: listado', () => {
  it('la fila: la fecha en Asunción, el usuario, el evento, el registro y la IP; sin usuario ni IP, la raya', async () => {
    await openList('/configuracion/auditoria', [
      auditListItem(CLIENT_UPDATED),
      auditListItem(USER_CREATED_LEAKY),
    ]);

    // 16:30:05 UTC son las 13:30 de Asunción; 02:59:59 UTC del 10, las 23:59 del 9.
    expect(await screen.findByText('09/11/2026 13:30', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('09/11/2026 23:59', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('Ana Gómez', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('Modificado', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Cliente n.º 12', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('Usuario n.º 8', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('127.0.0.1', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getAllByText('—', { selector: 'td' })).toHaveLength(2);
    // Solo lectura: ni alta, ni edición, ni baja.
    expect(screen.queryByText(/Nuevo|Editar|Eliminar/, { selector: 'a, button' })).toBeNull();
  });

  it('una fecha sola en "desde" y "hasta": el día completo de Asunción en UTC, la URL con la fecha tal cual', async () => {
    const { api, history } = await openList();

    type('Desde', '2026-11-09');
    type('Hasta', '2026-11-09');
    filter();

    await waitFor(() => {
      expect(api.calls.at(-1)?.get('to')).toBe('2026-11-10T02:59:59.000Z');
    });
    expect(api.calls.at(-1)?.get('from')).toBe('2026-11-09T03:00:00.000Z');
    expect(history.location.search).toBe('?desde=2026-11-09&hasta=2026-11-09');
  });

  it('con hora: "desde" con :00 y "hasta" con :59, en UTC', async () => {
    const { api, history } = await openList();

    type('Desde', '2026-11-09');
    type('Hora desde', '13:00');
    type('Hasta', '2026-11-09');
    type('Hora hasta', '18:00');
    filter();

    await waitFor(() => {
      expect(api.calls.at(-1)?.get('to')).toBe('2026-11-09T21:00:59.000Z');
    });
    expect(api.calls.at(-1)?.get('from')).toBe('2026-11-09T16:00:00.000Z');
    expect(history.location.search).toBe('?desde=2026-11-09T13%3A00&hasta=2026-11-09T18%3A00');
  });

  it('"hasta" antes de "desde": el mensaje de la API en el campo, con el foco, sin cambiar la URL ni pedir', async () => {
    const { api, history } = await openList();
    const before = api.calls.length;

    type('Desde', '2026-11-09');
    type('Hora desde', '18:00');
    type('Hasta', '2026-11-09');
    type('Hora hasta', '17:59');
    filter();

    await waitFor(() => {
      expect(fieldError('Hasta')).toBe(AUDIT_TO_BEFORE_FROM_MESSAGE);
    });
    expect(input('Hasta')).toHaveFocus();
    expect(history.location.search).toBe('');
    expect(api.calls).toHaveLength(before);
  });

  it('el registro sin el tipo: el mensaje de la API, sin pedir; con el tipo, los dos en el pedido', async () => {
    const { api, history } = await openList();
    const before = api.calls.length;

    type('N.º de registro', '12');
    filter();

    await waitFor(() => {
      expect(fieldError('N.º de registro')).toBe(AUDIT_TYPE_REQUIRED_MESSAGE);
    });
    expect(api.calls).toHaveLength(before);

    fireEvent.change(select('Tipo de registro'), { target: { value: 'client' } });
    await waitFor(() => {
      expect(history.location.search).toBe('?entidad=client');
    });
    type('N.º de registro', '12');
    filter();

    await waitFor(() => {
      expect(api.calls.at(-1)?.get('auditable_id')).toBe('12');
    });
    expect(api.calls.at(-1)?.get('auditable_type')).toBe('client');
  });

  it('los filtros de la URL van al pedido; un usuario que la lista no trae, como "Usuario n.º …"; los campos, con lo de la URL', async () => {
    const { api } = await openList(
      '/configuracion/auditoria?usuario=99&evento=updated&entidad=client&registro=12&desde=2026-11-09T13:00&pagina=2',
    );

    await waitFor(() => {
      expect(api.calls.length).toBeGreaterThan(0);
    });
    expect(Object.fromEntries(api.calls[0] ?? [])).toEqual({
      from: '2026-11-09T16:00:00.000Z',
      user_id: '99',
      event: 'updated',
      auditable_type: 'client',
      auditable_id: '12',
      page: '2',
      per_page: '20',
    });
    expect(await screen.findByText('Usuario n.º 99', { selector: 'option' })).toBeInTheDocument();
    expect(screen.getByText('Rosa Benítez (inactivo)', { selector: 'option' })).toBeInTheDocument();
    expect(select('Usuario').value).toBe('99');
    expect(input('Desde').value).toBe('2026-11-09');
    expect(input('Hora desde').value).toBe('13:00');
    expect(input('N.º de registro').value).toBe('12');
  });

  it('los enlaces de una fila filtran por su usuario y por su registro', async () => {
    const { api, history } = await openList('/configuracion/auditoria?evento=updated');

    fireEvent.click(await screen.findByText('Ana Gómez', { selector: 'a' }));
    await waitFor(() => {
      expect(history.location.search).toBe('?evento=updated&usuario=3');
    });

    fireEvent.click(screen.getByText('Cliente n.º 12', { selector: 'a' }));
    await waitFor(() => {
      expect(api.calls.at(-1)?.get('auditable_id')).toBe('12');
    });
    expect(api.calls.at(-1)?.get('auditable_type')).toBe('client');
    expect(api.calls.at(-1)?.get('user_id')).toBe('3');
  });

  it('la búsqueda: 299 ms sin URL ni pedido; a los 300 ms, la URL (con replace y en la página 1) y el pedido', async () => {
    const { api, history } = await openList('/configuracion/auditoria?pagina=2');
    await screen.findByText('Ana Gómez', { selector: 'a' });
    const entries = history.length;
    const searches = () => api.calls.filter((params) => params.has('search'));

    vi.useFakeTimers();
    fireEvent.change(input('Buscar en la auditoría'), { target: { value: '1e3 ' } });
    await act(() => vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS - 1));
    expect(history.location.search).toBe('?pagina=2');
    expect(searches()).toHaveLength(0);

    await act(() => vi.advanceTimersByTimeAsync(1));
    // El texto queda como texto (no el número 1000), sin el espacio del final.
    expect(history.location.search).toBe('?buscar=1e3');
    expect(history.length).toBe(entries);
    await act(() => vi.advanceTimersByTimeAsync(2000));
    vi.useRealTimers();

    await waitFor(() => {
      expect(searches()).toHaveLength(1);
    });
    expect(api.calls.at(-1)?.get('search')).toBe('1e3');
    expect(api.calls.at(-1)?.get('page')).toBe('1');
  });

  it('una página fuera de rango: "Esta página no existe." con el enlace a la página 1', async () => {
    const api = listHandler([], () =>
      success([], { meta: pagination({ total: 30, current_page: 9, last_page: 2 }) }),
    );
    server.use(meHandler(ADMIN), usersHandler(), api.handler);
    renderApp('/configuracion/auditoria?pagina=9&evento=login', { session: sessionFor() });

    expect(await screen.findByText('Esta página no existe.')).toBeInTheDocument();
    expect(screen.getByText('Ir a la página 1', { selector: 'a' }).getAttribute('href')).toBe(
      '/configuracion/auditoria?evento=login',
    );
  });
});

describe('/configuracion/auditoria/$id: detalle', () => {
  it('un cambio: la fecha con segundos en Asunción, los datos y los campos con el nombre de la API, antes y después', async () => {
    await openDetail(CLIENT_UPDATED);

    const data = document.querySelector('.audit-data')?.textContent ?? '';
    expect(data).toContain('09/11/2026 13:30:05');
    expect(data).toContain('Ana Gómez');
    expect(data).toContain('Modificado');
    expect(data).toContain('Cliente n.º 12');
    expect(data).toContain('Edg/142.0');
    expect(changeHeaders()).toEqual(['Campo', 'Antes', 'Después']);
    expect(changeRows()).toEqual([
      ['notes', 'Llamar a la tarde.', 'Prefiere WhatsApp.\nNo llamar.'],
      ['phone', '+595981400101', '(vacío)'],
    ]);
  });

  it('las contraseñas, los tokens y las rutas que la API dejara pasar no se muestran; el HTML se ve como texto', async () => {
    await openDetail(USER_CREATED_LEAKY);

    const page = document.body.textContent;
    expect(page).not.toContain('hash-que-no-tiene-que-verse');
    expect(page).not.toContain('token-que-no-tiene-que-verse');
    expect(page).not.toContain('clinics/1/');
    // Un alta: solo "después".
    expect(changeHeaders()).toEqual(['Campo', 'Después']);
    expect(changeRows()).toEqual([
      ['first_name', '<img src=x onerror=alert(1)>'],
      ['email', 'nuevo@example.com'],
      ['password', '********'],
      ['remember_token', '********'],
      ['photo_path', '(archivo)'],
      ['branch_ids', '[\n  10,\n  11\n]'],
      ['settings', '{\n  "logo": "(archivo)",\n  "theme": "claro"\n}'],
    ]);
    // El texto con HTML no se interpretó: ninguna imagen en la tabla.
    expect(document.querySelector('.audit-changes img')).toBeNull();
    expect(document.querySelector('.audit-changes pre')).not.toBeNull();
    // Sin usuario ni IP ni navegador: la raya.
    expect(document.querySelector('.audit-data')?.textContent).toContain('Usuario—');
  });

  it('una baja: solo "antes"; un ingreso: el aviso sin tabla', async () => {
    server.use(meHandler(ADMIN), detailHandler(PATIENT_DELETED, LOGIN));
    const { history } = renderApp('/configuracion/auditoria/43', { session: sessionFor() });
    await screen.findByText('Registro de auditoría n.º 43', { selector: 'h2' });

    expect(changeHeaders()).toEqual(['Campo', 'Antes']);
    expect(changeRows()).toEqual([
      ['name', 'Firulais'],
      ['photo_path', '(archivo)'],
    ]);

    act(() => {
      history.push('/configuracion/auditoria/44');
    });
    expect(
      await screen.findByText('Este registro no guarda cambios de datos.'),
    ).toBeInTheDocument();
    expect(document.querySelector('.audit-changes')).toBeNull();
  });

  it('un id que no existe o que no es un número: la página de no encontrado', async () => {
    server.use(meHandler(ADMIN), detailHandler(CLIENT_UPDATED));
    const { history } = renderApp('/configuracion/auditoria/999', { session: sessionFor() });

    expect(
      await screen.findByText('No encontramos este registro de auditoría'),
    ).toBeInTheDocument();

    act(() => {
      history.push('/configuracion/auditoria/abc');
    });
    expect(
      await screen.findByText('No encontramos este registro de auditoría'),
    ).toBeInTheDocument();
  });

  it('desde el listado y de vuelta, con los filtros', async () => {
    const { history } = await openList('/configuracion/auditoria?evento=updated');
    server.use(detailHandler(CLIENT_UPDATED));

    fireEvent.click(await screen.findByText('09/11/2026 13:30', { selector: 'a' }));
    await screen.findByText('Registro de auditoría n.º 41', { selector: 'h2' });
    fireEvent.click(screen.getByText('Auditoría', { selector: 'a' }));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/auditoria');
    });
    expect(history.location.search).toBe('?evento=updated');
  });
});
