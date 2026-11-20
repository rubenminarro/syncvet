import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { pauseAutoRefresh } from '../../lib/hooks/auto-refresh';
import { failure, success } from '../../test/api';
import { appointment, block } from '../../test/appointments';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { Me } from '../auth/types';
import {
  QUEUE_EMPTY_MESSAGE,
  QUEUE_NO_BRANCH_MESSAGE,
  QUEUE_TRUNCATED_MESSAGE,
  QUEUE_UNASSIGNED_NOTE,
} from './queue';

import type { QueueAppointment, QueueMeta } from './types';

// La cola del día (/agenda/cola, 3.7) con MSW. "Ahora" es el martes 17/11/2026 a las 10:00 de Asunción (13:00Z; solo `Date`
// falso, salvo en la actualización, que también falsea setInterval). Por selector, no por rol (los íconos SVG del layout hacen
// lento *ByRole en jsdom).

const NOW = new Date('2026-11-17T13:00:00Z');
const STATUS_LABELS: Record<string, string> = {
  pending_confirmation: 'A confirmar',
  confirmed: 'Confirmado',
  checked_in: 'Fichado',
  attended: 'Atendido',
  no_show: 'Ausente',
};
const TO: Record<string, string> = {
  'check-in': 'checked_in',
  attend: 'attended',
  'no-show': 'no_show',
};
const MESSAGES_OK: Record<string, string> = {
  'check-in': 'Turno fichado.',
  attend: 'Turno marcado como atendido.',
  'no-show': 'Turno marcado como ausente.',
};

function queued(
  id: number,
  overrides: Parameters<typeof appointment>[1] & { checked_in_at?: string | null } = {},
): QueueAppointment {
  const { checked_in_at = null, ...rest } = overrides;
  const base = appointment(id, rest);

  return {
    ...base,
    patient:
      base.patient === null
        ? null
        : {
            ...base.patient,
            species: { id: 1, name: 'Canino' },
            breed: { id: 2, name: 'Mestizo' },
          },
    checked_in_at,
  };
}

const OTHER_VET = { id: 8, code: 'VET-0008', name: 'Olga Rivas' };

/**
 * La cola de la recepción, en el orden de la API (la hora del turno): un fichado, un sobreturno de una serie de otro
 * veterinario, uno en un bloqueo que todavía no empezó y uno con un contacto (sin paciente).
 */
const RECEPTION_QUEUE: QueueAppointment[] = [
  queued(1, { at: '09:00', status: 'checked_in', checked_in_at: '2026-11-17T12:12:00.000000Z' }),
  queued(2, {
    at: '09:30',
    status: 'confirmed',
    is_overbooked: true,
    series_id: '0b5b8f2e-1111-4222-8333-944455556666',
    patient: { id: 10, code: 'PAC-0010', name: 'Rocky' },
    client: { id: 4, display_name: 'Carlos Duarte' },
    veterinarian: OTHER_VET,
    schedule: { id: 8, name: 'Consultorio 2' },
  }),
  queued(3, { at: '10:30', status: 'confirmed' }),
  queued(4, {
    at: '11:30',
    status: 'pending_confirmation',
    patient: null,
    client: null,
    contact_name: 'Pedro Paz',
    contact_phone: '+595981100099',
    schedule: { id: 8, name: 'Consultorio 2' },
  }),
];

interface Setup {
  me?: Me;
  queue?: () => QueueAppointment[];
  meta?: Partial<QueueMeta>;
  queueResponse?: () => Response;
  actionResponse?: (action: string) => Response | undefined;
}

interface Recorded {
  queueUrls: URL[];
  otherGets: string[];
  actions: string[];
}

