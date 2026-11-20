import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { appointment } from '../../test/appointments';
import { pagination } from '../../test/clients';
import { listPatient } from '../../test/patients';
import { meFor } from '../../test/roles';
import { ROOM_ONE, scheduleListItem } from '../../test/schedules';
import { server } from '../../test/server';
import { serviceListItem, VACCINE } from '../../test/services';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { Me } from '../auth/types';
import type { ScheduleDetail } from '../schedules/types';
import { SCOPE_NOTICE } from './AppointmentActions';
import { SERIES_EDIT_NOTICE } from './AppointmentForm';
import { SERIES_OVERBOOK_QUESTION } from './AppointmentFormPages';
import { SERIES_CONFLICT_HINTS, SERIES_MESSAGES } from './series';
import type { Appointment, AppointmentDetail } from './types';

// Las series (3.6): el alta con "Repetir" y su vista previa, los conflictos, la serie en el detalle y en la lista, y
// cancelar con alcance, con MSW. "Ahora" es el martes 17/11/2026 a las 10:00 de Asunción (solo `Date` falso). Por selector,
// no por rol (los íconos SVG del layout hacen lento *ByRole en jsdom).

const NOW = new Date('2026-11-17T13:00:00Z');
const SERIES = '9b2f1c4e-7f1a-4c55-a9a3-1f1e2d3c4b5a';
const LUNA = listPatient({
  id: 9,
  code: 'PAC-0009',
  name: 'Luna',
  client: { id: 3, display_name: 'Laura Martínez' },
});
const ONE: ScheduleDetail = {
  ...ROOM_ONE,
  hours: [{ id: 11, weekday: 2, starts_at: '08:00', ends_at: '18:00' }],
};

function detail(base: Appointment, overrides: Partial<AppointmentDetail> = {}): AppointmentDetail {
  return {
    ...base,
    notes: null,
    created_by: { id: 3, name: 'Ana Gómez' },
    confirmed_at: null,
    checked_in_at: null,
    attended_at: null,
    no_show_at: null,
    cancelled_at: null,
    cancelled_by: null,
    cancellation_reason: null,
    created_at: '2026-11-10T12:00:00.000000Z',
    ...overrides,
  };
}

const inSeries = (id: number, day: string, at = '09:30', status = 'confirmed') =>
  appointment(id, { day, at, status, series_id: SERIES });

/**
 * La serie: el 1 (hoy, ya empezó), el 6 (hoy a las 15:00, sin empezar), el 2 (el que se abre), el 3, el 4 (cancelado) y el 5.
 * Con "Este y los siguientes" desde el 2: el 2, el 3 y el 5; con "Toda la serie", además el 6.
 */
const SERIES_TURNS: Appointment[] = [
  inSeries(1, '2026-11-17', '09:00'),
  inSeries(6, '2026-11-17', '15:00', 'pending_confirmation'),
  inSeries(2, '2026-11-24'),
  inSeries(3, '2026-12-01', '09:30', 'pending_confirmation'),
  inSeries(4, '2026-12-08', '09:30', 'cancelled'),
  inSeries(5, '2026-12-15'),
];

type Responder = (body: Record<string, unknown>) => Response | Promise<Response>;

interface Setup {
  me?: Me;
  responses?: Responder[];
  current?: AppointmentDetail;
  cancel?: Responder;
}

interface Recorded {
  bodies: Record<string, unknown>[];
  actions: Record<string, unknown>[];
  lists: URL[];
}

function seriesCreated(body: Record<string, unknown>) {
  const recurrence = body.recurrence as { count: number | null };
  const count = recurrence.count ?? 3;
  const items = Array.from({ length: count }, (_, index) =>
    appointment(201 + index, { day: '2026-11-24', series_id: SERIES }),
  );

  return success(items, {
    status: 201,
    message: `Serie de ${String(count)} turnos creada correctamente.`,
    meta: { series_id: SERIES },
  });
}

