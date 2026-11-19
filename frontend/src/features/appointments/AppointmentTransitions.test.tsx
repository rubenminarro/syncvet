import { fireEvent, screen, waitFor } from '@testing-library/react';
import { delay, http } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { appointment } from '../../test/appointments';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { ROOM_ONE, scheduleListItem } from '../../test/schedules';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { Me } from '../auth/types';
import type { ScheduleDetail } from '../schedules/types';
import { UNDO_AS_OVERBOOK, UNDO_NO_SHOW_TEXT } from './AppointmentActions';
import { CANCELLATION_MESSAGES, PATIENT_REQUIRED_HINT } from './transitions';
import type { Appointment, AppointmentDetail } from './types';

// Los estados del turno (3.5) en el detalle (/turnos/$id) y en el panel del día, con MSW. "Ahora" es el martes 17/11/2026
// a las 10:00 de Asunción (solo `Date` falso: MSW y Testing Library siguen con sus esperas reales). Por selector, no por rol
// (los íconos SVG del layout hacen lento *ByRole en jsdom).

const NOW = new Date('2026-11-17T13:00:00Z');
const STATUS_LABELS: Record<string, string> = {
  pending_confirmation: 'A confirmar',
  confirmed: 'Confirmado',
  checked_in: 'Fichado',
  attended: 'Atendido',
  no_show: 'Ausente',
  cancelled: 'Cancelado',
};
const TO: Record<string, string> = {
  confirm: 'confirmed',
  'check-in': 'checked_in',
  attend: 'attended',
  'no-show': 'no_show',
  'undo-no-show': 'confirmed',
  cancel: 'cancelled',
};
const MESSAGES_OK: Record<string, string> = {
  confirm: 'Turno confirmado.',
  'check-in': 'Turno fichado.',
  attend: 'Turno marcado como atendido.',
  'no-show': 'Turno marcado como ausente.',
  'undo-no-show': 'Se deshizo el ausente: el turno volvió a confirmado.',
  cancel: 'Turno cancelado.',
};

function detail(
  overrides: Partial<AppointmentDetail> = {},
  status = 'confirmed',
): AppointmentDetail {
  return {
    // Hoy a las 09:00 (ya empezó), de la ficha 4 (Víctor Acosta).
    ...appointment(1, { at: '09:00', status }),
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

const withStatus = (turn: AppointmentDetail, value: string): AppointmentDetail => ({
  ...turn,
  status: { value, label: STATUS_LABELS[value] ?? value },
});

/** Lo que responde una acción: por defecto, el 200 con el turno en su estado nuevo. */
type Responder = (action: string, body: Record<string, unknown>) => Response | Promise<Response>;

interface Setup {
  me?: Me;
  current?: AppointmentDetail;
  responses?: Responder[];
  /** El turno que la API da al volver a pedirlo después de un error (otra PC lo cambió). */
  afterError?: AppointmentDetail;
}

interface Recorded {
  actions: { action: string; body: Record<string, unknown> }[];
  detailGets: number;
  rangeGets: number;
}

function handlers(recorded: Recorded, setup: Setup) {
  let current = setup.current ?? detail();
  const responses = setup.responses ?? [];

  return [
    meHandler(setup.me ?? meFor('recepcionista')),
    http.get('/api/schedules', () =>
      success([ROOM_ONE].map(scheduleListItem), { meta: pagination({ total: 1 }) }),
    ),
    http.get('/api/schedules/:id', () => success(ONE)),
    http.get('/api/schedules/:id/blocks', () => success([], { meta: pagination({ total: 0 }) })),
    http.get('/api/appointments', () => {
      recorded.rangeGets += 1;

      return success([current], { meta: pagination({ total: 1, per_page: 500 }) });
    }),
    http.get('/api/appointments/:id', () => {
      recorded.detailGets += 1;

      return success(current);
    }),
    http.post('/api/appointments/:id/:action', async ({ request, params }) => {
      const action = String(params.action);
      const body = (await request.json()) as Record<string, unknown>;
      recorded.actions.push({ action, body });
      const responder = responses[recorded.actions.length - 1];

      if (responder !== undefined) {
        const response = await responder(action, body);

        if (!response.ok && setup.afterError !== undefined) {
          current = setup.afterError;
        }

        return response;
      }

      current = withStatus(current, TO[action] ?? current.status.value);

      return success(current, { message: MESSAGES_OK[action] ?? 'Listo.' });
    }),
  ];
}

const ONE: ScheduleDetail = {
  ...ROOM_ONE,
  hours: [{ id: 11, weekday: 2, starts_at: '08:00', ends_at: '12:00' }],
};

function open(url: string, setup: Setup = {}) {
  const recorded: Recorded = { actions: [], detailGets: 0, rangeGets: 0 };
  server.use(...handlers(recorded, setup));
  const app = renderApp(url, { session: sessionFor() });

  return { ...app, recorded };
}

async function openDetail(setup: Setup = {}) {
  const app = open('/turnos/1', setup);
  await screen.findByText('Datos del turno');

  return app;
}

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

const transitions = () =>
  Array.from(document.querySelectorAll('.appointment-transitions > button')).map((element) =>
    element.textContent.trim(),
  );
const titleStatus = () =>
  document.querySelector('.appointment-title .appointment-status-label')?.textContent ?? '';
const dialog = () => document.querySelector<HTMLElement>('.confirm-dialog');
const toastText = () =>
  Array.from(document.querySelectorAll('.toast')).map((element) => element.textContent);

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'], now: NOW });
});

