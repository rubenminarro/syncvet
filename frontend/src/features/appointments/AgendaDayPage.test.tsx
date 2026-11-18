import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { pauseAutoRefresh } from '../../lib/hooks/auto-refresh';
import { success } from '../../test/api';
import { appointment, block } from '../../test/appointments';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { ROOM_ONE, scheduleListItem } from '../../test/schedules';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { ScheduleBlock, ScheduleDetail } from '../schedules/types';
import { authKeys } from '../auth/api';
import { MISSING_APPOINTMENTS_MESSAGE } from './AgendaDayPage';
import type { Appointment } from './types';

// El martes 17/11/2026 (weekday 2). Por selector, no por rol: con los íconos SVG del layout, *ByRole tarda en jsdom.

const TUESDAY = [
  { id: 11, weekday: 2 as const, starts_at: '08:00', ends_at: '12:00' },
  { id: 12, weekday: 2 as const, starts_at: '14:00', ends_at: '18:00' },
];
const ONE: ScheduleDetail = { ...ROOM_ONE, hours: TUESDAY };
const TWO: ScheduleDetail = {
  ...ROOM_ONE,
  id: 8,
  name: 'Consultorio 2',
  color: null,
  veterinarian: { id: 5, code: 'VET-0002', name: 'Valeria Ortiz' },
  hours: TUESDAY,
};
const GROOMING: ScheduleDetail = {
  ...ROOM_ONE,
  id: 9,
  name: 'Peluquería',
  veterinarian: null,
  hours: TUESDAY,
};
const SCHEDULES = [ONE, TWO, GROOMING];

const LUNA = appointment(1, { at: '09:00' });
const IN_BLOCK = appointment(2, {
  at: '10:00',
  patient: null,
  client: null,
  contact_name: 'Pedro Gómez',
});
const CANCELLED = appointment(3, { at: '11:00', status: 'cancelled' });
const NO_SHOW = appointment(4, { at: '08:00', status: 'no_show' });
const OTHER_ROOM = appointment(5, { at: '09:00', schedule: { id: 8, name: 'Consultorio 2' } });
const DEFAULT_APPOINTMENTS = [NO_SHOW, LUNA, OTHER_ROOM, IN_BLOCK, CANCELLED];

const BLOCKS: Record<number, ScheduleBlock[]> = { 7: [block(1, '10:00', '10:30', 'Capacitación')] };

interface Setup {
  me?: ReturnType<typeof meFor>;
  appointments?: (url: URL) => Appointment[];
  total?: number;
}

function handlers(urls: URL[], setup: Setup = {}) {
  const items = setup.appointments ?? (() => DEFAULT_APPOINTMENTS);

  return [
    meHandler(setup.me ?? meFor('recepcionista')),
    http.get('/api/schedules', ({ request }) => {
      urls.push(new URL(request.url));

      return success(SCHEDULES.map(scheduleListItem), { meta: pagination({ total: 3 }) });
    }),
    http.get('/api/schedules/:id', ({ params }) =>
      success(SCHEDULES.find((schedule) => String(schedule.id) === params.id)),
    ),
    http.get('/api/schedules/:id/blocks', ({ request, params }) => {
      urls.push(new URL(request.url));
      const list = BLOCKS[Number(params.id)] ?? [];

      return success(list, { meta: pagination({ total: list.length }) });
    }),
    http.get('/api/appointments', ({ request }) => {
      const url = new URL(request.url);
      urls.push(url);
      const list = items(url);

      return success(list, {
        meta: pagination({ total: setup.total ?? list.length, per_page: 500 }),
      });
    }),
  ];
}

const appointmentRequests = (urls: URL[]) =>
  urls.filter((url) => url.pathname === '/api/appointments');

async function openAgenda(url = '/agenda?fecha=2026-11-17', setup: Setup = {}) {
  const urls: URL[] = [];
  server.use(...handlers(urls, setup));
  const app = renderApp(url, { session: sessionFor() });
  await waitFor(() => {
    expect(document.querySelector('.agenda-column-name')).not.toBeNull();
  });

  return { ...app, urls };
}