function handlers(recorded: Recorded, setup: Setup) {
  const responses = setup.responses ?? [];

  return [
    meHandler(setup.me ?? meFor('recepcionista')),
    http.get('/api/schedules', () =>
      success([ROOM_ONE].map(scheduleListItem), { meta: pagination({ total: 1 }) }),
    ),
    http.get('/api/schedules/:id', () => success(ONE)),
    http.get('/api/services', () =>
      success([VACCINE].map(serviceListItem), { meta: pagination({ total: 1 }) }),
    ),
    http.get('/api/schedules/:id/availability', ({ request, params }) => {
      const url = new URL(request.url);
      const date = url.searchParams.get('from') ?? '2026-11-24';

      return success({
        schedule_id: Number(params.id),
        duration_minutes: 30,
        days: [
          {
            date,
            slots: ['09:00', '09:30'].map((clock) => {
              const start = new Date(`${date}T${clock}:00-03:00`).getTime();

              return {
                starts_at: new Date(start).toISOString(),
                ends_at: new Date(start + 30 * 60_000).toISOString(),
              };
            }),
          },
        ],
      });
    }),
    http.get('/api/schedules/:id/blocks', () => success([], { meta: pagination({ total: 0 }) })),
    http.get('/api/patients', () => success([LUNA], { meta: pagination({ total: 1 }) })),
    http.get('/api/appointments', ({ request }) => {
      const url = new URL(request.url);
      recorded.lists.push(url);
      const list = url.searchParams.has('series_id') ? SERIES_TURNS : [];

      return success(list, { meta: pagination({ total: list.length }) });
    }),
    http.get('/api/appointments/:id', ({ params }) => {
      const id = Number(params.id);

      return success(
        setup.current?.id === id
          ? setup.current
          : detail(SERIES_TURNS.find((item) => item.id === id) ?? inSeries(id, '2026-11-24')),
      );
    }),
    http.post('/api/appointments', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      recorded.bodies.push(body);
      const responder = responses[Math.min(recorded.bodies.length - 1, responses.length - 1)];

      return responder === undefined ? seriesCreated(body) : responder(body);
    }),
    http.post('/api/appointments/:id/cancel', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      recorded.actions.push(body);

      if (setup.cancel !== undefined) {
        return setup.cancel(body);
      }

      const current = setup.current ?? detail(inSeries(2, '2026-11-24'));

      return success(
        { ...current, status: { value: 'cancelled', label: 'Cancelado' } },
        {
          message:
            body.scope === undefined || body.scope === 'single'
              ? 'Turno cancelado.'
              : 'Se cancelaron 3 turnos de la serie.',
          meta: { cancelled_count: 3 },
        },
      );
    }),
  ];
}

function open(url: string, setup: Setup = {}) {
  const recorded: Recorded = { bodies: [], actions: [], lists: [] };
  server.use(...handlers(recorded, setup));
  const app = renderApp(url, { session: sessionFor() });

  return { ...app, ...recorded };
}

function labelled(text: string): HTMLInputElement {
  const label = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.trim() === text,
  );
  const target = label?.htmlFor ? document.getElementById(label.htmlFor) : null;

  if (target === null) {
    throw new Error(`Sin el campo ${text}`);
  }

  return target as HTMLInputElement;
}

const findField = (text: string) => waitFor(() => labelled(text));

const fieldError = (text: string) =>
  document.getElementById(`${labelled(text).id}-error`)?.textContent ?? null;

const button = (text: string, root: ParentNode = document) =>
  Array.from(root.querySelectorAll<HTMLElement>('button, a')).find(
    (element) => element.textContent.trim() === text,
  );

function present<T>(found: T | null | undefined): T {
  if (found === null || found === undefined) {
    throw new Error('Sin el elemento');
  }

  return found;
}

const findButton = (text: string, root?: () => ParentNode | null) =>
  waitFor(() => present(button(text, root?.() ?? document)));

const dialog = () => document.querySelector<HTMLElement>('[role="dialog"]');
const toastText = () =>
  Array.from(document.querySelectorAll('.toast')).map((element) => element.textContent);
const preview = () =>
  Array.from(document.querySelectorAll('.appointment-series-preview li')).map(
    (element) => element.textContent,
  );
const summary = () => document.querySelector('.appointment-series-summary')?.textContent ?? '';

