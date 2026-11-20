import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { ApiError, NETWORK_ERROR } from '../../lib/api/errors';
import { can } from '../../lib/auth/permissions';
import { appointment } from '../../test/appointments';
import { meFor, ROLES, type Role } from '../../test/roles';
import { STATUS_ICONS, statusLook, statusRowClass } from './status-icons';
import {
  availableTransitions,
  CANCELLATION_MESSAGES,
  cancellationReasonError,
  isSomeoneElses,
  noShowHint,
  NOT_TODAY_HINT,
  PATIENT_REQUIRED_HINT,
  TRANSITIONS,
  transitionFailure,
  type TransitionContext,
} from './transitions';
import { EDITABLE_STATUSES, OCCUPYING_STATUSES, type Appointment } from './types';

// Los estados del turno (3.5): la tabla contra la foto del backend (appointment-transitions.json, deuda 27), las acciones
// por estado y por rol (los permisos reales de clinic-roles.json), las reglas de hora con un "ahora" fijo (martes
// 17/11/2026 en Asunción) y el trato de cada error.

const backend = (path: string) =>
  readFileSync(new URL(`../../../../backend/${path}`, import.meta.url), 'utf8');

/** 17/11/2026 a las 10:00 en Asunción (13:00 en UTC). */
const NOW = new Date('2026-11-17T13:00:00Z');
const VET_FICHA = 4;

function contextFor(role: Role, now = NOW, overrides: Partial<ReturnType<typeof meFor>> = {}) {
  const me = meFor(role, {
    veterinarian: role === 'veterinario' ? { id: VET_FICHA, code: 'VET-0001' } : null,
    ...overrides,
  });

  return { can: (permission: string) => can(me, permission), me, now } satisfies TransitionContext;
}

const names = (turn: Appointment, context: TransitionContext) =>
  availableTransitions(turn, context).map((item) => item.transition.name);

describe('transitions.ts contra la foto del backend (appointment-transitions.json)', () => {
  // La arma AppointmentTransitionsSnapshotTest desde AppointmentTransition, las rutas, el middleware del controlador y los
  // roles sembrados (deuda 27): un cambio en el backend rompe ese test y, con la foto actualizada, este. Las reglas del
  // servicio (el turno ajeno, el día, la hora del ausente, el paciente) las cubren los tests del backend.
  const snapshot = JSON.parse(backend('tests/Fixtures/appointment-transitions.json')) as {
    statuses: { value: string; label: string; occupies_slot: boolean; editable: boolean }[];
    transitions: {
      name: string;
      from: string[];
      to: string;
      path: string;
      permission: string;
      roles: string[];
    }[];
  };

  it('los mismos estados, con los que ocupan el horario y los que se editan', () => {
    const values = snapshot.statuses.map((status) => status.value);
    const where = (key: 'occupies_slot' | 'editable') =>
      snapshot.statuses
        .filter((status) => status[key])
        .map((status) => status.value)
        .sort();

    expect(Object.keys(STATUS_ICONS).sort()).toEqual([...values].sort());
    expect([...OCCUPYING_STATUSES].sort()).toEqual(where('occupies_slot'));
    expect([...EDITABLE_STATUSES].sort()).toEqual(where('editable'));
  });

  it('las mismas acciones, en el mismo orden, con sus orígenes, su destino, su ruta y su permiso', () => {
    expect(
      TRANSITIONS.map(({ name, from, to, path, permission }) => ({
        name,
        from: [...from],
        to,
        path,
        permission,
      })),
    ).toEqual(
      snapshot.transitions.map(({ name, from, to, path, permission }) => ({
        name,
        from,
        to,
        path,
        permission,
      })),
    );
  });

  it('cada rol tiene el permiso de las acciones que la foto le da, y solo esas', () => {
    for (const transition of snapshot.transitions) {
      for (const role of ROLES) {
        expect(can(meFor(role), transition.permission), `${transition.name}, ${role}`).toBe(
          transition.roles.includes(role),
        );
      }
    }
  });

  it('los mensajes son los de la API (CancelAppointmentRequest y AppointmentTransitionService)', () => {
    const request = backend('app/Http/Requests/CancelAppointmentRequest.php');
    const service = backend('app/Services/AppointmentTransitionService.php');

    for (const message of Object.values(CANCELLATION_MESSAGES)) {
      expect(request).toContain(message);
    }

    expect(request).toContain("'min:3', 'max:500'");
    expect(service).toContain(NOT_TODAY_HINT);
  });
});

