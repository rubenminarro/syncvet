import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { delay, http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { todayInAppZone } from '../../lib/format/dates';
import { success } from '../../test/api';
import { appointment, block } from '../../test/appointments';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { ROOM_ONE, scheduleListItem } from '../../test/schedules';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { ScheduleBlock, ScheduleDetail } from '../schedules/types';
import { SINGLE_COLUMN_QUERY } from './agenda-shared';
import { RANGE_TOO_LONG, TO_BEFORE_FROM } from './search';
import type { Appointment } from './types';

// La lista de turnos (/turnos, 3.3). Por selector, no por rol (los íconos SVG del layout hacen lento *ByRole en jsdom).

const TWO: ScheduleDetail = {
  ...ROOM_ONE,
  id: 8,
  name: 'Consultorio 2',
  color: null,
  veterinarian: { id: 5, code: 'VET-0002', name: 'Valeria Ortiz' },
};
const SCHEDULES = [ROOM_ONE, TWO];

// El martes 17/11/2026: uno dentro del bloqueo de 10:00 a 10:30 de Consultorio 1, uno que solo lo toca, un ausente y un
// cancelado (la lista los muestra).
const IN_BLOCK = appointment(1, { at: '10:00' });
const TOUCHING = appointment(2, {
  at: '10:30',
  patient: null,
  client: null,
  contact_name: 'Pedro Gómez',
});
const NO_SHOW = appointment(3, { at: '08:00', status: 'no_show' });
const CANCELLED = appointment(4, {
  at: '11:00',
  status: 'cancelled',
  schedule: { id: 8, name: 'Consultorio 2' },
});
const DEFAULT_APPOINTMENTS = [NO_SHOW, IN_BLOCK, TOUCHING, CANCELLED];

const BLOCKS: Record<number, ScheduleBlock[]> = { 7: [block(1, '10:00', '10:30', 'Capacitación')] };

interface Setup {
  appointments?: (url: URL) => Appointment[] | Promise<Appointment[]>;
  total?: number;
  page?: number;
}

function handlers(urls: URL[], setup: Setup = {}) {
  const items = setup.appointments ?? (() => DEFAULT_APPOINTMENTS);

  return [
    meHandler(meFor('recepcionista')),
    http.get('/api/schedules', () =>
      success(SCHEDULES.map(scheduleListItem), { meta: pagination({ total: 2 }) }),
    ),
    http.get('/api/schedules/:id/blocks', ({ request, params }) => {
      urls.push(new URL(request.url));
      const list = BLOCKS[Number(params.id)] ?? [];

      return success(list, { meta: pagination({ total: list.length }) });
    }),
    http.get('/api/appointments', async ({ request }) => {
      const url = new URL(request.url);
      urls.push(url);
      const list = await items(url);
      const page = Number(url.searchParams.get('page') ?? '1');

      return success(list, {
        meta: pagination({
          total: setup.total ?? list.length,
          per_page: 20,
          current_page: page,
          last_page: Math.max(1, Math.ceil((setup.total ?? list.length) / 20)),
        }),
      });
    }),
  ];
}

const appointmentRequests = (urls: URL[]) =>
  urls.filter((url) => url.pathname === '/api/appointments');

async function openList(url = '/turnos?desde=2026-11-17&hasta=2026-11-17', setup: Setup = {}) {
  const urls: URL[] = [];
  server.use(...handlers(urls, setup));
  const app = renderApp(url, { session: sessionFor() });
  await waitFor(() => {
    expect(
      document.querySelector('.appointment-open, .page-out-of-range, td.text-center'),
    ).not.toBeNull();
  });

  return { ...app, urls };
}

function field(label: string): HTMLInputElement | HTMLSelectElement {
  const found = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.trim() === label,
  );
  const control =
    found?.querySelector('input') ??
    (found?.htmlFor ? document.getElementById(found.htmlFor) : null);

  if (!(control instanceof HTMLInputElement) && !(control instanceof HTMLSelectElement)) {
    throw new Error(`Sin el campo ${label}`);
  }

  return control;
}

const rows = () => Array.from(document.querySelectorAll('tbody tr'));
const rowOf = (text: string) => rows().find((row) => row.textContent.includes(text));
const searchInput = () => {
  const input = document.querySelector('input[type="search"]');

  if (!(input instanceof HTMLInputElement)) {
    throw new Error('Sin la búsqueda');
  }

  return input;
};
const status = () =>
  document.querySelector('[role="status"][aria-live="polite"]')?.textContent ?? '';

