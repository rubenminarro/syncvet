import { act, fireEvent, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { pauseAutoRefresh } from '../../lib/hooks/auto-refresh';
import { todayInAppZone } from '../../lib/format/dates';
import { success } from '../../test/api';
import { appointment, block } from '../../test/appointments';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { ROOM_ONE, scheduleListItem } from '../../test/schedules';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { ScheduleBlock, ScheduleDetail } from '../schedules/types';
import { MISSING_APPOINTMENTS_MESSAGE, SINGLE_COLUMN_QUERY } from './agenda-shared';
import type { Appointment } from './types';

// La semana del lunes 16 al domingo 22/11/2026, desde el miércoles 18. Por selector, no por rol (los íconos SVG del layout
// hacen lento *ByRole en jsdom).

const WEEKDAYS_MORNING = [1, 2, 3, 4, 5].map((weekday) => ({
  id: weekday,
  weekday: weekday as 1 | 2 | 3 | 4 | 5,
  starts_at: '08:00',
  ends_at: '12:00',
}));
const ONE: ScheduleDetail = { ...ROOM_ONE, hours: WEEKDAYS_MORNING };
const TWO: ScheduleDetail = {
  ...ROOM_ONE,
  id: 8,
  name: 'Consultorio 2',
  color: null,
  veterinarian: { id: 5, code: 'VET-0002', name: 'Valeria Ortiz' },
  hours: WEEKDAYS_MORNING,
};
const GROOMING: ScheduleDetail = {
  ...ROOM_ONE,
  id: 9,
  name: 'Peluquería',
  veterinarian: null,
  hours: WEEKDAYS_MORNING,
};
const SCHEDULES = [ONE, TWO, GROOMING];

const MONDAY = appointment(1, { day: '2026-11-16', at: '09:00' });
// El martes 17, de 10:00 a 10:30, dentro del bloqueo de 10:00 a 10:30; el de las 10:30 solo lo toca.
const IN_BLOCK = appointment(2, {
  at: '10:00',
  patient: null,
  client: null,
  contact_name: 'Pedro Gómez',
});
const TOUCHING = appointment(3, { at: '10:30' });
const CANCELLED = appointment(4, { day: '2026-11-19', at: '11:00', status: 'cancelled' });
const NO_SHOW = appointment(5, { day: '2026-11-20', at: '08:00', status: 'no_show' });
const OTHER_ROOM = appointment(6, {
  day: '2026-11-18',
  at: '09:00',
  schedule: { id: 8, name: 'Consultorio 2' },
});
const DEFAULT_APPOINTMENTS = [MONDAY, IN_BLOCK, TOUCHING, CANCELLED, NO_SHOW, OTHER_ROOM];

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
    http.get('/api/schedules', () =>
      success(SCHEDULES.map(scheduleListItem), { meta: pagination({ total: 3 }) }),
    ),
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

async function openWeek(url = '/agenda?vista=semana&fecha=2026-11-18', setup: Setup = {}) {
  const urls: URL[] = [];
  server.use(...handlers(urls, setup));
  const app = renderApp(url, { session: sessionFor() });
  await waitFor(() => {
    expect(document.querySelector('.agenda-column-name, .agenda-week-day')).not.toBeNull();
  });

  return { ...app, urls };
}

const headings = () =>
  Array.from(document.querySelectorAll('.agenda-column-name')).map(
    (element) => element.textContent,
  );

const item = (id: number) => document.querySelector<HTMLElement>(`[data-key="a:${String(id)}"]`);

const status = () =>
  document.querySelector('[role="status"][aria-live="polite"]')?.textContent ?? '';

function select(label: string): HTMLSelectElement {
  const found = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.trim() === label,
  );
  const control = found?.htmlFor ? document.getElementById(found.htmlFor) : null;

  if (!(control instanceof HTMLSelectElement)) {
    throw new Error(`Sin el selector ${label}`);
  }

  return control;
}

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

function link(className: string): HTMLAnchorElement {
  const found = document.querySelector(`a.${className}`);

  if (!(found instanceof HTMLAnchorElement)) {
    throw new Error(`Sin el enlace ${className}`);
  }

  return found;
}

/** El "celular": la consulta de una sola columna responde `matches` (jsdom no tiene medidas). */
function narrowScreen() {
  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    value: (query: string) => ({
      matches: query === SINGLE_COLUMN_QUERY,
      media: query,
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
    }),
  });
}

