import { fireEvent, screen, waitFor } from '@testing-library/react';
import { delay, http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { MESSAGES } from '../../lib/api/errors';
import { todayInAppZone } from '../../lib/format/dates';
import { failure, success } from '../../test/api';
import { appointment, block } from '../../test/appointments';
import { pagination } from '../../test/clients';
import { listPatient } from '../../test/patients';
import { meFor } from '../../test/roles';
import { ROOM_ONE, scheduleListItem } from '../../test/schedules';
import { server } from '../../test/server';
import { serviceListItem, VACCINE } from '../../test/services';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { Me } from '../auth/types';
import type { ScheduleBlock, ScheduleDetail } from '../schedules/types';
import { addDaysToDay } from './dates';
import { OVERBOOK_QUESTION, RELOADED_MESSAGE } from './AppointmentFormPages';
import type { AppointmentDetail } from './types';

// El alta, la edición, la reprogramación y el detalle del turno (3.4). Mañana en Asunción: un día que no pasó. Por
// selector, no por rol (los íconos SVG del layout hacen lento *ByRole en jsdom).

const DAY = addDaysToDay(todayInAppZone(), 1);
const TWO: ScheduleDetail = {
  ...ROOM_ONE,
  id: 8,
  name: 'Consultorio 2',
  service: null,
  slot_minutes: 20,
};
const CONSULTA = { ...VACCINE, id: 5, code: 'SRV-0005', name: 'Consulta', duration_minutes: 45 };
const LUNA = listPatient({
  id: 9,
  code: 'PAC-0009',
  name: 'Luna',
  client: { id: 3, display_name: 'Laura Martínez' },
});
const SLOTS = ['09:00', '09:30', '10:00'];

function detail(overrides: Partial<AppointmentDetail> = {}): AppointmentDetail {
  return {
    ...appointment(1, { at: '09:30', until: '10:00', day: DAY }),
    service: { id: 3, code: 'SRV-0001', name: 'Vacuna 5+1' },
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

/** Lo que responde el alta o la edición, en orden (el último se repite). */
type Responder = (body: Record<string, unknown>) => Response | Promise<Response>;

interface Setup {
  me?: Me;
  responses?: Responder[];
  current?: AppointmentDetail;
  blocks?: ScheduleBlock[];
}

interface Recorded {
  bodies: Record<string, unknown>[];
  availability: URL[];
}

function saved(body: Record<string, unknown>, status: number) {
  return success(detail({ id: 101, is_overbooked: body.overbook === true }), {
    status,
    message: status === 201 ? 'Turno creado correctamente.' : 'Turno actualizado correctamente.',
  });
}

function handlers(recorded: Recorded, setup: Setup) {
  const responses = setup.responses ?? [];
  const respond = async (request: Request, status: number) => {
    const body = (await request.json()) as Record<string, unknown>;
    recorded.bodies.push(body);
    const responder = responses[Math.min(recorded.bodies.length - 1, responses.length - 1)];

    return responder === undefined ? saved(body, status) : responder(body);
  };

  return [
    meHandler(setup.me ?? meFor('recepcionista')),
    http.get('/api/schedules', () =>
      success([ROOM_ONE, TWO].map(scheduleListItem), { meta: pagination({ total: 2 }) }),
    ),
    http.get('/api/services', () =>
      success([VACCINE, CONSULTA].map(serviceListItem), { meta: pagination({ total: 2 }) }),
    ),
    http.get('/api/schedules/:id/availability', ({ request, params }) => {
      const url = new URL(request.url);
      recorded.availability.push(url);
      const date = url.searchParams.get('from') ?? DAY;
      const minutes = Number(url.searchParams.get('duration_minutes'));

      return success({
        schedule_id: Number(params.id),
        duration_minutes: minutes,
        days: [
          {
            date,
            slots: SLOTS.map((clock) => {
              const start = new Date(`${date}T${clock}:00-03:00`).getTime();

              return {
                starts_at: new Date(start).toISOString(),
                ends_at: new Date(start + minutes * 60_000).toISOString(),
              };
            }),
          },
        ],
      });
    }),
    http.get('/api/schedules/:id/blocks', () => {
      const list = setup.blocks ?? [];

      return success(list, { meta: pagination({ total: list.length }) });
    }),
    http.get('/api/patients', () => success([LUNA], { meta: pagination({ total: 1 }) })),
    http.get('/api/appointments/:id', ({ params }) =>
      params.id === '101' ? success(detail({ id: 101 })) : success(setup.current ?? detail()),
    ),
    http.post('/api/appointments', ({ request }) => respond(request, 201)),
    http.patch('/api/appointments/:id', ({ request }) => respond(request, 200)),
  ];
}

function open(url: string, setup: Setup = {}) {
  const recorded: Recorded = { bodies: [], availability: [] };
  server.use(...handlers(recorded, setup));
  const app = renderApp(url, { session: sessionFor() });

  return { ...app, ...recorded };
}

function labelled(text: string): HTMLElement {
  const label = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.trim() === text,
  );
  const target = label?.htmlFor ? document.getElementById(label.htmlFor) : null;

  if (target === null) {
    throw new Error(`Sin el campo ${text}`);
  }

  return target;
}

const field = (text: string) => labelled(text) as HTMLInputElement;

async function findField(text: string) {
  return waitFor(() => field(text));
}

const fieldError = (text: string) =>
  document.getElementById(`${field(text).id}-error`)?.textContent ?? null;

const slot = (clock: string) =>
  document.querySelector<HTMLInputElement>(`.appointment-slot-options input[value="${clock}"]`);

const timeError = () => document.querySelector('.appointment-time-error')?.textContent ?? null;

const button = (text: string, root: ParentNode = document) =>
  Array.from(root.querySelectorAll<HTMLElement>('button, a')).find(
    (element) => element.textContent.trim() === text,
  );

const dialog = () => document.querySelector<HTMLElement>('[role="dialog"]');

/** El elemento, o la prueba falla (sin aserciones ). */
function present<T extends Element>(found: T | null | undefined): T {
  if (found === null || found === undefined) {
    throw new Error('Sin el elemento');
  }

  return found;
}

async function pickLuna() {
  fireEvent.change(field('Buscar el paciente'), { target: { value: 'Luna' } });
  const option = await waitFor(() => {
    const found = button('Luna (PAC-0009)de Laura Martínez');

    if (found === undefined) {
      throw new Error('Sin Luna');
    }

    return found;
  });
  fireEvent.click(option);
  await screen.findByText('Luna (PAC-0009)');
}

async function submit(label: string) {
  const found = await waitFor(() => {
    const element = button(label, document.querySelector('.appointment-form') ?? document);

    if (element === undefined) {
      throw new Error(`Sin ${label}`);
    }

    return element;
  });
  fireEvent.click(found);
}

/** Abre el alta desde una celda (agenda 7, mañana 09:30) y elige a Luna. */
async function newFromCell(setup: Setup = {}) {
  const app = open(`/turnos/nuevo?agenda=7&inicio=${DAY}T09:30`, setup);
  await findField('Agenda');
  await waitFor(() => {
    expect(slot('09:30')?.checked).toBe(true);
  });
  await pickLuna();

  return app;
}

const overbookRole = () => {
  const me = meFor('recepcionista');

  return meFor('recepcionista', {
    permissions: me.permissions.filter((permission) => permission !== 'appointments.overbook'),
  });
};

describe('/turnos/nuevo', () => {
  it('desde una celda: la agenda, su servicio, la duración y la hora llegan llenas; el cuerpo, con -03:00; y al detalle', async () => {
    const app = await newFromCell();

    expect(field('Agenda').value).toBe('7');
    expect(field('Servicio').value).toBe('3');
    expect(field('Duración (minutos)').value).toBe('30');
    expect(field('Fecha').value).toBe(DAY);
    expect(app.availability.at(-1)?.searchParams.toString()).toBe(
      `from=${DAY}&to=${DAY}&service_id=3&duration_minutes=30`,
    );
    expect(
      Array.from(document.querySelectorAll('.appointment-slot-options input')).map(
        (input) => (input as HTMLInputElement).value,
      ),
    ).toEqual([...SLOTS, 'otra']);
    expect(screen.getByText('Termina a las 10:00.')).toBeTruthy();

    await submit('Dar el turno');

    await screen.findByText('Turno creado correctamente.');
    expect(app.bodies).toEqual([
      {
        schedule_id: 7,
        service_id: 3,
        patient_id: 9,
        client_id: 3,
        contact_name: null,
        contact_phone: null,
        starts_at: `${DAY}T09:30:00.000-03:00`,
        ends_at: `${DAY}T10:00:00.000-03:00`,
        source: 'reception',
        reason: null,
        notes: null,
      },
    ]);
    await waitFor(() => {
      expect(app.history.location.pathname).toBe('/turnos/101');
    });
  });

  it('otra agenda trae su servicio (o ninguno) y su duración; el servicio cambia la duración', async () => {
    await newFromCell();

    fireEvent.change(field('Agenda'), { target: { value: '8' } });
    expect(field('Duración (minutos)').value).toBe('30');
    fireEvent.change(field('Servicio'), { target: { value: '' } });
    expect(field('Duración (minutos)').value).toBe('20');
    fireEvent.change(field('Servicio'), { target: { value: '5' } });
    expect(field('Duración (minutos)').value).toBe('45');
  });

  it('el foco: al elegir el paciente, en "Cambiar"; al cambiarlo, en el buscador', async () => {
    await newFromCell();

    const change = present(button('Cambiar'));
    await waitFor(() => {
      expect(document.activeElement).toBe(change);
    });
    fireEvent.click(change);
    await waitFor(() => {
      expect(document.activeElement).toBe(field('Buscar el paciente'));
    });
  });

  it('sin conexión: el aviso y lo cargado sigue (el paciente, la hora y el motivo)', async () => {
    const app = await newFromCell({ responses: [() => HttpResponse.error()] });
    fireEvent.change(field('Motivo'), { target: { value: 'Control anual' } });

    await submit('Dar el turno');

    await screen.findByText(MESSAGES.network);
    expect(app.bodies).toHaveLength(1);
    expect(screen.getByText('Luna (PAC-0009)')).toBeTruthy();
    expect(slot('09:30')?.checked).toBe(true);
    expect(field('Motivo').value).toBe('Control anual');
    expect(app.history.location.pathname).toBe('/turnos/nuevo');
  });

  it('"Otra hora": se escribe a mano y la API decide', async () => {
    const app = await newFromCell();

    fireEvent.click(present(document.querySelector('.appointment-other-time')));
    fireEvent.change(await findField('Escribí la hora'), { target: { value: '07:15' } });
    await submit('Dar el turno');

    await waitFor(() => {
      expect(app.bodies).toHaveLength(1);
    });
    expect(app.bodies[0]).toMatchObject({
      starts_at: `${DAY}T07:15:00.000-03:00`,
      ends_at: `${DAY}T07:45:00.000-03:00`,
    });
  });

  it('Zod antes de pedir: sin quién, con el mensaje de la API y sin pedido', async () => {
    const app = open(`/turnos/nuevo?agenda=7&inicio=${DAY}T09:30`);
    await findField('Agenda');

    await submit('Dar el turno');

    await screen.findByText(
      'Ingresá el paciente, el cliente o un nombre y un teléfono de contacto.',
    );
    expect(app.bodies).toEqual([]);
  });

  it('primera vez: el contacto con nombre y teléfono, sin paciente ni cliente', async () => {
    const app = open(`/turnos/nuevo?agenda=7&inicio=${DAY}T10:00`);
    await findField('Agenda');
    fireEvent.click(screen.getByText('Primera vez, sin cliente'));
    fireEvent.change(await findField('Nombre de contacto'), { target: { value: 'Pedro Gómez' } });
    fireEvent.change(field('Teléfono de contacto'), { target: { value: '981 100-001' } });

    await submit('Dar el turno');

    await waitFor(() => {
      expect(app.bodies).toHaveLength(1);
    });
    expect(app.bodies[0]).toMatchObject({
      patient_id: null,
      client_id: null,
      contact_name: 'Pedro Gómez',
      contact_phone: '981100001',
    });
  });

  it('un solo pedido con doble clic', async () => {
    const app = await newFromCell({
      responses: [
        async (body) => {
          await delay(100);

          return saved(body, 201);
        },
      ],
    });

    await submit('Dar el turno');
    await submit('Dar el turno');

    await screen.findByText('Turno creado correctamente.');
    expect(app.bodies).toHaveLength(1);
  });

  it('el 409 de la superposición: al horario, la disponibilidad de nuevo y "Dar como sobreturno" con confirmación y overbook: true', async () => {
    const app = await newFromCell({
      responses: [
        () =>
          failure(
            409,
            'APPOINTMENT_OVERLAP',
            'El horario ya está ocupado: elegí otro o dalo como sobreturno.',
          ),
        (body) => saved(body, 201),
      ],
    });
    const before = app.availability.length;

    await submit('Dar el turno');

    await waitFor(() => {
      expect(timeError()).toBe('El horario ya está ocupado: elegí otro o dalo como sobreturno.');
    });
    await waitFor(() => {
      expect(app.availability.length).toBeGreaterThan(before);
    });
    // Lo cargado sigue: el paciente y la hora.
    expect(screen.getByText('Luna (PAC-0009)')).toBeTruthy();
    expect(app.bodies[0]).not.toHaveProperty('overbook');

    fireEvent.click(present(button('Dar como sobreturno')));
    expect(dialog()?.textContent).toContain(OVERBOOK_QUESTION);
    fireEvent.click(present(button('Dar como sobreturno', present(dialog()))));

    await screen.findByText('Turno creado correctamente.');
    expect(app.bodies).toHaveLength(2);
    expect(app.bodies[1]).toMatchObject({ overbook: true, starts_at: `${DAY}T09:30:00.000-03:00` });
  });

  it('el 409 sin appointments.overbook: solo el mensaje, sin "Dar como sobreturno" y sin overbook', async () => {
    const app = await newFromCell({
      me: overbookRole(),
      responses: [
        () =>
          failure(
            409,
            'APPOINTMENT_OVERLAP',
            'El horario ya está ocupado: elegí otro o dalo como sobreturno.',
          ),
      ],
    });

    await submit('Dar el turno');

    await screen.findByText('Elegí otro horario: no tenés permiso para dar sobreturnos.');
    expect(button('Dar como sobreturno')).toBeUndefined();
    expect(app.bodies.every((body) => !('overbook' in body))).toBe(true);
  });

  it('APPOINTMENT_OUTSIDE_HOURS e _IN_BLOCK, a la hora, sin sobreturno', async () => {
    await newFromCell({
      responses: [
        () =>
          failure(
            422,
            'APPOINTMENT_OUTSIDE_HOURS',
            'El turno está fuera del horario de la agenda.',
          ),
      ],
    });

    await submit('Dar el turno');

    await waitFor(() => {
      expect(timeError()).toBe('El turno está fuera del horario de la agenda.');
    });
    expect(button('Dar como sobreturno')).toBeUndefined();
  });

  it('el 422 a cada campo: el veterinario inactivo en la agenda (con el foco) y el día que pasó en la hora', async () => {
    await newFromCell({
      responses: [
        () =>
          failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
            errors: {
              schedule_id: ['El veterinario de la agenda está inactivo.'],
              starts_at: ['No podés dar un turno en un día que ya pasó.'],
              branch_id: ['La sucursal sale de la agenda: no se envía.'],
            },
          }),
      ],
    });

    await submit('Dar el turno');

    await waitFor(() => {
      expect(fieldError('Agenda')).toBe('El veterinario de la agenda está inactivo.');
    });
    expect(timeError()).toBe('No podés dar un turno en un día que ya pasó.');
    expect(screen.getByText('La sucursal sale de la agenda: no se envía.')).toBeTruthy();
    expect(document.activeElement).toBe(field('Agenda'));
  });

  it('el veterinario no da turnos: /turnos/nuevo lleva a sin permiso', async () => {
    const app = open(`/turnos/nuevo?agenda=7`, { me: meFor('veterinario') });

    await waitFor(() => {
      expect(app.history.location.pathname).toBe('/sin-permiso');
    });
  });
});