function submitRange() {
  const form = document.querySelector('form.appointment-range');

  if (!(form instanceof HTMLFormElement)) {
    throw new Error('Sin el formulario de fechas');
  }

  fireEvent.submit(form);
}

afterEach(() => {
  vi.useRealTimers();
  Reflect.deleteProperty(window, 'matchMedia');
});

describe('/turnos: la lista', () => {
  it('pide la sucursal actual, el rango y la página, de a 20; sin fechas, hoy (lo que hace la API) en los campos', async () => {
    const { urls } = await openList('/turnos');

    expect(Object.fromEntries(appointmentRequests(urls)[0]?.searchParams ?? [])).toEqual({
      branch_id: '10',
      page: '1',
      per_page: '20',
    });
    expect(field('Desde').value).toBe(todayInAppZone());
    expect(field('Hasta').value).toBe(todayInAppZone());
  });

  it('una fila por turno, con la fecha, la agenda, quién, el servicio y el estado; también los cancelados', async () => {
    await openList();

    expect(rows()).toHaveLength(4);
    expect(rowOf('Pedro Gómez')?.textContent).toContain('17/11/2026 10:30–11:00');
    expect(rowOf('Cancelado')?.textContent).toContain('Consultorio 2');
    expect(rowOf('Ausente')?.className).toBe('appointment-row-dimmed');
    expect(rowOf('Pedro Gómez')?.className).toBe('');
  });

  it('el cancelado, tachado en la tabla como en las tarjetas (3.5), con su estado en ícono y texto', async () => {
    await openList();
    const cancelled = rowOf('Cancelado');

    expect(cancelled?.className).toBe('appointment-row-cancelled');
    const label = cancelled?.querySelector('.appointment-status-label');
    expect(label?.textContent).toBe('Cancelado');
    expect(label?.querySelector('svg')).not.toBeNull();
  });

  it('"Dentro de un bloqueo" con los bloqueos del rango de cada agenda; sin la marca, el que solo lo toca', async () => {
    const { urls } = await openList('/turnos?desde=2026-11-16&hasta=2026-11-22');

    await waitFor(() => {
      expect(rowOf('10:00–10:30')?.textContent).toContain('Dentro de un bloqueo');
    });
    expect(rowOf('Pedro Gómez')?.textContent).not.toContain('Dentro de un bloqueo');
    const blocks = urls.find((url) => url.pathname === '/api/schedules/7/blocks');
    expect(Object.fromEntries(blocks?.searchParams ?? [])).toMatchObject({
      from: '2026-11-16',
      to: '2026-11-22',
    });
  });

  it('los estados de la URL van a la API como status[], con los valores de la API', async () => {
    const { urls } = await openList('/turnos?desde=2026-11-17&estados=confirmado,fichado');

    const params = appointmentRequests(urls)[0]?.searchParams;
    expect(params?.getAll('status[]')).toEqual(['confirmed', 'checked_in']);
    expect(params?.get('status')).toBeNull();
    expect(params?.get('from')).toBe('2026-11-17');
    expect(params?.has('to')).toBe(false);
  });

  it('las casillas de los estados cambian la URL (una lista, en su orden) y vuelven a la página 1', async () => {
    const { history, urls } = await openList(
      '/turnos?desde=2026-11-17&hasta=2026-11-17&estados=fichado&pagina=2',
      { total: 30 },
    );

    fireEvent.click(field('Confirmado'));

    await waitFor(() => {
      expect(history.location.search).toBe(
        '?desde=2026-11-17&hasta=2026-11-17&estados=confirmado,fichado',
      );
    });
    await waitFor(() => {
      expect(appointmentRequests(urls).at(-1)?.searchParams.getAll('status[]')).toEqual([
        'confirmed',
        'checked_in',
      ]);
    });
    fireEvent.click(field('Confirmado'));
    await waitFor(() => {
      expect(history.location.search).toBe('?desde=2026-11-17&hasta=2026-11-17&estados=fichado');
    });
    fireEvent.click(field('Fichado'));
    await waitFor(() => {
      expect(history.location.search).toBe('?desde=2026-11-17&hasta=2026-11-17');
    });
  });

  it('dos casillas seguidas, antes de que llegue la primera navegación, no pierden ninguna', async () => {
    const { history } = await openList();

    fireEvent.click(field('Ausente'));
    fireEvent.click(field('Confirmado'));

    await waitFor(() => {
      expect(history.location.search).toBe(
        '?desde=2026-11-17&hasta=2026-11-17&estados=confirmado,ausente',
      );
    });
  });

  it('la agenda y el veterinario, como filtros de la API', async () => {
    const { history, urls } = await openList();

    fireEvent.change(field('Agenda'), { target: { value: '8' } });
    await waitFor(() => {
      expect(appointmentRequests(urls).at(-1)?.searchParams.get('schedule_id')).toBe('8');
    });
    fireEvent.change(field('Veterinario'), { target: { value: '5' } });
    await waitFor(() => {
      expect(appointmentRequests(urls).at(-1)?.searchParams.get('veterinarian_id')).toBe('5');
    });
    expect(history.location.search).toContain('agenda=8&veterinario=5');
  });

  it('sin resultados: "en estas fechas" sin filtros; con el filtro de veterinario, "que coincidan con los filtros"', async () => {
    const { history } = await openList('/turnos?desde=2026-11-17', { appointments: () => [] });

    expect(await screen.findByText('No hay turnos en estas fechas.')).toBeTruthy();

    history.push('/turnos?desde=2026-11-17&veterinario=5');

    expect(await screen.findByText('No hay turnos que coincidan con los filtros.')).toBeTruthy();
  });

  it('la paginación y la página que no existe', async () => {
    const { history, urls } = await openList('/turnos?desde=2026-11-17&pagina=2', {
      total: 30,
      appointments: (url) => (url.searchParams.get('page') === '9' ? [] : DEFAULT_APPOINTMENTS),
    });

    expect(appointmentRequests(urls)[0]?.searchParams.get('page')).toBe('2');
    history.push('/turnos?desde=2026-11-17&pagina=9');

    expect(await screen.findByText('Esta página no existe.', { exact: false })).toBeTruthy();
  });
});