function open(url = '/agenda/cola', setup: Setup = {}) {
  const recorded: Recorded = { queueUrls: [], otherGets: [], actions: [] };
  let current = (setup.queue ?? (() => RECEPTION_QUEUE))();

  server.use(
    meHandler(setup.me ?? meFor('recepcionista')),
    http.get('/api/appointments/queue', ({ request }) => {
      recorded.queueUrls.push(new URL(request.url));

      if (setup.queueResponse !== undefined) {
        return setup.queueResponse();
      }

      if (setup.queue !== undefined) {
        current = setup.queue();
      }

      return success(current, {
        message: 'Cola del día obtenida correctamente.',
        meta: {
          date: new URL(request.url).searchParams.get('date') ?? '2026-11-17',
          branch_id: 10,
          veterinarian_id: null,
          includes_unassigned: false,
          truncated: false,
          ...setup.meta,
        },
      });
    }),
    http.get('/api/schedules/:id/blocks', ({ params }) => {
      recorded.otherGets.push(`blocks:${String(params.id)}`);

      return success(params.id === '7' ? [block(1, '10:15', '11:00')] : [], {
        meta: pagination({ total: 1 }),
      });
    }),
    http.get('/api/appointments', () => {
      recorded.otherGets.push('appointments');

      return success([], { meta: pagination({ total: 0 }) });
    }),
    http.get('/api/appointments/:id', ({ params }) => {
      recorded.otherGets.push(`detail:${String(params.id)}`);

      return failure(404, 'RESOURCE_NOT_FOUND', 'No encontrado.');
    }),
    http.post('/api/appointments/:id/:action', ({ params }) => {
      const action = String(params.action);
      const id = Number(params.id);
      recorded.actions.push(`${action}:${String(id)}`);
      const custom = setup.actionResponse?.(action);

      if (custom !== undefined) {
        return custom;
      }

      const found = current.find((item) => item.id === id);

      if (found === undefined) {
        return failure(404, 'RESOURCE_NOT_FOUND', 'El turno no existe.');
      }

      const value = TO[action] ?? found.status.value;
      const updated: QueueAppointment = {
        ...found,
        status: { value, label: STATUS_LABELS[value] ?? value },
        checked_in_at: value === 'checked_in' ? '2026-11-17T13:00:00.000000Z' : found.checked_in_at,
      };
      current = current.map((item) => (item.id === id ? updated : item));

      return success(
        {
          ...updated,
          notes: null,
          created_by: null,
          confirmed_at: null,
          attended_at: null,
          no_show_at: null,
          cancelled_at: null,
          cancelled_by: null,
          cancellation_reason: null,
          created_at: null,
        },
        { message: MESSAGES_OK[action] ?? 'Listo.' },
      );
    }),
  );

  const app = renderApp(url, { session: sessionFor() });

  return { ...app, recorded };
}

const rows = () => Array.from(document.querySelectorAll<HTMLElement>('.queue-row'));
const row = (id: number) => document.querySelector<HTMLElement>(`[data-queue-id="${String(id)}"]`);
const rowText = (id: number) => row(id)?.textContent ?? '';
const actions = (id: number) =>
  Array.from(row(id)?.querySelectorAll('.appointment-transitions > button') ?? []).map((element) =>
    element.textContent.trim(),
  );
const status = () =>
  document.querySelector('[role="status"][aria-live="polite"]')?.textContent ?? '';
const toastText = () =>
  Array.from(document.querySelectorAll('.toast')).map((element) => element.textContent);
const dialog = () => document.querySelector<HTMLElement>('.confirm-dialog');

function buttonIn(root: ParentNode | null, text: string): HTMLElement {
  const found = Array.from(root?.querySelectorAll<HTMLElement>('button') ?? []).find(
    (element) => element.textContent.trim() === text,
  );

  if (found === undefined) {
    throw new Error(`Sin el botón ${text}`);
  }

  return found;
}

async function openQueue(url?: string, setup?: Setup) {
  const app = open(url, setup);
  await waitFor(() => {
    expect(rows().length).toBeGreaterThan(0);
  });

  return app;
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'], now: NOW });
});

afterEach(() => {
  vi.useRealTimers();
});