afterEach(() => {
  vi.useRealTimers();
});

describe('las acciones por rol en el detalle', () => {
  it('la recepción, en un turno a confirmar de hoy ya empezado: confirmar, fichar, ausente y cancelar', async () => {
    await openDetail({ current: detail({}, 'pending_confirmation') });

    expect(transitions()).toEqual(['Confirmar', 'Fichar', 'Ausente', 'Cancelar el turno']);
  });

  it('el veterinario, en su turno fichado: solo "Marcar atendido"; en uno de otra ficha, ninguna', async () => {
    const vet = meFor('veterinario', { veterinarian: { id: 4, code: 'VET-0001' } });
    await openDetail({ me: vet, current: detail({}, 'checked_in') });

    expect(transitions()).toEqual(['Marcar atendido']);
  });

  it('el veterinario, en un turno de otra ficha: sin "Fichar" ni "Atendido" (ocultas)', async () => {
    const vet = meFor('veterinario', { veterinarian: { id: 99, code: 'VET-0099' } });
    await openDetail({ me: vet, current: detail({}, 'confirmed') });

    expect(document.querySelector('.appointment-transitions')).toBeNull();
  });

  it('el administrador, en un fichado: "Marcar atendido" y cancelar', async () => {
    await openDetail({ me: meFor('administrador'), current: detail({}, 'checked_in') });

    expect(transitions()).toEqual(['Marcar atendido', 'Cancelar el turno']);
  });

  it('un atendido o un cancelado: sin acciones; el cancelado, tachado en el título con su estado', async () => {
    await openDetail({ current: detail({}, 'cancelled') });

    expect(document.querySelector('.appointment-transitions')).toBeNull();
    expect(document.querySelector('.appointment-title .agenda-item-cancelled')).not.toBeNull();
    expect(titleStatus()).toBe('Cancelado');
  });
});