afterEach(() => {
  vi.useRealTimers();
  Reflect.deleteProperty(window, 'matchMedia');
});

describe('/agenda?vista=semana: la semana de una agenda', () => {
  it('un solo pedido de la semana (de lunes a domingo) de la sucursal actual, con per_page 500, y los bloqueos de la semana', async () => {
    const { urls } = await openWeek();

    await waitFor(() => {
      expect(appointmentRequests(urls)).toHaveLength(1);
    });
    expect(Object.fromEntries(appointmentRequests(urls)[0]?.searchParams ?? [])).toEqual({
      branch_id: '10',
      from: '2026-11-16',
      to: '2026-11-22',
      per_page: '500',
    });
    await waitFor(() => {
      expect(urls.some((url) => url.pathname === '/api/schedules/7/blocks')).toBe(true);
    });
    const blocks = urls.find((url) => url.pathname === '/api/schedules/7/blocks');
    expect(Object.fromEntries(blocks?.searchParams ?? [])).toMatchObject({
      from: '2026-11-16',
      to: '2026-11-22',
    });
    // Solo los de la agenda que se ve.
    expect(urls.some((url) => url.pathname === '/api/schedules/8/blocks')).toBe(false);
  });

  it('siete columnas de lunes a domingo, el título de la semana y la primera agenda por defecto', async () => {
    await openWeek();

    expect(headings()).toEqual([
      'Lun 16',
      'Mar 17',
      'Mié 18',
      'Jue 19',
      'Vie 20',
      'Sáb 21',
      'Dom 22',
    ]);
    expect(document.querySelector('.agenda-title')?.textContent).toBe(
      'Semana del 16 al 22 de noviembre de 2026',
    );
    expect(select('Agenda').value).toBe('7');
    expect(Array.from(select('Agenda').options).map((option) => option.text)).toEqual([
      'Consultorio 1',
      'Consultorio 2',
      'Peluquería',
    ]);
  });

  it('desde el domingo, la misma semana (empieza el lunes, no el domingo)', async () => {
    const { urls } = await openWeek('/agenda?vista=semana&fecha=2026-11-22');

    await waitFor(() => {
      expect(appointmentRequests(urls)[0]?.searchParams.get('from')).toBe('2026-11-16');
    });
    expect(headings()[0]).toBe('Lun 16');
  });

  it('cada turno en su día, con el nombre accesible con la agenda y el día', async () => {
    await openWeek();

    await waitFor(() => {
      expect(item(1)?.getAttribute('aria-label')).toBe(
        '09:00 a 09:30, Luna, de Laura Martínez, Consulta, Confirmado, Consultorio 1, lunes 16 de noviembre',
      );
    });
    // El de Consultorio 2 no está en la semana de Consultorio 1.
    expect(item(6)).toBeNull();
    expect(document.querySelector('[role="grid"]')?.getAttribute('aria-label')).toBe(
      'Semana del 16 al 22 de noviembre de 2026, Consultorio 1',
    );
  });

  it('el selector cambia la agenda (en la URL) y muestra sus turnos', async () => {
    const { history } = await openWeek();

    fireEvent.change(select('Agenda'), { target: { value: '8' } });

    await waitFor(() => {
      expect(item(6)).not.toBeNull();
    });
    expect(history.location.search).toContain('agenda=8');
    expect(item(1)).toBeNull();
  });

  it('"Dentro de un bloqueo" en el que se superpone; sin la marca, el que solo lo toca', async () => {
    await openWeek();

    await waitFor(() => {
      expect(item(2)?.textContent).toContain('Dentro de un bloqueo');
    });
    expect(item(2)?.getAttribute('aria-label')).toContain('dentro de un bloqueo');
    expect(item(3)?.textContent).not.toContain('Dentro de un bloqueo');
    expect(document.querySelector('.agenda-blocked')).not.toBeNull();
  });

  it('los cancelados ocultos hasta "Mostrar los cancelados"; los ausentes, atenuados', async () => {
    const { history } = await openWeek();

    await waitFor(() => {
      expect(item(5)).not.toBeNull();
    });
    expect(item(5)?.className).toContain('agenda-item-dimmed');
    expect(item(4)).toBeNull();
    fireEvent.click(checkbox('Mostrar los cancelados'));

    await waitFor(() => {
      expect(item(4)).not.toBeNull();
    });
    expect(history.location.search).toContain('cancelados=1');
    expect(item(4)?.className).toContain('agenda-item-cancelled');
  });

  it('si el total supera lo traído, avisa que faltan turnos', async () => {
    await openWeek('/agenda?vista=semana&fecha=2026-11-18', { total: 620 });

    expect(document.querySelector('.agenda-missing')?.textContent).toContain(
      MISSING_APPOINTMENTS_MESSAGE,
    );
    expect(document.querySelector('.agenda-missing')?.textContent).toContain(
      'Se muestran 6 de los 620 turnos de la semana.',
    );
  });

  it('sin el aviso cuando llegaron todos', async () => {
    await openWeek();

    expect(document.querySelector('.agenda-missing')).toBeNull();
  });
});