describe('/agenda/cola: la recepción', () => {
  it('toda la sucursal, sin all, en el orden de la API y con los datos de cada turno', async () => {
    const { recorded } = await openQueue();

    expect(recorded.queueUrls).toHaveLength(1);
    expect(recorded.queueUrls[0]?.searchParams.get('date')).toBe('2026-11-17');
    expect(recorded.queueUrls[0]?.searchParams.has('all')).toBe(false);
    expect(rows().map((element) => element.dataset.queueId)).toEqual(['1', '2', '3', '4']);
    expect(rowText(1)).toContain('09:00');
    expect(rowText(1)).toContain('Luna');
    expect(rowText(1)).toContain('Canino · Mestizo · de Laura Martínez');
    expect(rowText(1)).toContain('Consulta · Víctor Acosta · Consultorio 1');
    // Fichado a las 09:12 de Asunción (12:12Z), 48 minutos antes de las 10:00.
    expect(rowText(1)).toContain('Esperando desde las 09:12 (48 min)');
    expect(rowText(2)).toContain('Rocky');
    expect(rowText(4)).toContain('Pedro Paz');
    expect(rowText(4)).toContain('+595981100099');
    expect(rowText(2)).toContain('Sobreturno');
    expect(rowText(2)).toContain('Serie');
    expect(rowText(3)).toContain('Dentro de un bloqueo');
    expect(rowText(1)).not.toContain('Dentro de un bloqueo');
    expect(document.querySelector('.queue-unassigned')).toBeNull();
    expect(document.body.textContent).not.toContain('Ver toda la sucursal');
    await waitFor(() => {
      expect(status()).toBe('Cola del martes 17 de noviembre: 4 turnos.');
    });
  });

  it('las acciones de la cola para su rol: fichar y ausente; ni confirmar, ni cancelar, ni atendido', async () => {
    await openQueue();

    // El fichado: la recepción no marca atendido ni cancela desde la cola.
    expect(actions(1)).toEqual([]);
    expect(actions(2)).toEqual(['Fichar', 'Ausente']);
    // El de las 10:30 todavía no empezó: ausente, deshabilitado con el motivo.
    expect(actions(3)).toEqual(['Fichar', 'Ausente']);
    expect(rowText(3)).toContain('Se marca desde las 10:30.');
    // Con un contacto (sin paciente): fichar, deshabilitado con el motivo.
    expect(actions(4)).toEqual(['Fichar', 'Ausente']);
    expect(rowText(4)).toContain('Para fichar, cargá el paciente.');
    expect(row(1)?.querySelector('.queue-row-detail')?.getAttribute('href')).toBe('/turnos/1');
  });

  it('la pestaña "Cola" en la barra, marcada, y "Día" conserva la fecha', async () => {
    await openQueue('/agenda/cola?fecha=2026-11-17');

    const cola = document.querySelector('a.agenda-view-cola');
    expect(cola?.getAttribute('aria-current')).toBe('page');
    expect(document.querySelector('a.agenda-view-dia')?.getAttribute('href')).toBe(
      '/agenda?fecha=2026-11-17',
    );
  });

  it('otro día: la fecha en el pedido, el fichado sin la cuenta de la espera', async () => {
    const { recorded } = await openQueue('/agenda/cola?fecha=2026-11-16');

    expect(recorded.queueUrls[0]?.searchParams.get('date')).toBe('2026-11-16');
    expect(rowText(1)).toContain('Fichado a las 09:12');
  });

  it('el aviso de truncated', async () => {
    await openQueue('/agenda/cola', { meta: { truncated: true } });

    expect(document.querySelector('.queue-truncated')?.textContent).toContain(
      QUEUE_TRUNCATED_MESSAGE,
    );
  });

  it('sin turnos: el aviso', async () => {
    open('/agenda/cola', { queue: () => [] });

    expect(await screen.findByText(QUEUE_EMPTY_MESSAGE)).toBeTruthy();
  });
});

describe('/agenda/cola: el veterinario', () => {
  const vet = meFor('veterinario', { veterinarian: { id: 4, code: 'VET-0001' } });
  const vetQueue = [
    queued(1, { at: '09:00', status: 'checked_in', checked_in_at: '2026-11-17T12:12:00.000000Z' }),
    queued(4, {
      at: '09:15',
      status: 'confirmed',
      veterinarian: null,
      schedule: { id: 9, name: 'Guardia' },
    }),
  ];

  it('su vista por defecto (sin all) con el aviso de las agendas sin veterinario; atendido en lo suyo, fichar en lo sin veterinario', async () => {
    const { recorded } = await openQueue('/agenda/cola', {
      me: vet,
      queue: () => vetQueue,
      meta: { includes_unassigned: true, veterinarian_id: 4 },
    });

    expect(recorded.queueUrls[0]?.searchParams.has('all')).toBe(false);
    expect(document.querySelector('.queue-unassigned')?.textContent).toBe(QUEUE_UNASSIGNED_NOTE);
    expect(actions(1)).toEqual(['Marcar atendido']);
    expect(actions(4)).toEqual(['Fichar']);
    expect(rowText(4)).toContain('Sin veterinario');
  });

  it('"Ver toda la sucursal" pide all=1; en un turno de otro veterinario, sin acciones', async () => {
    const { recorded } = await openQueue('/agenda/cola', {
      me: vet,
      queue: () => RECEPTION_QUEUE,
      meta: { includes_unassigned: true },
    });
    const checkbox = Array.from(
      document.querySelectorAll<HTMLInputElement>('input[type="checkbox"]'),
    ).find((input) =>
      input.closest('label, .form-check')?.textContent.includes('Ver toda la sucursal'),
    );
    expect(checkbox).toBeDefined();
    fireEvent.click(checkbox ?? document.body);

    await waitFor(() => {
      expect(recorded.queueUrls.at(-1)?.searchParams.get('all')).toBe('1');
    });
    expect(actions(2)).toEqual([]);
  });

  it('con todos=1 en la URL, entra con all=1', async () => {
    const { recorded } = await openQueue('/agenda/cola?todos=1', { me: vet });

    expect(recorded.queueUrls[0]?.searchParams.get('all')).toBe('1');
  });
});