/** El alta desde una celda (agenda 7, el martes 24/11 a las 09:30), con Luna y "Repetir" marcado. */
async function newSeries(setup: Setup = {}) {
  const app = open('/turnos/nuevo?agenda=7&inicio=2026-11-24T09:30', setup);
  await findField('Agenda');
  fireEvent.change(labelled('Buscar el paciente'), { target: { value: 'Luna' } });
  fireEvent.click(await findButton('Luna (PAC-0009)de Laura Martínez'));
  await screen.findByText('Luna (PAC-0009)');
  fireEvent.click(labelled('Repetir el turno (una serie)'));
  await findField('Cantidad de turnos');

  return app;
}

async function submit() {
  fireEvent.click(
    await findButton('Dar el turno', () => document.querySelector('.appointment-form')),
  );
}

const conflict = (reasons: string[]) => () =>
  failure(
    409,
    'APPOINTMENT_SERIES_CONFLICT',
    'Hay fechas de la serie que no se pueden dar: revisá la lista.',
    {
      errors: {
        conflicts: reasons.map((reason, index) => ({
          // El martes 01/12 y el 08/12 a las 09:30 de Asunción (12:30 UTC).
          starts_at: `2026-12-${String(1 + 7 * index).padStart(2, '0')}T12:30:00.000000Z`,
          reason,
          message:
            reason === 'overlap'
              ? 'El horario ya está ocupado: elegí otro o dalo como sobreturno.'
              : 'La agenda está bloqueada en ese horario.',
        })),
      },
    },
  );

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'], now: NOW });
});

afterEach(() => {
  vi.useRealTimers();
});

describe('el alta de una serie', () => {
  it('la vista previa en Asunción, el cuerpo con recurrence y, con el 201, al primer turno con el mensaje de la API', async () => {
    const app = await newSeries();
    fireEvent.change(labelled('Cantidad de turnos'), { target: { value: '4' } });

    await waitFor(() => {
      expect(summary()).toBe('4 turnos, los martes a las 09:30, hasta el 15/12/2026.');
    });
    expect(preview()).toEqual([
      'Martes 24 de noviembre de 2026, 09:30 a 10:00',
      'Martes 1 de diciembre de 2026, 09:30 a 10:00',
      'Martes 8 de diciembre de 2026, 09:30 a 10:00',
      'Martes 15 de diciembre de 2026, 09:30 a 10:00',
    ]);

    await submit();

    await waitFor(() => {
      expect(toastText()).toContain('Serie de 4 turnos creada correctamente.');
    });
    expect(app.bodies).toHaveLength(1);
    expect(app.bodies[0]).toMatchObject({
      starts_at: '2026-11-24T09:30:00.000-03:00',
      recurrence: { every_weeks: 1, count: 4, until: null },
    });
    expect(app.bodies[0]).not.toHaveProperty('overbook');
    await waitFor(() => {
      expect(app.history.location.pathname).toBe('/turnos/201');
    });
  });

  it('cada 2 semanas hasta una fecha: la fecha de fin entra', async () => {
    const app = await newSeries();
    fireEvent.change(labelled('Cada cuántas semanas'), { target: { value: '2' } });
    fireEvent.click(present(document.querySelector<HTMLInputElement>('input[value="until"]')));
    fireEvent.change(await findField('Hasta'), { target: { value: '2026-12-22' } });

    await waitFor(() => {
      expect(summary()).toBe(
        '3 turnos, cada 2 semanas, los martes a las 09:30, hasta el 22/12/2026.',
      );
    });
    await submit();

    await waitFor(() => {
      expect(app.bodies[0]?.recurrence).toEqual({
        every_weeks: 2,
        count: null,
        until: '2026-12-22',
      });
    });
  });

  it('las reglas de la API antes de pedir: 53 turnos, sin pedido y con el mensaje en el campo', async () => {
    const app = await newSeries();
    fireEvent.change(labelled('Cantidad de turnos'), { target: { value: '53' } });
    await submit();

    await waitFor(() => {
      expect(fieldError('Cantidad de turnos')).toBe(SERIES_MESSAGES.tooMany);
    });
    expect(app.bodies).toHaveLength(0);
    expect(preview()).toEqual([]);
  });

  it('el 422 de recurrence va a su campo', async () => {
    await newSeries({
      responses: [
        () =>
          failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
            errors: { 'recurrence.count': [SERIES_MESSAGES.countTooFar] },
          }),
      ],
    });
    await submit();

    await waitFor(() => {
      expect(fieldError('Cantidad de turnos')).toBe(SERIES_MESSAGES.countTooFar);
    });
  });
});