describe('las acciones por estado y por rol', () => {
  // Hoy a las 09:00 (ya empezó), con paciente, de la ficha 4 (la del veterinario de los tests).
  const at = (status: string, overrides: Parameters<typeof appointment>[1] = {}) =>
    appointment(1, { status, day: '2026-11-17', at: '09:00', ...overrides });

  const table: [string, Record<Role, string[]>][] = [
    [
      'pending_confirmation',
      {
        administrador: ['confirm', 'check_in', 'no_show', 'cancel'],
        recepcionista: ['confirm', 'check_in', 'no_show', 'cancel'],
        veterinario: ['check_in'],
      },
    ],
    [
      'confirmed',
      {
        administrador: ['check_in', 'no_show', 'cancel'],
        recepcionista: ['check_in', 'no_show', 'cancel'],
        veterinario: ['check_in'],
      },
    ],
    [
      'checked_in',
      {
        administrador: ['attend', 'cancel'],
        recepcionista: ['cancel'],
        veterinario: ['attend'],
      },
    ],
    ['attended', { administrador: [], recepcionista: [], veterinario: [] }],
    [
      'no_show',
      { administrador: ['undo_no_show'], recepcionista: ['undo_no_show'], veterinario: [] },
    ],
    ['cancelled', { administrador: [], recepcionista: [], veterinario: [] }],
  ];

  for (const [status, byRole] of table) {
    for (const role of ['administrador', 'recepcionista', 'veterinario'] as const) {
      it(`${status}, ${role}: ${byRole[role].join(', ') || 'ninguna'}`, () => {
        expect(names(at(status), contextFor(role))).toEqual(byRole[role]);
      });
    }
  }

  it('el veterinario, en un turno de otra ficha: sin "Fichar" ni "Atendido"', () => {
    const foreign = { veterinarian: { id: 99, code: 'VET-0099', name: 'Otra' } };

    expect(names(at('confirmed', foreign), contextFor('veterinario'))).toEqual([]);
    expect(names(at('checked_in', foreign), contextFor('veterinario'))).toEqual([]);
    // La recepción y el administrador, en cualquiera.
    expect(names(at('checked_in', foreign), contextFor('administrador'))).toContain('attend');
    expect(names(at('confirmed', foreign), contextFor('recepcionista'))).toContain('check_in');
  });

  it('el veterinario, en una agenda sin veterinario: "Fichar" y "Atendido"', () => {
    expect(names(at('confirmed', { veterinarian: null }), contextFor('veterinario'))).toEqual([
      'check_in',
    ]);
    expect(names(at('checked_in', { veterinarian: null }), contextFor('veterinario'))).toEqual([
      'attend',
    ]);
  });

  it('isSomeoneElses: con el rol veterinario y sin ficha, solo las agendas sin veterinario', () => {
    const noFicha = contextFor('veterinario', NOW, { veterinarian: null });

    expect(isSomeoneElses(at('confirmed'), noFicha)).toBe(true);
    expect(isSomeoneElses(at('confirmed', { veterinarian: null }), noFicha)).toBe(false);
    expect(isSomeoneElses(at('confirmed'), contextFor('veterinario'))).toBe(false);
  });
});