describe('/agenda/cola: sin sucursal actual', () => {
  it('sin sucursal en me: el mensaje y ningún pedido', async () => {
    const { recorded } = open('/agenda/cola', {
      me: meFor('recepcionista', { current_branch: null }),
    });

    expect(await screen.findByText(QUEUE_NO_BRANCH_MESSAGE)).toBeTruthy();
    expect(recorded.queueUrls).toHaveLength(0);
  });

  it('el 422 APPOINTMENT_QUEUE_NO_BRANCH (la sucursal se quitó en otra pestaña): su mensaje, sin "Probá de nuevo"', async () => {
    open('/agenda/cola', {
      queueResponse: () =>
        failure(
          422,
          'APPOINTMENT_QUEUE_NO_BRANCH',
          'Elegí tu sucursal actual para ver la cola del día.',
        ),
    });

    expect(await screen.findByText(QUEUE_NO_BRANCH_MESSAGE)).toBeTruthy();
    expect(document.body.textContent).not.toContain('Probá de nuevo');
  });
});

describe('/agenda/cola: el cambio de sucursal', () => {
  it('con la cola abierta: ni filas de la anterior mientras llega la nueva, ni un pedido con la clave vieja', async () => {
    const north = { id: 11, code: 'NOR', name: 'Sucursal norte' };
    const base = meFor('recepcionista');
    let me: Me = { ...base, branches: [...base.branches, north] };
    const recorded: { at: string; branch: number }[] = [];
    let releaseNorth: () => void = () => undefined;
    const northReady = new Promise<void>((resolve) => {
      releaseNorth = resolve;
    });
    const NORTH_QUEUE = [
      queued(50, { at: '08:00', contact_name: 'Nora Norte', patient: null, client: null }),
    ];

    server.use(
      http.get('/api/me', () => success(me)),
      http.patch('/api/me/current-branch', () => {
        me = { ...me, current_branch: north };

        return success(me);
      }),
      http.get('/api/appointments/queue', async () => {
        const branch = me.current_branch?.id ?? 0;
        recorded.push({ at: 'queue', branch });

        if (branch === north.id) {
          await northReady;
        }

        return success(branch === north.id ? NORTH_QUEUE : RECEPTION_QUEUE, {
          meta: {
            date: '2026-11-17',
            branch_id: branch,
            veterinarian_id: null,
            includes_unassigned: false,
            truncated: false,
          },
        });
      }),
      http.get('/api/schedules/:id/blocks', () => success([], { meta: pagination({ total: 0 }) })),
    );
    const { queryClient } = renderApp('/agenda/cola', { session: sessionFor() });
    await waitFor(() => {
      expect(rows()).toHaveLength(4);
    });

    const selector = document.querySelector<HTMLSelectElement>('.branch-selector');
    expect(selector).not.toBeNull();
    fireEvent.change(selector ?? document.body, { target: { value: '11' } });

    // La nueva todavía no llegó: la cola de Casa central ya no se ve.
    await waitFor(() => {
      expect(recorded.filter((item) => item.branch === north.id)).toHaveLength(1);
    });
    expect(rows()).toHaveLength(0);
    expect(document.body.textContent).toContain('Cargando la cola…');

    releaseNorth();
    await waitFor(() => {
      expect(rowText(50)).toContain('Nora Norte');
    });
    expect(rows()).toHaveLength(1);
    // Un solo pedido después del cambio (el de la clave nueva), y la clave vieja conserva la cola de su sucursal.
    expect(recorded).toEqual([
      { at: 'queue', branch: 10 },
      { at: 'queue', branch: 11 },
    ]);
    const old = queryClient.getQueriesData<{ items: QueueAppointment[] }>({
      queryKey: ['branch', 10, 'appointments', 'queue'],
    });
    expect(old.flatMap(([, value]) => value?.items.map((item) => item.id) ?? [])).toEqual([
      1, 2, 3, 4,
    ]);
  });
});