const columnNames = () =>
  Array.from(document.querySelectorAll('.agenda-column-name')).map(
    (element) => element.textContent,
  );

const item = (id: number) => document.querySelector<HTMLElement>(`[data-key="a:${String(id)}"]`);

function checkbox(label: string): HTMLInputElement {
  const found = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.trim() === label,
  );
  const input =
    found?.querySelector('input') ??
    (found?.htmlFor ? document.getElementById(found.htmlFor) : null);

  if (!(input instanceof HTMLInputElement)) {
    throw new Error(`Sin la casilla ${label}`);
  }

  return input;
}

/** El elemento, o el test falla (sin aserciones de tipo). */
function element(found: Element | null | undefined): HTMLElement {
  if (!(found instanceof HTMLElement)) {
    throw new Error('Sin el elemento');
  }

  return found;
}

const active = () => element(document.activeElement);

const status = () =>
  document.querySelector('[role="status"][aria-live="polite"]')?.textContent ?? '';

afterEach(() => {
  vi.useRealTimers();
});

describe('/agenda: la grilla del día', () => {
  it('un solo pedido de turnos del día de la sucursal actual, con per_page 500', async () => {
    const { urls } = await openAgenda();

    await waitFor(() => {
      expect(appointmentRequests(urls)).toHaveLength(1);
    });
    const params = Object.fromEntries(appointmentRequests(urls)[0]?.searchParams ?? []);
    expect(params).toEqual({
      branch_id: '10',
      from: '2026-11-17',
      to: '2026-11-17',
      per_page: '500',
    });
    // Los bloqueos del día de cada agenda que se ve.
    const blocks = urls.filter((url) => url.pathname === '/api/schedules/7/blocks');
    expect(Object.fromEntries(blocks[0]?.searchParams ?? [])).toMatchObject({
      from: '2026-11-17',
      to: '2026-11-17',
    });
  });

  it('una columna por agenda activa de la sucursal, con su título y la fecha', async () => {
    await openAgenda();

    expect(columnNames()).toEqual(['Consultorio 1', 'Consultorio 2', 'Peluquería']);
    expect(document.querySelector('.agenda-title')?.textContent).toBe(
      'Martes 17 de noviembre de 2026',
    );
  });

  it('cada turno con su nombre accesible completo, en su columna', async () => {
    await openAgenda();

    await waitFor(() => {
      expect(item(1)?.getAttribute('aria-label')).toBe(
        '09:00 a 09:30, Luna, de Laura Martínez, Consulta, Confirmado, Consultorio 1',
      );
    });
    expect(item(5)?.getAttribute('aria-label')).toContain('Consultorio 2');
  });

  it('los cancelados ocultos; "Mostrar los cancelados" los muestra y queda en la URL', async () => {
    const { history } = await openAgenda();

    expect(item(3)).toBeNull();
    fireEvent.click(checkbox('Mostrar los cancelados'));

    await waitFor(() => {
      expect(item(3)).not.toBeNull();
    });
    expect(history.location.search).toContain('cancelados=1');
    expect(item(3)?.className).toContain('agenda-item-cancelled');
  });

  it('una agenda inactivada con turnos ese día suma su columna; con solo cancelados, solo al mostrarlos', async () => {
    const old = { id: 20, name: 'Consultorio viejo' };
    const { history } = await openAgenda('/agenda?fecha=2026-11-17', {
      appointments: () => [
        ...DEFAULT_APPOINTMENTS,
        appointment(30, { at: '15:00', status: 'cancelled', schedule: old }),
      ],
    });

    expect(columnNames()).toEqual(['Consultorio 1', 'Consultorio 2', 'Peluquería']);
    history.push('/agenda?fecha=2026-11-17&cancelados=1');

    await waitFor(() => {
      expect(columnNames()).toEqual([
        'Consultorio 1',
        'Consultorio 2',
        'Peluquería',
        'Consultorio viejo',
      ]);
    });
    expect(document.body.textContent).toContain('(inactivada)');
  });

  it('los ausentes, atenuados', async () => {
    await openAgenda();

    await waitFor(() => {
      expect(item(4)?.className).toContain('agenda-item-dimmed');
    });
    expect(item(1)?.className).not.toContain('agenda-item-dimmed');
  });

  it('el turno dentro de un bloqueo lleva la marca (texto e ícono); el bloqueo y lo de fuera del horario, sombreados', async () => {
    await openAgenda();

    await waitFor(() => {
      expect(item(2)?.textContent).toContain('Dentro de un bloqueo');
    });
    expect(item(2)?.getAttribute('aria-label')).toContain('dentro de un bloqueo');
    expect(item(1)?.textContent).not.toContain('Dentro de un bloqueo');
    expect(document.querySelectorAll('.agenda-blocked').length).toBeGreaterThan(0);
    expect(document.querySelector('.agenda-block-label')?.textContent).toContain('Capacitación');
    // 12:00 a 14:00 del martes, sin atención.
    const noon = document.querySelector('[data-key="c:16:0"]');
    expect(noon?.className).toContain('agenda-closed');
    expect(noon?.getAttribute('aria-label')).toBe('12:00, Consultorio 1, fuera del horario');
  });

  it('si el total supera lo traído, avisa que faltan turnos (nunca corta en silencio)', async () => {
    await openAgenda('/agenda?fecha=2026-11-17', { total: 612 });

    expect(await screen.findByText(MISSING_APPOINTMENTS_MESSAGE)).toBeTruthy();
    expect(document.querySelector('.agenda-missing')?.textContent).toContain(
      'Se muestran 5 de los 612 turnos del día.',
    );
  });

  it('sin el aviso cuando llegaron todos', async () => {
    await openAgenda();

    await waitFor(() => {
      expect(item(1)).not.toBeNull();
    });
    expect(document.querySelector('.agenda-missing')).toBeNull();
  });

  it('el filtro de agenda deja una sola columna y el de veterinario, las suyas', async () => {
    const { history } = await openAgenda('/agenda?fecha=2026-11-17&agenda=8');

    await waitFor(() => {
      expect(columnNames()).toEqual(['Consultorio 2']);
    });

    history.push('/agenda?fecha=2026-11-17&veterinario=4');
    await waitFor(() => {
      expect(columnNames()).toEqual(['Consultorio 1']);
    });
  });
});