describe('las acciones directas', () => {
  it('confirmar: el pedido, el aviso, el estado nuevo, la agenda vuelta a pedir y el foco en el título', async () => {
    const { recorded } = await openDetail({ current: detail({}, 'pending_confirmation') });
    const confirm = present(button('Confirmar'));
    confirm.focus();
    fireEvent.click(confirm);

    await waitFor(() => {
      expect(titleStatus()).toBe('Confirmado');
    });
    expect(recorded.actions).toEqual([{ action: 'confirm', body: {} }]);
    expect(toastText()).toContain('Turno confirmado.');
    expect(transitions()).toEqual(['Fichar', 'Ausente', 'Cancelar el turno']);
    await waitFor(() => {
      expect(document.activeElement?.classList.contains('appointment-title')).toBe(true);
    });
  });

  it('fichar: a fichado, con "Cancelar el turno" y sin "Fichar"', async () => {
    const { recorded } = await openDetail();
    fireEvent.click(present(button('Fichar')));

    await waitFor(() => {
      expect(titleStatus()).toBe('Fichado');
    });
    expect(recorded.actions.map((item) => item.action)).toEqual(['check-in']);
    expect(transitions()).toEqual(['Cancelar el turno']);
  });

  it('el veterinario marca atendido su turno fichado', async () => {
    const vet = meFor('veterinario', { veterinarian: { id: 4, code: 'VET-0001' } });
    const { recorded } = await openDetail({ me: vet, current: detail({}, 'checked_in') });
    fireEvent.click(present(button('Marcar atendido')));

    await waitFor(() => {
      expect(titleStatus()).toBe('Atendido');
    });
    expect(recorded.actions.map((item) => item.action)).toEqual(['attend']);
    expect(document.querySelector('.appointment-transitions')).toBeNull();
  });

  it('ausente: a ausente, con "Deshacer el ausente"', async () => {
    const { recorded } = await openDetail();
    fireEvent.click(present(button('Ausente')));

    await waitFor(() => {
      expect(titleStatus()).toBe('Ausente');
    });
    expect(recorded.actions.map((item) => item.action)).toEqual(['no-show']);
    expect(transitions()).toEqual(['Deshacer el ausente']);
    expect(document.querySelector('.appointment-title .agenda-item-dimmed')).not.toBeNull();
  });

  it('en el detalle, el 200 no pide nada más: el turno sale de la respuesta y la agenda no se ve', async () => {
    const { recorded } = await openDetail({ current: detail({}, 'pending_confirmation') });
    const gets = { detail: recorded.detailGets, range: recorded.rangeGets };
    fireEvent.click(present(button('Confirmar')));

    await waitFor(() => {
      expect(titleStatus()).toBe('Confirmado');
    });
    await delay(100);
    expect({ detail: recorded.detailGets, range: recorded.rangeGets }).toEqual(gets);
  });

  it('un doble clic manda un solo pedido', async () => {
    const { recorded } = await openDetail({
      responses: [
        async (action) => {
          await delay(50);

          return success(withStatus(detail(), TO[action] ?? 'confirmed'), { message: 'Listo.' });
        },
      ],
    });
    const checkIn = present(button('Fichar'));
    fireEvent.click(checkIn);
    fireEvent.click(checkIn);

    await waitFor(() => {
      expect(titleStatus()).toBe('Fichado');
    });
    expect(recorded.actions).toHaveLength(1);
  });
});

describe('las reglas que el navegador avisa antes', () => {
  it('fichar sin paciente: deshabilitado, con el motivo, y el aviso lleva a cargarlo', async () => {
    const { recorded } = await openDetail({
      current: detail({ patient: null, client: { id: 3, display_name: 'Laura Martínez' } }),
    });
    const checkIn = present(button('Fichar'));

    expect(checkIn.getAttribute('aria-disabled')).toBe('true');
    const hints = present(document.getElementById(checkIn.getAttribute('aria-describedby') ?? ''));
    expect(hints.textContent).toContain(`Fichar: ${PATIENT_REQUIRED_HINT}`);
    fireEvent.click(checkIn);
    expect(recorded.actions).toHaveLength(0);
    const notice = present(document.querySelector('.appointment-no-patient'));
    expect(button('Nuevo paciente', notice)?.getAttribute('href')).toBe(
      '/clientes/3/pacientes/nuevo',
    );
    expect(button('Editar el turno', notice)?.getAttribute('href')).toBe('/turnos/1/editar');
  });

  it('otro día: "Fichar" deshabilitado ("Solo se fichan los turnos del día.") y el ausente, desde su hora', async () => {
    const tomorrow = appointment(1, { at: '09:30', day: '2026-11-18' });
    const { recorded } = await openDetail({
      current: { ...detail(), starts_at: tomorrow.starts_at, ends_at: tomorrow.ends_at },
    });
    const hints = document.querySelector('.appointment-transition-hints')?.textContent ?? '';

    expect(hints).toContain('Fichar: Solo se fichan los turnos del día.');
    expect(hints).toContain('Ausente: Se marca desde el 18/11/2026 a las 09:30.');
    fireEvent.click(present(button('Ausente')));
    expect(recorded.actions).toHaveLength(0);
  });

  it('el ausente de hoy, antes del inicio: "Se marca desde las 10:30."', async () => {
    const later = appointment(1, { at: '10:30' });
    await openDetail({
      current: { ...detail(), starts_at: later.starts_at, ends_at: later.ends_at },
    });

    expect(document.querySelector('.appointment-transition-hints')?.textContent).toContain(
      'Ausente: Se marca desde las 10:30.',
    );
  });
});

