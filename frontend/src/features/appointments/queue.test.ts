import { describe, expect, it } from 'vitest';
import { appointment, block } from '../../test/appointments';
import { appointmentKeys } from './api';
import {
  buildQueue,
  waitingDuration,
  patientKind,
  queueChanges,
  queueFilters,
  queueTitleAnnouncement,
  waitingText,
} from './queue';
import { validateQueueSearch } from './search';
import type { QueueAppointment } from './types';

// La cola del día (3.7), sin DOM y con la PC en UTC (vite.config: TZ=UTC): las horas tienen que salir en Asunción (UTC-3).

/** Un turno de la cola: el del listado con la especie y la raza del paciente y `checked_in_at`. */
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

const VET = { veterinarian: { id: 4, code: 'VET-0001' } };
const NO_VET = { veterinarian: null };

describe('los filtros del pedido según el rol', () => {
  const now = new Date('2026-11-17T13:00:00Z');

  it('el veterinario con ficha: la vista por defecto (sin all) y, con "Ver toda la sucursal", all', () => {
    expect(queueFilters({}, VET, now)).toEqual({
      date: '2026-11-17',
      all: false,
      veterinarian_id: null,
      schedule_id: null,
    });
    expect(queueFilters({ todos: 1 }, VET, now).all).toBe(true);
  });

  it('la recepción y el administrador (sin ficha) nunca mandan all, aunque la URL traiga todos=1', () => {
    expect(queueFilters({ todos: 1 }, NO_VET, now).all).toBe(false);
    expect(queueFilters({}, undefined, now).all).toBe(false);
  });

  it('el día por defecto es hoy en Asunción, no en la PC: a las 23:30 de Asunción la PC en UTC ya es el día siguiente', () => {
    // 2026-11-21T02:30Z = viernes 20 a las 23:30 en Asunción.
    expect(queueFilters({}, VET, new Date('2026-11-21T02:30:00Z')).date).toBe('2026-11-20');
    // 03:00Z = 00:00 del sábado 21 en Asunción.
    expect(queueFilters({}, VET, new Date('2026-11-21T03:00:00Z')).date).toBe('2026-11-21');
    expect(queueFilters({ fecha: '2026-11-18' }, VET, now).date).toBe('2026-11-18');
  });

  it('la clave lleva la sucursal y todos los filtros (D4)', () => {
    const filters = queueFilters({ todos: 1 }, VET, now);

    expect(appointmentKeys.queue(10, filters)).toEqual([
      'branch',
      10,
      'appointments',
      'queue',
      { date: '2026-11-17', all: true, veterinarian_id: null, schedule_id: null },
    ]);
    expect(appointmentKeys.queue(11, filters)).not.toEqual(appointmentKeys.queue(10, filters));
  });

  it('validateQueueSearch descarta una fecha mal formada o inexistente y un todos desconocido', () => {
    expect(validateQueueSearch({ fecha: '2026-11-17', todos: '1' })).toEqual({
      fecha: '2026-11-17',
      todos: 1,
    });
    expect(validateQueueSearch({ fecha: '2026-02-30', todos: 'si' })).toEqual({
      fecha: undefined,
      todos: undefined,
    });
  });
});

describe('el armado de la cola', () => {
  const now = new Date('2026-11-17T13:00:00Z');
  const none = new Map();

  it('conserva el orden de la API (la hora del turno), no el de llegada', () => {
    const items = [
      queued(1, { at: '09:00', status: 'checked_in', checked_in_at: '2026-11-17T12:40:00Z' }),
      queued(2, { at: '09:30', status: 'checked_in', checked_in_at: '2026-11-17T12:05:00Z' }),
      queued(3, { at: '10:00', status: 'confirmed' }),
    ];

    expect(buildQueue(items, none, '2026-11-17', now).map((row) => row.appointment.id)).toEqual([
      1, 2, 3,
    ]);
  });

  it('dos sobreturnos a la misma hora quedan los dos, en el orden de la API, con su marca', () => {
    const items = [queued(5, { at: '09:00' }), queued(4, { at: '09:00', is_overbooked: true })];
    const rows = buildQueue(items, none, '2026-11-17', now);

    expect(rows.map((row) => row.appointment.id)).toEqual([5, 4]);
    expect(rows.map((row) => row.appointment.is_overbooked)).toEqual([false, true]);
  });

  it('"Dentro de un bloqueo": el que se superpone, no el que solo lo toca ni un ausente', () => {
    const blocks = new Map([[7, [block(1, '10:00', '11:00')]]]);
    const rows = buildQueue(
      [
        queued(1, { at: '10:15' }),
        queued(2, { at: '09:30', until: '10:00' }),
        queued(3, { at: '10:30', status: 'no_show' }),
      ],
      blocks,
      '2026-11-17',
      now,
    );

    expect(rows.map((row) => row.inBlock)).toEqual([true, false, false]);
  });
});