describe('/agenda: el veterinario', () => {
  const VET = meFor('veterinario', { veterinarian: { id: 4, code: 'VET-0001' } });

  it('entra con su vista por defecto (sus agendas y las sin veterinario) y "Ver toda la sucursal" muestra todas', async () => {
    const { history } = await openAgenda('/agenda?fecha=2026-11-17', { me: VET });

    expect(columnNames()).toEqual(['Consultorio 1', 'Peluquería']);
    fireEvent.click(checkbox('Ver toda la sucursal'));

    await waitFor(() => {
      expect(columnNames()).toEqual(['Consultorio 1', 'Consultorio 2', 'Peluquería']);
    });
    expect(history.location.search).toContain('todos=1');
  });

  it('el recepcionista no tiene "Ver toda la sucursal" (ve todo)', async () => {
    await openAgenda();

    expect(() => checkbox('Ver toda la sucursal')).toThrow();
  });
});

describe('/agenda: la sucursal', () => {
  it('sin sucursal actual: el aviso del layout y ningún pedido de turnos', async () => {
    const urls: URL[] = [];
    server.use(...handlers(urls, { me: meFor('recepcionista', { current_branch: null }) }));
    renderApp('/agenda?fecha=2026-11-17', { session: sessionFor() });

    expect(
      await screen.findByText(
        'Elegí tu sucursal actual en la barra de arriba para empezar a trabajar.',
      ),
    ).toBeTruthy();
    expect(appointmentRequests(urls)).toHaveLength(0);
    expect(document.querySelector('[role="grid"]')).toBeNull();
  });

  it('al cambiar la sucursal, se vuelven a pedir los turnos de la nueva', async () => {
    const { urls, queryClient } = await openAgenda();
    const me = meFor('recepcionista', {
      current_branch: { id: 11, code: 'NOR', name: 'Sucursal Norte' },
      branches: [
        { id: 10, code: 'CEN', name: 'Casa central' },
        { id: 11, code: 'NOR', name: 'Sucursal Norte' },
      ],
    });

    act(() => {
      queryClient.setQueryData(authKeys.me, me);
    });

    await waitFor(() => {
      expect(appointmentRequests(urls).at(-1)?.searchParams.get('branch_id')).toBe('11');
    });
  });
});