describe('/turnos/$id', () => {
  it('el detalle: la fecha, el horario con la duración, el paciente y el cliente con enlaces, y Reprogramar y Editar', async () => {
    open('/turnos/1');

    await screen.findByText('Datos del turno');
    const text = document.querySelector('.appointment-data')?.textContent ?? '';

    expect(text).toContain('09:30 a 10:00 (30 minutos)');
    expect(text).toContain('Consultorio 1');
    expect(document.querySelector('.appointment-data a[href="/pacientes/9"]')?.textContent).toBe(
      'Luna (PAC-0009)',
    );
    expect(document.querySelector('.appointment-data a[href="/clientes/3"]')).not.toBeNull();
    expect(document.querySelector('.appointment-reschedule')?.getAttribute('href')).toBe(
      '/turnos/1/editar?reprogramar=1',
    );
    expect(document.querySelector('.appointment-edit')?.getAttribute('href')).toBe(
      '/turnos/1/editar',
    );
  });

  it('el veterinario ve el detalle, sin Editar ni Reprogramar; la edición lo lleva a sin permiso', async () => {
    const app = open('/turnos/1', { me: meFor('veterinario') });

    await screen.findByText('Datos del turno');
    expect(document.querySelector('.appointment-edit')).toBeNull();
    expect(document.querySelector('.appointment-reschedule')).toBeNull();

    app.history.push('/turnos/1/editar');
    await waitFor(() => {
      expect(app.history.location.pathname).toBe('/sin-permiso');
    });
  });

  it('un turno fichado o cancelado no se edita: sin los botones', async () => {
    open('/turnos/1', { current: detail({ status: { value: 'checked_in', label: 'Fichado' } }) });

    await screen.findByText('Datos del turno');
    expect(document.querySelector('.appointment-edit')).toBeNull();
  });

  it('dentro de un bloqueo de su agenda: el aviso con la marca', async () => {
    open('/turnos/1', {
      blocks: [{ ...block(1, '09:00', '12:00', 'Capacitación'), ...dayBlock('09:00', '12:00') }],
    });

    await screen.findByText('Dentro de un bloqueo');
    expect(document.querySelector('.appointment-in-block')?.textContent).toContain(
      'reprogramalo o cancelalo.',
    );
  });

  it('un contacto sin cliente: "Para fichar, cargá el paciente." con Nuevo cliente y Editar el turno', async () => {
    open('/turnos/1', {
      current: detail({
        patient: null,
        client: null,
        contact_name: 'Pedro Gómez',
        contact_phone: '981100001',
      }),
    });

    await screen.findByText('Para fichar, cargá el paciente.');
    const notice = present(document.querySelector('.appointment-no-patient'));

    expect(button('Nuevo cliente', notice)?.getAttribute('href')).toBe('/clientes/nuevo');
    expect(button('Editar el turno', notice)?.getAttribute('href')).toBe('/turnos/1/editar');
    expect(document.querySelector('.appointment-data')?.textContent).toContain('981100001');
  });

  it('un id que no existe: la página de no encontrado', async () => {
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/appointments/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El turno no existe.'),
      ),
    );
    renderApp('/turnos/999', { session: sessionFor() });

    await screen.findByText('No encontramos este turno');
  });
});