describe('cancelar con motivo', () => {
  it('el diálogo con el foco en el motivo; sin motivo, el mensaje y ningún pedido; con motivo, cancelado', async () => {
    const { recorded } = await openDetail();
    fireEvent.click(present(button('Cancelar el turno')));

    const reason = await waitFor(() => present(document.querySelector('textarea')));
    expect(document.activeElement).toBe(reason);
    expect(button('Volver', present(dialog()))).toBeDefined();

    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));
    expect(await screen.findByText(CANCELLATION_MESSAGES.required)).toBeTruthy();
    expect(recorded.actions).toHaveLength(0);
    expect(document.activeElement).toBe(reason);

    fireEvent.change(reason, { target: { value: '  El cliente avisó  ' } });
    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));

    await waitFor(() => {
      expect(titleStatus()).toBe('Cancelado');
    });
    expect(recorded.actions).toEqual([
      { action: 'cancel', body: { cancellation_reason: 'El cliente avisó' } },
    ]);
    expect(dialog()).toBeNull();
    expect(toastText()).toContain('Turno cancelado.');
    expect(document.querySelector('.appointment-title .agenda-item-cancelled')).not.toBeNull();
    await waitFor(() => {
      expect(document.activeElement?.classList.contains('appointment-title')).toBe(true);
    });
  });

  it('el 422 del motivo, en su campo', async () => {
    await openDetail({
      responses: [
        () =>
          failure(422, 'VALIDATION_ERROR', 'Datos inválidos.', {
            errors: { cancellation_reason: [CANCELLATION_MESSAGES.min] },
          }),
      ],
    });
    fireEvent.click(present(button('Cancelar el turno')));
    const reason = await waitFor(() => present(document.querySelector('textarea')));
    fireEvent.change(reason, { target: { value: 'abc' } });
    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));

    expect(await screen.findByText(CANCELLATION_MESSAGES.min)).toBeTruthy();
    expect(reason.getAttribute('aria-invalid')).toBe('true');
    // El 422 no borra lo escrito: se corrige y se vuelve a mandar.
    expect(reason.value).toBe('abc');
    expect(button('Cancelar el turno', present(dialog()))).toBeDefined();
  });

  it('el 409 (otra PC lo cambió): el mensaje, el motivo sigue, "Probá de nuevo" y el turno vuelto a pedir', async () => {
    const { recorded } = await openDetail({
      responses: [() => failure(409, 'CONFLICT', 'El turno cambió mientras guardabas.')],
    });
    fireEvent.click(present(button('Cancelar el turno')));
    const reason = await waitFor(() => present(document.querySelector('textarea')));
    fireEvent.change(reason, { target: { value: 'El cliente avisó' } });
    const before = recorded.detailGets;
    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('Alguien cambió estos datos');
    });
    expect(reason.value).toBe('El cliente avisó');
    await waitFor(() => {
      expect(recorded.detailGets).toBeGreaterThan(before);
    });

    fireEvent.click(present(button('Probá de nuevo', present(dialog()))));
    await waitFor(() => {
      expect(titleStatus()).toBe('Cancelado');
    });
    expect(recorded.actions.map((item) => item.body)).toEqual([
      { cancellation_reason: 'El cliente avisó' },
      { cancellation_reason: 'El cliente avisó' },
    ]);
  });
});