describe('las reglas de hora y del paciente (deshabilitadas con el motivo, u ocultas)', () => {
  const reasons = (turn: Appointment, context = contextFor('recepcionista')) =>
    Object.fromEntries(
      availableTransitions(turn, context).map((item) => [
        item.transition.name,
        item.disabledReason,
      ]),
    );

  it('fichar sin paciente: deshabilitado, "Para fichar, cargá el paciente." (antes que el día)', () => {
    const turn = appointment(1, { day: '2026-11-18', patient: null, client: null });
    const found = availableTransitions(turn, contextFor('recepcionista')).find(
      (item) => item.transition.name === 'check_in',
    );

    expect(found?.disabledReason).toBe(PATIENT_REQUIRED_HINT);
    expect(found?.needsPatient).toBe(true);
  });

  it('fichar otro día: deshabilitado, "Solo se fichan los turnos del día."; hoy, habilitado', () => {
    expect(reasons(appointment(1, { day: '2026-11-18' })).check_in).toBe(NOT_TODAY_HINT);
    expect(reasons(appointment(1, { day: '2026-11-16' })).check_in).toBe(NOT_TODAY_HINT);
    expect(reasons(appointment(1, { day: '2026-11-17', at: '15:00' })).check_in).toBeNull();
  });

  it('hoy en Asunción aunque en UTC ya sea mañana (22:30 del 17 = 01:30Z del 18)', () => {
    const late = new Date('2026-11-18T01:30:00Z');
    const turn = appointment(1, { day: '2026-11-17', at: '22:00' });

    expect(reasons(turn, contextFor('recepcionista', late)).check_in).toBeNull();
  });

  it('el ausente antes del inicio: "Se marca desde las 10:30."; desde el inicio, habilitado', () => {
    expect(reasons(appointment(1, { day: '2026-11-17', at: '10:30' })).no_show).toBe(
      'Se marca desde las 10:30.',
    );
    // A las 10:00 en punto, el de las 10:00 ya empezó (la API: starts_at > now).
    expect(reasons(appointment(1, { day: '2026-11-17', at: '10:00' })).no_show).toBeNull();
    expect(noShowHint(appointment(1, { day: '2026-11-20', at: '09:30' }).starts_at, NOW)).toBe(
      'Se marca desde el 20/11/2026 a las 09:30.',
    );
  });

  it('deshacer el ausente: oculto otro día', () => {
    expect(
      names(appointment(1, { status: 'no_show', day: '2026-11-16' }), contextFor('recepcionista')),
    ).toEqual([]);
    expect(
      reasons(appointment(1, { status: 'no_show', day: '2026-11-17' })).undo_no_show,
    ).toBeNull();
  });

  it('deshacer el ausente de un sobreturno: solo con appointments.overbook (la API responde 403)', () => {
    const overbooked = appointment(1, {
      status: 'no_show',
      day: '2026-11-17',
      is_overbooked: true,
    });
    const recepcion = contextFor('recepcionista');
    const sinSobreturno = {
      ...recepcion,
      can: (permission: string) =>
        permission !== 'appointments.overbook' && recepcion.can(permission),
    };

    expect(names(overbooked, recepcion)).toEqual(['undo_no_show']);
    expect(names(overbooked, sinSobreturno)).toEqual([]);
    expect(names(appointment(1, { status: 'no_show', day: '2026-11-17' }), sinSobreturno)).toEqual([
      'undo_no_show',
    ]);
  });
});

describe('el motivo de la cancelación', () => {
  it('obligatorio, de 3 a 500 caracteres (puntos de código), sin contar los espacios de los extremos', () => {
    expect(cancellationReasonError('')).toBe(CANCELLATION_MESSAGES.required);
    expect(cancellationReasonError('   ')).toBe(CANCELLATION_MESSAGES.required);
    expect(cancellationReasonError(' ab ')).toBe(CANCELLATION_MESSAGES.min);
    expect(cancellationReasonError('abc')).toBeNull();
    expect(cancellationReasonError('ñ'.repeat(500))).toBeNull();
    expect(cancellationReasonError('😀'.repeat(500))).toBeNull();
    expect(cancellationReasonError('a'.repeat(501))).toBe(CANCELLATION_MESSAGES.max);
    expect(cancellationReasonError(` ${'a'.repeat(500)}\n`)).toBeNull();
  });

  it('los invisibles de los extremos no cuentan, como en la API (TrimStrings): "\\u200Bab" tiene 2', () => {
    expect(cancellationReasonError('​ab')).toBe(CANCELLATION_MESSAGES.min);
    expect(cancellationReasonError('ㅤab﻿')).toBe(CANCELLATION_MESSAGES.min);
    expect(cancellationReasonError('<b>&"')).toBeNull();
  });
});