describe('la espera de los fichados', () => {
  it('con la hora de Asunción y los minutos desde que se fichó', () => {
    const turn = queued(1, { status: 'checked_in', checked_in_at: '2026-11-17T12:12:00.000000Z' });

    expect(waitingText(turn, '2026-11-17', new Date('2026-11-17T12:26:30Z'))).toBe(
      'Esperando desde las 09:12 (14 min)',
    );
  });

  it('cerca de la medianoche de Asunción: sigue contando hasta las 00:00 de Asunción, después es otro día', () => {
    // Fichado el viernes 20 a las 23:40 de Asunción (02:40Z del sábado).
    const turn = queued(1, {
      day: '2026-11-20',
      at: '23:30',
      status: 'checked_in',
      checked_in_at: '2026-11-21T02:40:00Z',
    });

    expect(waitingText(turn, '2026-11-20', new Date('2026-11-21T02:55:00Z'))).toBe(
      'Esperando desde las 23:40 (15 min)',
    );
    expect(waitingText(turn, '2026-11-20', new Date('2026-11-21T03:05:00Z'))).toBe(
      'Fichado a las 23:40',
    );
  });

  it('sin fichar, o ya atendido: sin espera', () => {
    const now = new Date('2026-11-17T13:00:00Z');

    expect(waitingText(queued(1, { status: 'confirmed' }), '2026-11-17', now)).toBeNull();
    expect(
      waitingText(
        queued(2, { status: 'attended', checked_in_at: '2026-11-17T12:00:00Z' }),
        '2026-11-17',
        now,
      ),
    ).toBeNull();
  });

  it('los textos de la duración', () => {
    expect(waitingDuration(0)).toBe('menos de 1 min');
    expect(waitingDuration(59)).toBe('59 min');
    expect(waitingDuration(60)).toBe('1 h');
    expect(waitingDuration(65)).toBe('1 h 05 min');
  });

  it('la especie y la raza del paciente; sin paciente, vacío', () => {
    expect(patientKind(queued(1))).toBe('Canino · Mestizo');
    expect(patientKind(queued(2, { patient: null, client: null, contact_name: 'Ana' }))).toBe('');
  });
});

describe('el anuncio de lo que cambió', () => {
  const one = queued(1, { at: '09:00' });
  const two = queued(2, { at: '09:30', patient: null, client: null, contact_name: 'Pedro Paz' });

  it('sin cambios, nada', () => {
    expect(queueChanges([one, two], [one, two])).toBeNull();
  });

  it('nuevo, cambio de estado y salida, uno por uno', () => {
    const fichado = { ...one, status: { value: 'checked_in', label: 'Fichado' } };
    const three = queued(3, { at: '10:00', patient: null, client: null, contact_name: 'Rita' });

    expect(queueChanges([one, two], [fichado, three])).toBe(
      'La cola cambió. Nuevo: 10:00, Rita. 09:00, Luna, de Laura Martínez: Fichado. Salió de la cola: 09:30, Pedro Paz.',
    );
  });

  it('el cambio propio (ya anunciado por el aviso) no se repite', () => {
    const fichado = { ...one, status: { value: 'checked_in', label: 'Fichado' } };

    expect(queueChanges([one], [fichado], new Map([[1, 'checked_in']]))).toBeNull();
  });

  it('con más de tres cambios, solo las cuentas', () => {
    const news = [3, 4, 5].map((id) => queued(id, { at: '11:00' }));

    expect(queueChanges([one, two], [...news, one])).toBe(
      'La cola cambió: 3 turnos nuevos, 1 turno salió.',
    );
  });

  it('el título del anuncio: el día y la cuenta', () => {
    expect(queueTitleAnnouncement('2026-11-17', 0)).toBe(
      'Cola del martes 17 de noviembre: sin turnos.',
    );
    expect(queueTitleAnnouncement('2026-11-17', 1)).toBe(
      'Cola del martes 17 de noviembre: 1 turno.',
    );
  });
});