describe('los errores de una acción directa', () => {
  it('APPOINTMENT_INVALID_TRANSITION: el mensaje con "Cerrar" y el turno vuelto a pedir (ya cancelado)', async () => {
    const message = 'El turno está «Cancelado» y no admite ese cambio.';
    const { recorded } = await openDetail({
      current: detail({}, 'pending_confirmation'),
      responses: [() => failure(422, 'APPOINTMENT_INVALID_TRANSITION', message)],
      afterError: withStatus(detail(), 'cancelled'),
    });
    fireEvent.click(present(button('Confirmar')));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain(message);
    });
    expect(dialog()?.querySelector('.modal-title')?.textContent).toBe('Confirmar el turno');
    expect(button('Probá de nuevo', present(dialog()))).toBeUndefined();
    await waitFor(() => {
      expect(titleStatus()).toBe('Cancelado');
    });

    fireEvent.click(present(button('Cerrar', present(dialog()))));
    expect(dialog()).toBeNull();
    expect(recorded.actions).toHaveLength(1);
    await waitFor(() => {
      expect(document.activeElement?.classList.contains('appointment-title')).toBe(true);
    });
  });

  it('APPOINTMENT_NOT_YOURS (403): el mensaje en el diálogo', async () => {
    const vet = meFor('veterinario', { veterinarian: { id: 4, code: 'VET-0001' } });
    await openDetail({
      me: vet,
      current: detail({}, 'checked_in'),
      responses: [() => failure(403, 'APPOINTMENT_NOT_YOURS', 'Ese turno no es de tu agenda.')],
    });
    fireEvent.click(present(button('Marcar atendido')));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('Ese turno no es de tu agenda.');
    });
    expect(button('Cerrar', present(dialog()))).toBeDefined();
  });

  it('APPOINTMENT_PATIENT_REQUIRED (otra PC sacó el paciente): el diálogo lleva a cargarlo', async () => {
    await openDetail({
      responses: [
        () =>
          failure(422, 'APPOINTMENT_PATIENT_REQUIRED', 'Para fichar el turno, cargá el paciente.'),
      ],
    });
    fireEvent.click(present(button('Fichar')));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('Para fichar el turno, cargá el paciente.');
    });
    expect(button('Editar el turno', present(dialog()))?.getAttribute('href')).toBe(
      '/turnos/1/editar',
    );
  });

  it('la red: "Probá de nuevo" repite la acción', async () => {
    const { recorded } = await openDetail({
      responses: [() => failure(503, 'SERVICE_UNAVAILABLE', 'Mantenimiento.')],
    });
    fireEvent.click(present(button('Ausente')));

    await waitFor(() => {
      expect(button('Probá de nuevo', present(dialog()))).toBeDefined();
    });
    fireEvent.click(present(button('Probá de nuevo', present(dialog()))));

    await waitFor(() => {
      expect(titleStatus()).toBe('Ausente');
    });
    expect(recorded.actions.map((item) => item.action)).toEqual(['no-show', 'no-show']);
    expect(dialog()).toBeNull();
  });
});

describe('deshacer el ausente', () => {
  it('con la confirmación: "El turno vuelve a confirmado."', async () => {
    const { recorded } = await openDetail({ current: detail({}, 'no_show') });
    fireEvent.click(present(button('Deshacer el ausente')));

    expect(dialog()?.textContent).toContain(UNDO_NO_SHOW_TEXT);
    fireEvent.click(present(button('Deshacer el ausente', present(dialog()))));

    await waitFor(() => {
      expect(titleStatus()).toBe('Confirmado');
    });
    expect(recorded.actions).toEqual([{ action: 'undo-no-show', body: {} }]);
  });

  it('el 409 del horario ocupado: con appointments.overbook, "Volver como sobreturno" reenvía con overbook', async () => {
    const { recorded } = await openDetail({
      current: detail({}, 'no_show'),
      responses: [
        () =>
          failure(
            409,
            'APPOINTMENT_OVERLAP',
            'El horario ya está ocupado: elegí otro o dalo como sobreturno.',
          ),
      ],
    });
    fireEvent.click(present(button('Deshacer el ausente')));
    fireEvent.click(present(button('Deshacer el ausente', present(dialog()))));

    const again = await waitFor(() => present(button(UNDO_AS_OVERBOOK, present(dialog()))));
    expect(dialog()?.textContent).toContain('El horario ya está ocupado');
    fireEvent.click(again);

    await waitFor(() => {
      expect(titleStatus()).toBe('Confirmado');
    });
    expect(recorded.actions.map((item) => item.body)).toEqual([{}, { overbook: true }]);
  });

  it('el 409 sin appointments.overbook: solo el mensaje y "Cerrar"', async () => {
    const recepcion = meFor('recepcionista');
    const me = {
      ...recepcion,
      permissions: recepcion.permissions.filter((name) => name !== 'appointments.overbook'),
    };
    await openDetail({
      me,
      current: detail({}, 'no_show'),
      responses: [() => failure(409, 'APPOINTMENT_OVERLAP', 'El horario ya está ocupado.')],
    });
    fireEvent.click(present(button('Deshacer el ausente')));
    fireEvent.click(present(button('Deshacer el ausente', present(dialog()))));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('El horario ya está ocupado.');
    });
    expect(button(UNDO_AS_OVERBOOK, present(dialog()))).toBeUndefined();
    expect(button('Cerrar', present(dialog()))).toBeDefined();
  });
});