describe('transitionFailure', () => {
  const api = (status: number, errorCode: string, message = 'Mensaje de la API.', errors = {}) =>
    new ApiError({ status, errorCode, message, errors });

  it('un 422 de negocio: el mensaje, solo "Cerrar" y el turno vuelto a pedir', () => {
    const found = transitionFailure(
      api(
        422,
        'APPOINTMENT_INVALID_TRANSITION',
        'El turno está «Cancelado» y no admite ese cambio.',
      ),
    );

    expect(found).toMatchObject({
      message: 'El turno está «Cancelado» y no admite ese cambio.',
      final: true,
      refresh: true,
      overlap: false,
    });
  });

  it('el 409 CONFLICT: probar de nuevo (no final) y volver a pedir', () => {
    expect(transitionFailure(api(409, 'CONFLICT'))).toMatchObject({ final: false, refresh: true });
  });

  it('el 409 APPOINTMENT_OVERLAP (deshacer el ausente): la marca del sobreturno', () => {
    expect(transitionFailure(api(409, 'APPOINTMENT_OVERLAP'))).toMatchObject({
      overlap: true,
      final: true,
      refresh: true,
    });
  });

  it('APPOINTMENT_PATIENT_REQUIRED lleva a cargar el paciente; NOT_YOURS (403), solo "Cerrar"', () => {
    expect(transitionFailure(api(422, 'APPOINTMENT_PATIENT_REQUIRED')).needsPatient).toBe(true);
    expect(transitionFailure(api(403, 'APPOINTMENT_NOT_YOURS'))).toMatchObject({
      final: true,
      needsPatient: false,
    });
  });

  it('el 422 del motivo va al campo; el del paciente fallecido, al mensaje', () => {
    expect(
      transitionFailure(
        api(422, 'VALIDATION_ERROR', 'Datos inválidos.', {
          cancellation_reason: [CANCELLATION_MESSAGES.min],
        }),
      ).reasonError,
    ).toBe(CANCELLATION_MESSAGES.min);
    expect(
      transitionFailure(
        api(422, 'VALIDATION_ERROR', 'Datos inválidos.', {
          patient_id: ['El paciente falleció: no se puede fichar el turno.'],
        }),
      ),
    ).toMatchObject({
      message: 'El paciente falleció: no se puede fichar el turno.',
      reasonError: null,
      final: true,
    });
  });

  it('la red: probar de nuevo, sin volver a pedir; el 404, ya no existe', () => {
    expect(transitionFailure(api(0, NETWORK_ERROR))).toMatchObject({
      final: false,
      refresh: false,
    });
    expect(transitionFailure(api(404, 'RESOURCE_NOT_FOUND')).gone).toBe(true);
  });
});

describe('cómo se ve cada estado (igual en todas las vistas)', () => {
  it('el ausente, atenuado; el cancelado, tachado; los demás, normales', () => {
    expect(
      ['pending_confirmation', 'confirmed', 'checked_in', 'attended', 'no_show', 'cancelled'].map(
        statusLook,
      ),
    ).toEqual([null, null, null, null, 'dimmed', 'cancelled']);
    expect(statusRowClass('cancelled')).toBe('appointment-row-cancelled');
    expect(statusRowClass('no_show')).toBe('appointment-row-dimmed');
    expect(statusRowClass('confirmed')).toBeUndefined();
  });
});