describe('/agenda?vista=semana: el veterinario', () => {
  const VET = meFor('veterinario', { veterinarian: { id: 5, code: 'VET-0002' } });

  it('entra con la primera de su vista por defecto (sus agendas y las sin veterinario); "Ver toda la sucursal", con todas', async () => {
    const { history } = await openWeek('/agenda?vista=semana&fecha=2026-11-18', { me: VET });

    expect(select('Agenda').value).toBe('8');
    expect(Array.from(select('Agenda').options).map((option) => option.text)).toEqual([
      'Consultorio 2',
      'Peluquería',
    ]);
    fireEvent.click(checkbox('Ver toda la sucursal'));

    await waitFor(() => {
      expect(select('Agenda').value).toBe('7');
    });
    expect(history.location.search).toContain('todos=1');
  });
});

describe('/agenda?vista=semana: navegar', () => {
  it('Av Pág y Re Pág cambian de semana (la fecha en la URL, como texto) y se anuncia', async () => {
    const { history, urls } = await openWeek();

    await waitFor(() => {
      expect(status()).toBe('Semana del 16 al 22 de noviembre de 2026, Consultorio 1: 4 turnos.');
    });
    const grid = document.querySelector<HTMLElement>('[role="grid"]');
    const first = grid?.querySelector<HTMLElement>('[tabindex="0"]');
    first?.focus();
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'PageDown' });

    await waitFor(() => {
      expect(history.location.search).toBe('?vista=semana&fecha=2026-11-25');
    });
    await waitFor(() => {
      expect(status()).toBe('Semana del 23 al 29 de noviembre de 2026, Consultorio 1: sin turnos.');
    });
    expect(appointmentRequests(urls).at(-1)?.searchParams.get('from')).toBe('2026-11-23');
    fireEvent.keyDown(document.querySelector('[role="grid"]') ?? document.body, { key: 'PageUp' });

    await waitFor(() => {
      expect(history.location.search).toBe('?vista=semana&fecha=2026-11-18');
    });
  });

  it('un solo punto de tabulación en la grilla de la semana', async () => {
    await openWeek();

    await waitFor(() => {
      expect(item(1)).not.toBeNull();
    });
    expect(document.querySelectorAll('[role="grid"] [tabindex="0"]')).toHaveLength(1);
  });

  it('semana anterior, siguiente y "Esta semana"', async () => {
    const { history } = await openWeek();

    fireEvent.click(document.querySelector<HTMLElement>('.agenda-next') ?? document.body);
    await waitFor(() => {
      expect(history.location.search).toContain('fecha=2026-11-25');
    });
    fireEvent.click(document.querySelector<HTMLElement>('.agenda-previous') ?? document.body);
    await waitFor(() => {
      expect(history.location.search).toContain('fecha=2026-11-18');
    });
    fireEvent.click(document.querySelector<HTMLElement>('.agenda-previous') ?? document.body);
    await waitFor(() => {
      expect(history.location.search).toContain('fecha=2026-11-11');
    });
    fireEvent.click(document.querySelector<HTMLElement>('.agenda-today') ?? document.body);
    await waitFor(() => {
      expect(history.location.search).toContain(`fecha=${todayInAppZone()}`);
    });
  });

  it('pasar al día y a la lista conserva la fecha, la agenda y el veterinario', async () => {
    await openWeek('/agenda?vista=semana&fecha=2026-11-18&agenda=8&veterinario=5&cancelados=1');

    expect(link('agenda-view-dia').getAttribute('href')).toBe(
      '/agenda?agenda=8&veterinario=5&cancelados=1&fecha=2026-11-18',
    );
    expect(link('agenda-view-semana').getAttribute('aria-current')).toBe('page');
    expect(link('agenda-view-semana').className).toContain('btn-primary');
    // El día no se ve marcado aunque sea la misma ruta.
    expect(link('agenda-view-dia').className.split(' ')).not.toContain('active');
    expect(link('agenda-view-dia').className).toContain('btn-outline-primary');
    expect(link('agenda-view-lista').getAttribute('href')).toBe(
      '/turnos?desde=2026-11-16&hasta=2026-11-22&agenda=8&veterinario=5',
    );
  });

  it('desde el día, "Semana" conserva la fecha y la agenda', async () => {
    const urls: URL[] = [];
    server.use(...handlers(urls));
    const { history } = renderApp('/agenda?fecha=2026-11-18&agenda=7', { session: sessionFor() });
    await waitFor(() => {
      expect(document.querySelector('a.agenda-view-semana')).not.toBeNull();
    });

    fireEvent.click(link('agenda-view-semana'));

    await waitFor(() => {
      expect(history.location.search).toBe('?agenda=7&vista=semana&fecha=2026-11-18');
    });
    await waitFor(() => {
      expect(headings()).toHaveLength(7);
    });
  });

  it('un turno abre el panel de solo lectura con la fecha; "Cerrar" devuelve el foco', async () => {
    await openWeek();
    await waitFor(() => {
      expect(item(1)).not.toBeNull();
    });
    const opener = item(1);
    opener?.focus();
    fireEvent.click(opener ?? document.body);

    await waitFor(() => {
      expect(document.activeElement?.id).toBe('agenda-panel-title');
    });
    expect(document.querySelector('.agenda-panel')?.textContent).toContain(
      'Lunes 16 de noviembre de 2026',
    );
    expect(document.querySelector('.agenda-panel button')?.textContent).toBe('Cerrar');
    fireEvent.click(document.querySelector('.agenda-panel button') ?? document.body);
    expect(document.activeElement).toBe(opener);
  });
});