describe('los conflictos de la serie', () => {
  const conflictItems = () =>
    Array.from(document.querySelectorAll('.appointment-series-conflicts li')).map(
      (element) => element.textContent,
    );

  it('todas superposiciones, con el permiso: la lista por fecha en Asunción y "Dar la serie con sobreturnos"', async () => {
    const app = await newSeries({ responses: [conflict(['overlap', 'overlap']), seriesCreated] });
    await submit();

    await waitFor(() => {
      expect(conflictItems()).toEqual([
        'Martes 1 de diciembre de 2026, 09:30: El horario ya está ocupado: elegí otro o dalo como sobreturno.',
        'Martes 8 de diciembre de 2026, 09:30: El horario ya está ocupado: elegí otro o dalo como sobreturno.',
      ]);
    });
    // El foco, en la alerta con la lista.
    await waitFor(() => {
      expect(document.activeElement?.classList.contains('appointment-alert')).toBe(true);
    });

    fireEvent.click(await findButton('Dar la serie con sobreturnos'));
    await waitFor(() => {
      expect(dialog()?.textContent).toContain(SERIES_OVERBOOK_QUESTION);
    });
    fireEvent.click(present(button('Dar la serie con sobreturnos', present(dialog()))));

    await waitFor(() => {
      expect(app.bodies).toHaveLength(2);
    });
    expect(app.bodies[1]).toMatchObject({ overbook: true, recurrence: { count: 4 } });
  });

  it('con una fecha en un bloqueo: sin el sobreturno, corregir o cancelar', async () => {
    await newSeries({ responses: [conflict(['overlap', 'in_block'])] });
    await submit();

    await waitFor(() => {
      expect(document.querySelector('.appointment-series-conflict-hint')?.textContent).toBe(
        SERIES_CONFLICT_HINTS.fix,
      );
    });
    expect(button('Dar la serie con sobreturnos')).toBeUndefined();
  });

  it('la vista previa marca las fechas que chocan, con ícono y el motivo, y anuncia cuántas son', async () => {
    await newSeries({ responses: [conflict(['overlap', 'in_block'])] });
    fireEvent.change(labelled('Cantidad de turnos'), { target: { value: '4' } });
    await submit();

    await waitFor(() => {
      expect(summary()).toBe(
        '4 turnos, los martes a las 09:30, hasta el 15/12/2026. 2 de las 4 fechas no se pueden dar: están marcadas en la lista.',
      );
    });
    expect(preview()).toEqual([
      'Martes 24 de noviembre de 2026, 09:30 a 10:00',
      'Martes 1 de diciembre de 2026, 09:30 a 10:00 No se puede dar: El horario ya está ocupado: elegí otro o dalo como sobreturno.',
      'Martes 8 de diciembre de 2026, 09:30 a 10:00 No se puede dar: La agenda está bloqueada en ese horario.',
      'Martes 15 de diciembre de 2026, 09:30 a 10:00',
    ]);
    const marked = document.querySelectorAll(
      '.appointment-series-preview li.appointment-series-conflict',
    );
    expect(marked).toHaveLength(2);
    // No solo color: cada una con su ícono (oculto al lector, que lee el texto).
    marked.forEach((item) => {
      expect(item.querySelector('svg[aria-hidden="true"]')).not.toBeNull();
    });

    // Cada 2 semanas: del 409 queda solo el 08/12 (el instante); el 01/12 ya no está en la serie.
    fireEvent.change(labelled('Cada cuántas semanas'), { target: { value: '2' } });
    await waitFor(() => {
      expect(summary()).toContain('1 de las 4 fechas no se puede dar: está marcada en la lista.');
    });
    expect(
      document.querySelectorAll('.appointment-series-preview li.appointment-series-conflict'),
    ).toHaveLength(1);
  });

  it('sin appointments.overbook: el motivo, sin el botón', async () => {
    const me = meFor('recepcionista');
    await newSeries({
      me: meFor('recepcionista', {
        permissions: me.permissions.filter((permission) => permission !== 'appointments.overbook'),
      }),
      responses: [conflict(['overlap'])],
    });
    await submit();

    await waitFor(() => {
      expect(document.querySelector('.appointment-series-conflict-hint')?.textContent).toBe(
        SERIES_CONFLICT_HINTS.noPermission,
      );
    });
    expect(button('Dar la serie con sobreturnos')).toBeUndefined();
  });
});