describe('el panel de la agenda del día', () => {
  const contact: Partial<Appointment> = {
    patient: null,
    client: null,
    contact_name: 'Pedro Gómez',
    contact_phone: '981100001',
  };

  it('fichar sin paciente, en el panel: el motivo con los enlaces para cargarlo', async () => {
    open('/agenda?fecha=2026-11-17', { current: detail(contact) });
    const turn = await waitFor(() =>
      present(document.querySelector<HTMLElement>('[data-key="a:1"]')),
    );
    fireEvent.click(turn);

    const panel = await waitFor(() => present(document.querySelector('.agenda-panel')));
    const hints = present(panel.querySelector('.appointment-transition-hints'));
    expect(hints.textContent).toContain(PATIENT_REQUIRED_HINT);
    expect(button('Nuevo cliente', hints)?.getAttribute('href')).toBe('/clientes/nuevo');
    expect(button('Editar el turno', hints)?.getAttribute('href')).toBe('/turnos/1/editar');
  });

  it('confirmar desde el panel: un solo pedido del día (la grilla que se ve), ninguno del detalle; el foco en el título', async () => {
    const { recorded } = open('/agenda?fecha=2026-11-17', {
      current: detail({}, 'pending_confirmation'),
    });
    fireEvent.click(
      await waitFor(() => present(document.querySelector<HTMLElement>('[data-key="a:1"]'))),
    );
    const panel = await waitFor(() => present(document.querySelector('.agenda-panel')));
    const before = { detail: recorded.detailGets, range: recorded.rangeGets };
    const confirm = present(button('Confirmar', panel));
    confirm.focus();
    fireEvent.click(confirm);

    await waitFor(() => {
      expect(recorded.rangeGets).toBe(before.range + 1);
    });
    await delay(100);
    expect(recorded.rangeGets).toBe(before.range + 1);
    expect(recorded.detailGets).toBe(before.detail);
    await waitFor(() => {
      expect(document.activeElement?.id).toBe('agenda-panel-title');
    });
  });

  it('cancelar desde el panel: la grilla se vuelve a pedir y el cancelado se oculta; el panel muestra "Cancelado"', async () => {
    const { recorded } = open('/agenda?fecha=2026-11-17');
    const turn = await waitFor(() =>
      present(document.querySelector<HTMLElement>('[data-key="a:1"]')),
    );
    fireEvent.click(turn);
    const panel = await waitFor(() => present(document.querySelector('.agenda-panel')));
    const before = recorded.rangeGets;

    fireEvent.click(present(button('Cancelar el turno', panel)));
    const reason = await waitFor(() => present(document.querySelector('textarea')));
    fireEvent.change(reason, { target: { value: 'El cliente avisó' } });
    fireEvent.click(present(button('Cancelar el turno', present(dialog()))));

    await waitFor(() => {
      expect(recorded.rangeGets).toBeGreaterThan(before);
    });
    await waitFor(() => {
      expect(document.querySelector('[data-key="a:1"]')).toBeNull();
    });
    const shown = present(document.querySelector('.agenda-panel'));
    expect(shown.querySelector('.appointment-status-label')?.textContent).toBe('Cancelado');
    expect(shown.querySelector('.appointment-transitions')).toBeNull();
    await waitFor(() => {
      expect(document.activeElement?.id).toBe('agenda-panel-title');
    });
  });
});