describe('/agenda/cola: una respuesta de otra sucursal', () => {
  it('la sucursal cambió en otra pestaña antes del aviso: la cola de la otra no se muestra y se vuelve a pedir me', async () => {
    vi.useFakeTimers({
      shouldAdvanceTime: true,
      toFake: ['setInterval', 'clearInterval', 'Date'],
      now: NOW,
    });
    let otherBranch = false;
    let meRequests = 0;
    await openQueue('/agenda/cola', {
      queueResponse: () =>
        success(
          otherBranch
            ? [queued(50, { at: '08:00', patient: null, client: null, contact_name: 'Nora Norte' })]
            : RECEPTION_QUEUE,
          {
            meta: {
              date: '2026-11-17',
              branch_id: otherBranch ? 11 : 10,
              veterinarian_id: null,
              includes_unassigned: false,
              truncated: false,
            },
          },
        ),
    });
    server.use(
      http.get('/api/me', () => {
        meRequests += 1;

        return success(meFor('recepcionista'));
      }),
    );

    otherBranch = true;
    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });

    await waitFor(() => {
      expect(meRequests).toBe(1);
    });
    expect(rows()).toHaveLength(4);
    expect(row(50)).toBeNull();
  });
});

describe('/agenda/cola: las acciones', () => {
  it('fichar: el pedido, el aviso, la fila con su estado, solo la cola vuelta a pedir y el foco en la fila', async () => {
    const { recorded } = await openQueue();
    const before = recorded.otherGets.length;
    const fichar = buttonIn(row(2), 'Fichar');
    fichar.focus();
    fireEvent.click(fichar);

    await waitFor(() => {
      expect(toastText().join(' ')).toContain('Turno fichado.');
    });
    expect(recorded.actions).toEqual(['check-in:2']);
    await waitFor(() => {
      expect(recorded.queueUrls).toHaveLength(2);
    });
    await waitFor(() => {
      expect(rowText(2)).toContain('Esperando desde las 10:00 (menos de 1 min)');
    });
    // Ni el día, ni el detalle, ni otros bloqueos: solo la cola.
    expect(recorded.otherGets.slice(before)).toEqual([]);
    expect(document.activeElement).toBe(row(2)?.querySelector('.queue-row-title'));
    // El cambio propio no se anuncia otra vez en la región de la cola (ya lo dijo el aviso).
    expect(status()).toBe('Cola del martes 17 de noviembre: 4 turnos.');
  });

  it('el veterinario marca atendido su fichado', async () => {
    const vet = meFor('veterinario', { veterinarian: { id: 4, code: 'VET-0001' } });
    const { recorded } = await openQueue('/agenda/cola', { me: vet });
    fireEvent.click(buttonIn(row(1), 'Marcar atendido'));

    await waitFor(() => {
      expect(rowText(1)).toContain('Atendido');
    });
    expect(recorded.actions).toEqual(['attend:1']);
    expect(rowText(1)).not.toContain('Esperando');
  });

  it('el 409 CONFLICT: el diálogo con "Probá de nuevo" y la cola vuelta a pedir', async () => {
    let first = true;
    const { recorded } = await openQueue('/agenda/cola', {
      actionResponse: () => {
        if (first) {
          first = false;

          return failure(409, 'CONFLICT', 'El turno cambió mientras guardabas.');
        }

        return undefined;
      },
    });
    fireEvent.click(buttonIn(row(2), 'Fichar'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('El turno cambió mientras guardabas.');
    });
    await waitFor(() => {
      expect(recorded.queueUrls).toHaveLength(2);
    });
    fireEvent.click(buttonIn(dialog(), 'Probá de nuevo'));
    await waitFor(() => {
      expect(toastText().join(' ')).toContain('Turno fichado.');
    });
    expect(recorded.actions).toEqual(['check-in:2', 'check-in:2']);
  });

  it('un 422 de negocio: el mensaje y solo "Cerrar"', async () => {
    await openQueue('/agenda/cola', {
      actionResponse: () =>
        failure(422, 'APPOINTMENT_CHECK_IN_NOT_TODAY', 'Solo se fichan los turnos del día.'),
    });
    fireEvent.click(buttonIn(row(2), 'Fichar'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('Solo se fichan los turnos del día.');
    });
    expect(() => buttonIn(dialog(), 'Probá de nuevo')).toThrow();
  });

  it('el 404 saca el turno de la cola: el foco pasa a la fila que quedó en su lugar', async () => {
    let current = RECEPTION_QUEUE;
    await openQueue('/agenda/cola', {
      queue: () => current,
      actionResponse: () => {
        current = RECEPTION_QUEUE.filter((item) => item.id !== 2);

        return failure(404, 'RESOURCE_NOT_FOUND', 'El turno no existe.');
      },
    });
    const fichar = buttonIn(row(2), 'Fichar');
    fichar.focus();
    fireEvent.focus(fichar);
    fireEvent.click(fichar);

    await waitFor(() => {
      expect(row(2)).toBeNull();
    });
    await waitFor(() => {
      expect(document.activeElement).toBe(row(3)?.querySelector('.queue-row-title'));
    });
  });

  it('si sale la última fila de la cola, el foco va al aviso sin turnos (no al body)', async () => {
    let current = RECEPTION_QUEUE.filter((item) => item.id === 3);
    await openQueue('/agenda/cola', {
      queue: () => current,
      actionResponse: () => {
        current = [];

        return failure(404, 'RESOURCE_NOT_FOUND', 'El turno no existe.');
      },
    });
    const fichar = buttonIn(row(3), 'Fichar');
    fichar.focus();
    fireEvent.focus(fichar);
    fireEvent.click(fichar);

    await waitFor(() => {
      expect(document.querySelector('.queue-empty')?.textContent).toBe(QUEUE_EMPTY_MESSAGE);
    });
    await waitFor(() => {
      expect(document.activeElement).toBe(document.querySelector('.queue-empty'));
    });
  });
});