describe('/turnos: el rango de fechas', () => {
  it('más de 31 días: el error de la API en "Hasta", antes de pedir, sin cambiar la URL', async () => {
    const { history, urls } = await openList();
    const before = appointmentRequests(urls).length;

    fireEvent.change(field('Desde'), { target: { value: '2026-11-01' } });
    fireEvent.change(field('Hasta'), { target: { value: '2026-12-02' } });
    submitRange();

    expect(await screen.findByText(RANGE_TOO_LONG)).toBeTruthy();
    expect(document.activeElement).toBe(field('Hasta'));
    expect(history.location.search).toBe('?desde=2026-11-17&hasta=2026-11-17');
    expect(appointmentRequests(urls)).toHaveLength(before);

    // 31 días contando los dos extremos: pasa.
    fireEvent.change(field('Hasta'), { target: { value: '2026-12-01' } });
    submitRange();
    await waitFor(() => {
      expect(history.location.search).toBe('?desde=2026-11-01&hasta=2026-12-01');
    });
    await waitFor(() => {
      expect(appointmentRequests(urls).at(-1)?.searchParams.get('to')).toBe('2026-12-01');
    });
    expect(screen.queryByText(RANGE_TOO_LONG)).toBeNull();
  });

  it('hasta anterior a desde: el error, sin pedir', async () => {
    const { urls } = await openList();
    const before = appointmentRequests(urls).length;

    fireEvent.change(field('Desde'), { target: { value: '2026-11-18' } });
    submitRange();

    expect(await screen.findByText(TO_BEFORE_FROM)).toBeTruthy();
    expect(appointmentRequests(urls)).toHaveLength(before);
  });

  it('un rango de 32 días escrito en la URL se descarta: queda el día de desde', async () => {
    const { urls } = await openList('/turnos?desde=2026-11-01&hasta=2026-12-02');

    const params = appointmentRequests(urls)[0]?.searchParams;
    expect(params?.get('from')).toBe('2026-11-01');
    expect(params?.has('to')).toBe(false);
  });
});