/** Un bloqueo de mañana, entre dos horas de pared de Asunción. */
function dayBlock(from: string, to: string) {
  return {
    starts_at: new Date(`${DAY}T${from}:00-03:00`).toISOString(),
    ends_at: new Date(`${DAY}T${to}:00-03:00`).toISOString(),
  };
}

describe('/turnos/$id/editar', () => {
  it('editar sin tocar el horario: el cuerpo sin la agenda, el inicio ni el fin', async () => {
    const app = open('/turnos/1/editar');
    fireEvent.change(await findField('Motivo'), { target: { value: 'Control anual' } });

    await submit('Guardar');

    await screen.findByText('Turno actualizado correctamente.');
    // Solo lo que cambió: ni el horario ni quién ni los demás datos.
    expect(app.bodies).toEqual([{ reason: 'Control anual' }]);
  });

  it('un turno de ayer que quedó confirmado: las notas se corrigen sin "un día que ya pasó"', async () => {
    const yesterday = appointment(1, {
      at: '09:30',
      until: '10:00',
      day: addDaysToDay(todayInAppZone(), -1),
    });
    const app = open('/turnos/1/editar', {
      current: detail({ starts_at: yesterday.starts_at, ends_at: yesterday.ends_at }),
    });
    fireEvent.change(await findField('Notas'), { target: { value: 'Llegó tarde.' } });

    await submit('Guardar');

    await screen.findByText('Turno actualizado correctamente.');
    expect(app.bodies).toEqual([{ notes: 'Llegó tarde.' }]);
  });

  it('reprogramar: el foco en la fecha, otra hora libre y la agenda del día se vuelve a pedir', async () => {
    // A las 11:00, fuera de los libres del simulacro (en la API real, el horario del turno se ocupa a sí mismo).
    const eleven = appointment(1, { at: '11:00', until: '11:30', day: DAY });
    const app = open('/turnos/1/editar?reprogramar=1', {
      current: detail({ starts_at: eleven.starts_at, ends_at: eleven.ends_at }),
    });

    await screen.findByText('Reprogramar el turno');
    await waitFor(() => {
      expect(document.activeElement).toBe(field('Fecha'));
    });
    // El horario del turno no está entre los libres: queda en "Otra hora".
    await waitFor(() => {
      expect(field('Escribí la hora').value).toBe('11:00');
    });
    const rangeKey = ['branch', 10, 'appointments', 'range', { from: DAY, to: DAY }];
    app.queryClient.setQueryData(rangeKey, { items: [], total: 0 });

    fireEvent.click(present(slot('10:00')));
    await submit('Reprogramar');

    await screen.findByText('Turno actualizado correctamente.');
    expect(app.bodies[0]).toMatchObject({
      starts_at: `${DAY}T10:00:00.000-03:00`,
      ends_at: `${DAY}T10:30:00.000-03:00`,
    });
    expect(app.bodies[0]).not.toHaveProperty('schedule_id');
    expect(app.queryClient.getQueryState(rangeKey)?.isInvalidated).toBe(true);
  });

  it('la agenda del turno que no está entre las activas de la sucursal se ofrece igual, con su marca, y no se manda', async () => {
    const app = open('/turnos/1/editar', {
      current: detail({ schedule: { id: 42, name: 'Consultorio viejo' } }),
    });
    await findField('Agenda');

    await waitFor(() => {
      expect(field('Agenda').value).toBe('42');
    });
    await screen.findByText('Consultorio viejo (no está entre las activas de tu sucursal)');
    await submit('Guardar');

    await screen.findByText('Turno actualizado correctamente.');
    expect(app.bodies[0]).not.toHaveProperty('schedule_id');
  });

  it('el 409 de un cambio concurrente: el aviso y lo cargado sigue; guardar de nuevo no pisa lo del otro', async () => {
    const conflict = () => failure(409, 'CONFLICT', 'El turno cambió mientras guardabas.');
    const app = open('/turnos/1/editar', { responses: [conflict] });
    // Después del 409, la API tiene la versión del otro: movido a las 11:00, con sus notas.
    const eleven = appointment(1, { at: '11:00', until: '11:30', day: DAY });
    let fetched = 0;
    server.use(
      http.get('/api/appointments/:id', ({ params }) => {
        // Solo el turno 1 (el caso anterior puede seguir pidiendo el 101 al que navegó).
        if (params.id !== '1') {
          return success(detail({ id: Number(params.id) }));
        }

        fetched += 1;

        return success(
          fetched === 1
            ? detail()
            : detail({ starts_at: eleven.starts_at, ends_at: eleven.ends_at, notes: 'Del otro' }),
        );
      }),
    );
    fireEvent.change(await findField('Motivo'), { target: { value: 'Control anual' } });

    await submit('Guardar');

    await screen.findByText(`${MESSAGES.conflict} El turno cambió mientras guardabas.`);
    expect(field('Motivo').value).toBe('Control anual');
    expect(app.history.location.pathname).toBe('/turnos/1/editar');
    // El detalle ya es la versión del otro (se volvió a pedir); el formulario sigue con lo cargado.
    await waitFor(() => {
      expect(fetched).toBeGreaterThan(1);
    });
    // Guardar de nuevo: solo el motivo (ni las 09:30 ni las notas vacías de esta pantalla pisan lo del otro).
    await submit('Guardar');
    await waitFor(() => {
      expect(app.bodies).toHaveLength(2);
    });
    expect(app.bodies).toEqual([{ reason: 'Control anual' }, { reason: 'Control anual' }]);
  });

  it('el 409 de un cambio concurrente: "Cargar la versión actual" rearma el formulario con la del otro', async () => {
    const app = open('/turnos/1/editar', {
      responses: [
        () => failure(409, 'CONFLICT', 'El turno cambió mientras guardabas.'),
        (body) => saved(body, 200),
      ],
    });
    const eleven = appointment(1, { at: '11:00', until: '11:30', day: DAY });
    let fetched = 0;
    server.use(
      http.get('/api/appointments/:id', ({ params }) => {
        // Solo el turno 1 (el caso anterior puede seguir pidiendo el 101 al que navegó).
        if (params.id !== '1') {
          return success(detail({ id: Number(params.id) }));
        }

        fetched += 1;

        return success(
          fetched === 1
            ? detail()
            : detail({ starts_at: eleven.starts_at, ends_at: eleven.ends_at, notes: 'Del otro' }),
        );
      }),
    );
    fireEvent.change(await findField('Motivo'), { target: { value: 'Control anual' } });
    await submit('Guardar');
    await screen.findByText(`${MESSAGES.conflict} El turno cambió mientras guardabas.`);

    fireEvent.click(present(button('Cargar la versión actual')));

    await screen.findByText(RELOADED_MESSAGE);
    await waitFor(() => {
      expect(field('Notas').value).toBe('Del otro');
    });
    expect(field('Motivo').value).toBe('');
    expect(field('Escribí la hora').value).toBe('11:00');
    expect(document.activeElement).toBe(document.querySelector('.appointment-alert'));
    // Con la versión nueva como base, un cambio manda solo ese cambio.
    fireEvent.change(field('Motivo'), { target: { value: 'Control' } });
    await submit('Guardar');
    await screen.findByText('Turno actualizado correctamente.');
    expect(app.bodies.at(-1)).toEqual({ reason: 'Control' });
  });

  it('APPOINTMENT_NOT_EDITABLE: el aviso y el turno de nuevo (ya fichado, sin el formulario)', async () => {
    // Otra PC lo fichó: la API lo devuelve fichado desde que rechazó la edición.
    let rejected = false;
    open('/turnos/1/editar', {
      responses: [
        () => {
          rejected = true;

          return failure(
            422,
            'APPOINTMENT_NOT_EDITABLE',
            'Solo se modifican los turnos a confirmar o confirmados.',
          );
        },
      ],
    });
    server.use(
      http.get('/api/appointments/:id', () =>
        success(
          rejected ? detail({ status: { value: 'checked_in', label: 'Fichado' } }) : detail(),
        ),
      ),
    );
    await findField('Motivo');

    await submit('Guardar');

    await screen.findByText('Este turno ya no se modifica');
  });
});