describe('/agenda?vista=semana: en el celular', () => {
  it('una lista por día, sin la grilla, con los turnos, el bloqueo y los días sin atención', async () => {
    narrowScreen();
    await openWeek();

    await waitFor(() => {
      expect(document.querySelectorAll('.agenda-week-day')).toHaveLength(7);
    });
    expect(document.querySelector('[role="grid"]')).toBeNull();
    const days = Array.from(document.querySelectorAll('.agenda-week-day')).map(
      (h) => h.textContent,
    );
    expect(days[0]).toBe('Lunes 16 de noviembre');
    expect(days[6]).toBe('Domingo 22 de noviembre');
    await waitFor(() => {
      expect(item(2)?.textContent).toContain('Dentro de un bloqueo');
    });
    expect(document.querySelector('.agenda-week-block')?.textContent).toBe(
      'Bloqueado de 10:00 a 10:30: Capacitación',
    );
    expect(document.body.textContent).toContain('Sin atención.');
    expect(item(5)?.className).toContain('agenda-item-dimmed');
    fireEvent.click(item(1) ?? document.body);

    await waitFor(() => {
      expect(document.querySelector('.agenda-panel')).not.toBeNull();
    });
  });
});

describe('/agenda?vista=semana: la actualización cada 60 s', () => {
  it('vuelve a pedir cada 60 s sin anunciar nada; en pausa con un diálogo abierto', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, toFake: ['setInterval', 'clearInterval'] });
    const { urls } = await openWeek();
    await waitFor(() => {
      expect(status()).toContain('Consultorio 1: 4 turnos.');
    });
    const before = appointmentRequests(urls).length;

    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });
    await waitFor(() => {
      expect(appointmentRequests(urls).length).toBe(before + 1);
    });
    expect(status()).toBe('Semana del 16 al 22 de noviembre de 2026, Consultorio 1: 4 turnos.');

    const release = pauseAutoRefresh();
    await act(async () => {
      await vi.advanceTimersByTimeAsync(180_000);
    });
    expect(appointmentRequests(urls).length).toBe(before + 1);
    act(() => {
      release();
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });
    await waitFor(() => {
      expect(appointmentRequests(urls).length).toBe(before + 2);
    });
  });
});