describe('/agenda/cola: la actualización', () => {
  function hide(hidden: boolean) {
    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      get: () => (hidden ? 'hidden' : 'visible'),
    });
    document.dispatchEvent(new Event('visibilitychange', { bubbles: true }));
  }

  afterEach(() => {
    hide(false);
  });

  it('cada 60 s, y anuncia solo lo que cambió (no la cola entera)', async () => {
    vi.useFakeTimers({
      shouldAdvanceTime: true,
      toFake: ['setInterval', 'clearInterval', 'Date'],
      now: NOW,
    });
    let current = RECEPTION_QUEUE;
    const { recorded } = await openQueue('/agenda/cola', { queue: () => current });
    await waitFor(() => {
      expect(status()).toBe('Cola del martes 17 de noviembre: 4 turnos.');
    });

    current = [
      ...RECEPTION_QUEUE,
      queued(5, { at: '11:00', patient: null, client: null, contact_name: 'Rita Ruiz' }),
    ];
    await act(async () => {
      await vi.advanceTimersByTimeAsync(60_000);
    });

    await waitFor(() => {
      expect(recorded.queueUrls).toHaveLength(2);
    });
    await waitFor(() => {
      expect(status()).toBe('La cola cambió. Nuevo: 11:00, Rita Ruiz.');
    });
  });

  it('en pausa con un diálogo abierto o la pestaña oculta; al volver a la pestaña, se pide', async () => {
    vi.useFakeTimers({
      shouldAdvanceTime: true,
      toFake: ['setInterval', 'clearInterval', 'Date'],
      now: NOW,
    });
    const { recorded } = await openQueue();

    let release: () => void = () => undefined;
    act(() => {
      release = pauseAutoRefresh();
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(180_000);
    });
    expect(recorded.queueUrls).toHaveLength(1);
    act(() => {
      release();
    });

    act(() => {
      hide(true);
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(180_000);
    });
    expect(recorded.queueUrls).toHaveLength(1);

    // Más de 30 s después (la cola ya está vieja): al volver, se pide sin esperar al minuto.
    vi.setSystemTime(new Date(NOW.getTime() + 5 * 60_000));
    act(() => {
      hide(false);
    });
    await waitFor(() => {
      expect(recorded.queueUrls).toHaveLength(2);
    });
  });
});