describe('la serie en el detalle y en la lista', () => {
  it('el detalle: "Turno 3 de 6", el enlace a la serie y la serie entera en un pedido sin rango', async () => {
    const app = open('/turnos/2');
    await screen.findByText('Datos del turno');

    await waitFor(() => {
      expect(document.querySelector('.appointment-series')?.textContent).toContain(
        'Turno 3 de 6 de una serie.',
      );
    });
    const link = present(document.querySelector<HTMLAnchorElement>('.appointment-series-link'));
    expect(link.getAttribute('href')).toBe(`/turnos?serie=${SERIES}`);
    const seriesGet = present(app.lists.find((url) => url.searchParams.has('series_id')));
    expect(seriesGet.searchParams.has('from')).toBe(false);
    expect(seriesGet.searchParams.has('to')).toBe(false);
  });

  it('la lista de la serie: series_id sin rango ni sucursal, sin "Desde" ni "Hasta", con la marca "Serie"', async () => {
    const app = open(`/turnos?serie=${SERIES}`);

    await waitFor(() => {
      expect(document.querySelectorAll('.agenda-item-series-flag').length).toBe(
        SERIES_TURNS.length,
      );
    });
    const url = present(app.lists.at(-1));
    expect(url.searchParams.get('series_id')).toBe(SERIES);
    expect(['from', 'to', 'branch_id'].filter((key) => url.searchParams.has(key))).toEqual([]);
    expect(document.querySelector('.appointment-range')).toBeNull();
    expect(button('Quitar la serie')).toBeDefined();
  });

  it('la edición de un turno de una serie: el aviso y sin "Repetir"', async () => {
    open('/turnos/2/editar');
    await findField('Agenda');

    expect(document.querySelector('.appointment-series-single')?.textContent).toBe(
      SERIES_EDIT_NOTICE,
    );
    expect(document.querySelector('.appointment-series')).toBeNull();
    expect(screen.queryByText('Repetir el turno (una serie)')).toBeNull();
  });
});