describe('/agenda: el teclado y los anuncios', () => {
  it('un solo punto de tabulación; las flechas, Inicio y Fin recorren las celdas y los turnos', async () => {
    await openAgenda();
    await waitFor(() => {
      expect(item(4)).not.toBeNull();
    });
    const grid = document.querySelector<HTMLElement>('[role="grid"]');
    const tabbable = () => grid?.querySelectorAll('[tabindex="0"]') ?? [];

    expect(tabbable()).toHaveLength(1);
    const first = element(tabbable()[0]);
    expect(first.dataset.key).toBe('c:0:0');
    first.focus();

    // La celda de las 08:00 y, abajo, el ausente que empieza ahí.
    fireEvent.keyDown(active(), { key: 'ArrowDown' });
    expect(active().dataset.key).toBe('a:4');
    fireEvent.keyDown(active(), { key: 'ArrowDown' });
    expect(active().dataset.key).toBe('c:1:0');
    fireEvent.keyDown(active(), { key: 'ArrowRight' });
    expect(active().dataset.key).toBe('c:1:1');
    fireEvent.keyDown(active(), { key: 'End' });
    expect(active().dataset.key).toBe('c:1:2');
    fireEvent.keyDown(active(), { key: 'Home' });
    expect(active().dataset.key).toBe('c:1:0');
    fireEvent.keyDown(active(), { key: 'ArrowUp' });
    expect(active().dataset.key).toBe('a:4');
    fireEvent.keyDown(active(), { key: 'End', ctrlKey: true });
    expect(active().dataset.key).toBe(
      `c:${String(document.querySelectorAll('[role="row"]').length - 2)}:2`,
    );
    expect(tabbable()).toHaveLength(1);
  });

  it('Av Pág y Re Pág cambian de día (en la URL, como texto) y el cambio se anuncia', async () => {
    const { history } = await openAgenda();
    await waitFor(() => {
      expect(status()).toBe('Martes 17 de noviembre: 4 turnos.');
    });
    const cell = document.querySelector<HTMLElement>('[data-key="c:0:0"]');
    cell?.focus();

    fireEvent.keyDown(element(cell), { key: 'PageDown' });
    await waitFor(() => {
      expect(history.location.search).toBe('?fecha=2026-11-18');
    });
    await waitFor(() => {
      expect(status()).toBe('Miércoles 18 de noviembre: 4 turnos.');
    });

    fireEvent.keyDown(element(document.querySelector('[role="grid"]')), {
      key: 'PageUp',
    });
    await waitFor(() => {
      expect(history.location.search).toBe('?fecha=2026-11-17');
    });
  });

  it('un día sin agendas que mostrar no pierde el foco: va al aviso, que sigue con Re Pág', async () => {
    const urls: URL[] = [];
    // Sin agendas activas (como la demo): solo la columna de la agenda inactivada con turnos del 17.
    // Los primeros mandan: MSW toma el primer handler que coincide.
    server.use(
      http.get('/api/schedules', () => success([], { meta: pagination({ total: 0 }) })),
      http.get('/api/schedules/20', () => success({ ...ONE, id: 20, name: 'Consultorio viejo' })),
      ...handlers(urls, {
        appointments: (url) =>
          url.searchParams.get('from') === '2026-11-17'
            ? [appointment(40, { schedule: { id: 20, name: 'Consultorio viejo' } })]
            : [],
      }),
    );
    const { history } = renderApp('/agenda?fecha=2026-11-17', { session: sessionFor() });
    await waitFor(() => {
      expect(item(40)).not.toBeNull();
    });
    element(item(40)).focus();

    fireEvent.keyDown(active(), { key: 'PageDown' });
    const empty = await screen.findByText(
      'La sucursal no tiene agendas activas ni turnos este día.',
    );
    await waitFor(() => {
      expect(document.activeElement).toBe(empty);
    });

    fireEvent.keyDown(empty, { key: 'PageUp' });
    await waitFor(() => {
      expect(history.location.search).toBe('?fecha=2026-11-17');
    });
    await waitFor(() => {
      expect(item(40)).not.toBeNull();
    });
  });

  it('Enter en un turno abre sus datos en un panel de solo lectura; "Cerrar" devuelve el foco al turno', async () => {
    await openAgenda();
    await waitFor(() => {
      expect(item(2)).not.toBeNull();
    });

    item(2)?.focus();
    fireEvent.click(element(item(2)));

    const title = await screen.findByText('Turno de las 10:00', { selector: 'h3' });
    await waitFor(() => {
      expect(document.activeElement).toBe(title);
    });
    const panel = document.querySelector('.agenda-panel');
    expect(panel?.textContent).toContain('Pedro Gómez');
    expect(panel?.textContent).toContain('Dentro de un bloqueo');

    fireEvent.click(screen.getByText('Cerrar', { selector: '.agenda-panel button' }));
    expect(document.querySelector('.agenda-panel')).toBeNull();
    expect(document.activeElement).toBe(item(2));
  });
});