describe('/turnos: la búsqueda', () => {
  it('espera 300 ms, muestra "Buscando…" mientras llega y vuelve a la página 1', async () => {
    const { history, urls } = await openList('/turnos?desde=2026-11-17&pagina=2', {
      total: 30,
      appointments: async (url) => {
        if (url.searchParams.has('search')) {
          await delay(300);
        }

        return DEFAULT_APPOINTMENTS;
      },
    });
    const searches = () =>
      appointmentRequests(urls).filter((url) => url.searchParams.has('search'));

    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    fireEvent.change(searchInput(), { target: { value: 'luna' } });
    await act(() => vi.advanceTimersByTimeAsync(299));
    expect(history.location.search).toBe('?desde=2026-11-17&pagina=2');
    await act(() => vi.advanceTimersByTimeAsync(1));
    expect(history.location.search).toBe('?desde=2026-11-17&buscar=luna');
    vi.useRealTimers();

    expect(await screen.findByText('Buscando…')).toBeTruthy();
    await waitFor(() => {
      expect(searches()).toHaveLength(1);
    });
    expect(searches()[0]?.searchParams.get('search')).toBe('luna');
    expect(searches()[0]?.searchParams.get('page')).toBe('1');
    expect(searchInput().placeholder).toBe('Buscá por paciente, cliente, documento o contacto');
  });
});

describe('/turnos: el turno, las vistas y el anuncio', () => {
  it('un turno abre el panel con sus acciones de estado (3.5); "Cerrar" devuelve el foco', async () => {
    await openList();
    const opener = rowOf('Pedro Gómez')?.querySelector<HTMLButtonElement>('.appointment-open');
    opener?.focus();
    fireEvent.click(opener ?? document.body);

    await waitFor(() => {
      expect(document.activeElement?.id).toBe('agenda-panel-title');
    });
    const panel = document.querySelector('.agenda-panel');
    expect(panel?.textContent).toContain('Pedro Gómez');
    expect(panel?.querySelector('.appointment-transitions')).not.toBeNull();
    fireEvent.click(panel?.querySelector('.card-actions button') ?? document.body);
    expect(document.activeElement).toBe(opener);
  });

  it('"Día" y "Semana" van a la agenda con la fecha desde, la agenda y el veterinario', async () => {
    await openList('/turnos?desde=2026-11-18&hasta=2026-11-20&agenda=8&veterinario=5');

    expect(document.querySelector('a.agenda-view-dia')?.getAttribute('href')).toBe(
      '/agenda?agenda=8&veterinario=5&fecha=2026-11-18',
    );
    expect(document.querySelector('a.agenda-view-semana')?.getAttribute('href')).toBe(
      '/agenda?agenda=8&veterinario=5&vista=semana&fecha=2026-11-18',
    );
    expect(document.querySelector('a.agenda-view-lista')?.getAttribute('aria-current')).toBe(
      'page',
    );
  });

  it('el anuncio dice el rango y cuántos turnos hay', async () => {
    await openList('/turnos?desde=2026-11-16&hasta=2026-11-22', { total: 4 });

    await waitFor(() => {
      expect(status()).toBe('Del lunes 16 de noviembre al domingo 22 de noviembre: 4 turnos.');
    });
  });

  it('"Agenda" queda activa en el menú', async () => {
    await openList();

    const agenda = Array.from(document.querySelectorAll('a.nav-link')).find((element) =>
      element.textContent.includes('Agenda'),
    );
    expect(agenda?.className).toContain('active');
  });

  it('en el celular, tarjetas en lugar de la tabla, con la marca y la paginación', async () => {
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: (query: string) => ({
        matches: query === SINGLE_COLUMN_QUERY,
        media: query,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
      }),
    });
    const urls: URL[] = [];
    server.use(...handlers(urls));
    renderApp('/turnos?desde=2026-11-17', { session: sessionFor() });

    await waitFor(() => {
      expect(document.querySelectorAll('.appointment-card')).toHaveLength(4);
    });
    expect(document.querySelector('table')).toBeNull();
    // Los mismos estados que la tabla (3.5): el cancelado tachado, el ausente atenuado.
    const card = (status: string) =>
      document.querySelector(`.appointment-card[data-status="${status}"]`)?.className ?? '';
    expect(card('cancelled')).toContain('agenda-item-cancelled');
    expect(card('no_show')).toContain('agenda-item-dimmed');
    await waitFor(() => {
      expect(document.querySelector('.appointment-cards')?.textContent).toContain(
        'Dentro de un bloqueo',
      );
    });
    expect(document.querySelector('.pagination')).not.toBeNull();
  });
});

describe('/turnos: la actualización', () => {
  it('vuelve a pedir cada 60 s con la pestaña visible', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, toFake: ['setInterval', 'clearInterval'] });
    const { urls } = await openList();
    const before = appointmentRequests(urls).length;

    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });

    await waitFor(() => {
      expect(appointmentRequests(urls).length).toBe(before + 1);
    });
  });
});