describe('cancelar con alcance', () => {
  const scopeInput = (value: string) =>
    present(
      document.querySelector<HTMLInputElement>(`.appointment-cancel-scope input[value="${value}"]`),
    );
  const count = () => document.querySelector('.appointment-cancel-scope-count')?.textContent ?? '';

  async function openCancel(setup: Setup = {}) {
    const app = open('/turnos/2', setup);
    await screen.findByText('Datos del turno');
    fireEvent.click(
      await findButton('Cancelar el turno', () =>
        document.querySelector('.appointment-transitions'),
      ),
    );
    await waitFor(() => {
      expect(dialog()).not.toBeNull();
    });
    fireEvent.change(labelled('Motivo de la cancelación'), { target: { value: 'Viaje' } });

    return app;
  }

  it('"Este y los siguientes": cuántos turnos se cancelan, el alcance en el cuerpo y el mensaje de la API', async () => {
    const app = await openCancel();

    expect(dialog()?.textContent).toContain(SCOPE_NOTICE);
    expect(scopeInput('single').checked).toBe(true);
    fireEvent.click(scopeInput('following'));

    await waitFor(() => {
      expect(count()).toBe('Se cancelan 3 turnos: este y 2 más de la serie.');
    });
    fireEvent.click(present(button('Cancelar 3 turnos', present(dialog()))));

    await waitFor(() => {
      expect(app.actions).toEqual([{ cancellation_reason: 'Viaje', scope: 'following' }]);
    });
    await waitFor(() => {
      expect(toastText()).toContain('Se cancelaron 3 turnos de la serie.');
    });
  });

  it('"Toda la serie" suma los anteriores que no empezaron', async () => {
    const app = await openCancel();
    fireEvent.click(scopeInput('series'));

    await waitFor(() => {
      expect(count()).toBe('Se cancelan 4 turnos: este y 3 más de la serie.');
    });
    fireEvent.click(present(button('Cancelar 4 turnos', present(dialog()))));

    await waitFor(() => {
      expect(app.actions[0]?.scope).toBe('series');
    });
  });

  it('"Solo este turno" manda scope single', async () => {
    const app = await openCancel();
    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));

    await waitFor(() => {
      expect(app.actions).toEqual([{ cancellation_reason: 'Viaje', scope: 'single' }]);
    });
  });

  it('después de "Toda la serie", solo los detalles en caché de los otros turnos de la serie se vuelven a pedir', async () => {
    const app = await openCancel();
    const key = (id: number) => ['appointments', 'detail', id];
    // En caché: otro turno de la serie y uno sin serie (como si se hubieran abierto antes).
    app.queryClient.setQueryData(key(3), detail(inSeries(3, '2026-12-01')));
    app.queryClient.setQueryData(key(99), detail(appointment(99, { day: '2026-12-01' })));
    fireEvent.click(scopeInput('series'));
    await waitFor(() => {
      expect(count()).toContain('Se cancelan 4 turnos');
    });
    fireEvent.click(present(button('Cancelar 4 turnos', present(dialog()))));

    await waitFor(() => {
      expect(app.queryClient.getQueryState(key(3))?.isInvalidated).toBe(true);
    });
    expect(app.queryClient.getQueryState(key(99))?.isInvalidated).toBe(false);
    // El pedido llega en la respuesta: se fija, no se vuelve a pedir.
    expect(app.queryClient.getQueryState(key(2))?.isInvalidated).toBe(false);
  });

  it('con "Solo este turno", los otros turnos de la serie en caché no se tocan', async () => {
    const app = await openCancel();
    const key = ['appointments', 'detail', 3];
    app.queryClient.setQueryData(key, detail(inSeries(3, '2026-12-01')));
    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));

    await waitFor(() => {
      expect(toastText()).toContain('Turno cancelado.');
    });
    expect(app.queryClient.getQueryState(key)?.isInvalidated).toBe(false);
  });

  it('un turno sin serie: sin el alcance ni el pedido de la serie, y el cuerpo sin scope', async () => {
    const single = detail(appointment(2, { day: '2026-11-24' }));
    const app = await openCancel({ current: single });

    expect(document.querySelector('.appointment-cancel-scope')).toBeNull();
    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));

    await waitFor(() => {
      expect(app.actions).toEqual([{ cancellation_reason: 'Viaje' }]);
    });
    expect(app.lists.some((url) => url.searchParams.has('series_id'))).toBe(false);
  });

  it('el 422 de la API con alcance: el mensaje en el diálogo y el turno vuelto a pedir', async () => {
    const app = await openCancel({
      cancel: () =>
        failure(
          422,
          'APPOINTMENT_INVALID_TRANSITION',
          'El turno está «Fichado» y no admite ese cambio.',
        ),
    });
    fireEvent.click(scopeInput('following'));
    await waitFor(() => {
      expect(count()).toContain('Se cancelan 3 turnos');
    });
    const seriesGets = () => app.lists.filter((url) => url.searchParams.has('series_id')).length;
    const before = seriesGets();
    fireEvent.click(present(button('Cancelar 3 turnos', present(dialog()))));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('El turno está «Fichado» y no admite ese cambio.');
    });
    // La serie (los demás turnos pudieron cambiar) se vuelve a pedir.
    await waitFor(() => {
      expect(seriesGets()).toBeGreaterThan(before);
    });
  });
});