describe('/agenda: la actualización cada 60 s', () => {
  function hide(hidden: boolean) {
    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      get: () => (hidden ? 'hidden' : 'visible'),
    });
    document.dispatchEvent(new Event('visibilitychange'));
  }

  afterEach(() => {
    hide(false);
  });

  it('vuelve a pedir cada 60 s, sin anunciar nada ni mover el foco', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, toFake: ['setInterval', 'clearInterval'] });
    const { urls } = await openAgenda();
    await waitFor(() => {
      expect(status()).toBe('Martes 17 de noviembre: 4 turnos.');
    });
    const before = appointmentRequests(urls).length;
    const cell = document.querySelector<HTMLElement>('[data-key="c:2:1"]');
    cell?.focus();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });

    await waitFor(() => {
      expect(appointmentRequests(urls).length).toBe(before + 1);
    });
    expect(status()).toBe('Martes 17 de noviembre: 4 turnos.');
    expect(document.activeElement).toBe(cell);
  });

  it('en pausa con un diálogo o un formulario abierto, y sigue al cerrarlo', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, toFake: ['setInterval', 'clearInterval'] });
    const { urls } = await openAgenda();
    await waitFor(() => {
      expect(appointmentRequests(urls)).toHaveLength(1);
    });

    let release: () => void = () => undefined;
    act(() => {
      release = pauseAutoRefresh();
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(180_000);
    });
    expect(appointmentRequests(urls)).toHaveLength(1);

    act(() => {
      release();
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });
    await waitFor(() => {
      expect(appointmentRequests(urls)).toHaveLength(2);
    });
  });

  it('en pausa con la pestaña oculta', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, toFake: ['setInterval', 'clearInterval'] });
    const { urls } = await openAgenda();
    await waitFor(() => {
      expect(appointmentRequests(urls)).toHaveLength(1);
    });

    act(() => {
      hide(true);
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(180_000);
    });

    expect(appointmentRequests(urls)).toHaveLength(1);
  });

  it('si el turno con el foco desaparece con la actualización (otra PC lo canceló), el foco va a su celda', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, toFake: ['setInterval', 'clearInterval'] });
    let current = DEFAULT_APPOINTMENTS;
    await openAgenda('/agenda?fecha=2026-11-17', { appointments: () => current });
    await waitFor(() => {
      expect(item(1)).not.toBeNull();
    });
    // Luna empieza a las 09:00: la fila 4 de Consultorio 1.
    item(1)?.focus();
    fireEvent.focus(element(item(1)));

    current = DEFAULT_APPOINTMENTS.filter((entry) => entry.id !== 1);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });

    await waitFor(() => {
      expect(item(1)).toBeNull();
    });
    await waitFor(() => {
      expect(active().dataset.key).toBe('c:4:0');
    });
  });
});
