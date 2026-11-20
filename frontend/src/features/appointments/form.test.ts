import { describe, expect, it } from 'vitest';
import { ApiError } from '../../lib/api/errors';
import { appointment } from '../../test/appointments';
import {
  APPOINTMENT_MESSAGES as M,
  appointmentFormSchema,
  appointmentFormToPayload,
  appointmentSeriesPayload,
  appointmentToForm,
  EMPTY_APPOINTMENT_FORM,
  mapAppointmentErrors,
  seriesInputOf,
  type AppointmentFormValues,
} from './form';
import { SERIES_MESSAGES } from './series';
import type { AppointmentDetail } from './types';

const LUNA = {
  id: 9,
  code: 'PAC-0009',
  name: 'Luna',
  client: { id: 3, display_name: 'Laura Martínez' },
};

const VALID: AppointmentFormValues = {
  ...EMPTY_APPOINTMENT_FORM,
  who: 'patient',
  patient: LUNA,
  schedule_id: '7',
  service_id: '3',
  duration_minutes: '30',
  date: '2026-11-17',
  time: '09:30',
};

function detail(overrides: Partial<AppointmentDetail> = {}): AppointmentDetail {
  return {
    ...appointment(1, { at: '09:30', until: '10:00' }),
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

/** Los mensajes de Zod por campo. */
function errors(values: Partial<AppointmentFormValues>, today = '2026-11-17') {
  const result = appointmentFormSchema(today).safeParse({ ...VALID, ...values });

  if (result.success) {
    return {};
  }

  return Object.fromEntries(
    result.error.issues.map((issue) => [issue.path.join('.'), issue.message]),
  );
}

describe('el esquema del turno', () => {
  it('acepta un turno completo', () => {
    expect(errors({})).toEqual({});
  });

  it('la duración en su borde: 720 pasa, 725 no; múltiplos de 5', () => {
    expect(errors({ time: '08:00', duration_minutes: '720' })).toEqual({});
    expect(errors({ time: '08:00', duration_minutes: '725' })).toEqual({
      duration_minutes: M.tooLong,
    });
    expect(errors({ duration_minutes: '32' })).toEqual({ duration_minutes: M.offGrid });
    expect(errors({ duration_minutes: '0' })).toEqual({ duration_minutes: M.endsBeforeStart });
    expect(errors({ duration_minutes: '' })).toEqual({ duration_minutes: M.endsBeforeStart });
  });

  it('las 23:55 pasan; el fin a las 00:00 del día siguiente, no (H10)', () => {
    expect(errors({ time: '23:55', duration_minutes: '5' })).toEqual({
      duration_minutes: M.crossesMidnight,
    });
    expect(errors({ time: '23:50', duration_minutes: '5' })).toEqual({});
    expect(errors({ time: '23:30', duration_minutes: '30' })).toEqual({
      duration_minutes: M.crossesMidnight,
    });
  });

  it('la hora en la grilla de 5 minutos', () => {
    expect(errors({ time: '09:32' })).toEqual({ time: M.offGrid });
    expect(errors({ time: '' })).toEqual({ time: M.startRequired });
  });

  it('ayer no; hoy sí, también una hora que ya pasó (la API la acepta)', () => {
    expect(errors({ date: '2026-11-16' })).toEqual({ date: M.pastDay });
    expect(errors({ date: '2026-11-17', time: '00:05' })).toEqual({});
    expect(errors({ date: '' })).toEqual({ date: M.startRequired });
  });

  it('la edición de un turno de ayer que no se mueve pasa (la API revisa el día solo al mover); moverlo, no', () => {
    const original: AppointmentFormValues = { ...VALID, date: '2026-11-16', reason: '' };
    const schema = appointmentFormSchema('2026-11-17', original);

    expect(schema.safeParse({ ...original, reason: 'Llegó tarde' }).success).toBe(true);
    expect(schema.safeParse({ ...original, time: '10:00' }).success).toBe(false);
    expect(schema.safeParse({ ...original, duration_minutes: '45' }).success).toBe(false);
    expect(schema.safeParse({ ...original, schedule_id: '8' }).success).toBe(false);
  });

  it('"hoy" es el de Asunción, no el de la PC: con la PC en UTC ya en el 18, el 17 sigue siendo hoy', () => {
    // El 17/11 a las 22:30 en Asunción son las 01:30 del 18 en UTC: la página calcula `today` con todayInAppZone().
    expect(errors({ date: '2026-11-17' }, '2026-11-17')).toEqual({});
    expect(errors({ date: '2026-11-17' }, '2026-11-18')).toEqual({ date: M.pastDay });
  });

  it('la agenda es obligatoria', () => {
    expect(errors({ schedule_id: '' })).toEqual({ schedule_id: M.scheduleRequired });
  });

  it('quién: el paciente o el cliente elegidos, con el mensaje de la API', () => {
    expect(errors({ patient: null })).toEqual({ patient: M.whoRequired });
    expect(errors({ who: 'client', patient: null, client: null })).toEqual({
      client: M.whoRequired,
    });
    expect(
      errors({ who: 'client', patient: null, client: { id: 3, display_name: 'Laura Martínez' } }),
    ).toEqual({});
  });

  it('quién: el contacto, con nombre y teléfono juntos', () => {
    const contact = { who: 'contact' as const, patient: null };

    expect(errors({ ...contact })).toEqual({ contact_name: M.whoRequired });
    expect(errors({ ...contact, contact_name: 'Pedro' })).toEqual({
      contact_phone: M.contactTogether,
    });
    expect(errors({ ...contact, contact_phone: '981123456' })).toEqual({
      contact_name: M.contactTogether,
    });
    expect(errors({ ...contact, contact_name: 'Pedro', contact_phone: '0981 123-456' })).toEqual({
      contact_phone: M.contactPhoneFormat,
    });
    expect(errors({ ...contact, contact_name: 'Pedro', contact_phone: '981 123-456' })).toEqual({});
    expect(errors({ ...contact, contact_name: 'P', contact_phone: '981123456' })).toEqual({
      contact_name: M.contactNameMin,
    });
    expect(errors({ ...contact, contact_name: '12345', contact_phone: '981123456' })).toEqual({
      contact_name: M.contactNameFormat,
    });
    expect(errors({ ...contact, contact_name: 'Pedro <b>', contact_phone: '981123456' })).toEqual({
      contact_name: M.contactNameFormat,
    });
    expect(
      errors({ ...contact, contact_name: 'a'.repeat(151), contact_phone: '981123456' }),
    ).toEqual({ contact_name: M.contactNameMax });
  });

  it('el motivo y las notas, en caracteres', () => {
    expect(errors({ reason: 'ñ'.repeat(255) })).toEqual({});
    expect(errors({ reason: 'ñ'.repeat(256) })).toEqual({ reason: M.reasonMax });
    expect(errors({ notes: '🐶'.repeat(1000) })).toEqual({});
    expect(errors({ notes: 'x'.repeat(1001) })).toEqual({ notes: M.notesMax });
  });
});

describe('el cuerpo del turno', () => {
  it('el alta: los instantes con -03:00 y el paciente con su dueño', () => {
    expect(appointmentFormToPayload({ ...VALID, reason: '  Control ', notes: ' ' })).toEqual({
      schedule_id: 7,
      service_id: 3,
      patient_id: 9,
      client_id: 3,
      contact_name: null,
      contact_phone: null,
      starts_at: '2026-11-17T09:30:00.000-03:00',
      ends_at: '2026-11-17T10:00:00.000-03:00',
      source: 'reception',
      reason: 'Control',
      notes: null,
    });
  });

  it('el contacto: sin paciente ni cliente, el teléfono sin espacios ni guiones', () => {
    const payload = appointmentFormToPayload({
      ...VALID,
      who: 'contact',
      contact_name: ' Pedro Gómez ',
      contact_phone: '981 123-456',
      service_id: '',
    });

    expect(payload).toMatchObject({
      patient_id: null,
      client_id: null,
      contact_name: 'Pedro Gómez',
      contact_phone: '981123456',
      service_id: null,
    });
  });

  it('el cliente sin paciente', () => {
    expect(
      appointmentFormToPayload({
        ...VALID,
        who: 'client',
        client: { id: 3, display_name: 'Laura Martínez' },
      }),
    ).toMatchObject({ patient_id: null, client_id: 3, contact_name: null, contact_phone: null });
  });

  it('overbook va solo si se pide', () => {
    expect(appointmentFormToPayload(VALID)).not.toHaveProperty('overbook');
    expect(appointmentFormToPayload(VALID, { overbook: true }).overbook).toBe(true);
  });

  it('la edición manda solo lo que cambió: sin tocar nada, un cuerpo vacío; el motivo, solo el motivo', () => {
    const original = detail({ reason: 'Vacuna anual' });
    const values = appointmentToForm(original);

    expect(appointmentFormToPayload(values, { original })).toEqual({});
    expect(appointmentFormToPayload({ ...values, reason: '  Control  ' }, { original })).toEqual({
      reason: 'Control',
    });
    expect(appointmentFormToPayload({ ...values, reason: '' }, { original })).toEqual({
      reason: null,
    });
    expect(
      appointmentFormToPayload({ ...values, service_id: '', source: 'phone' }, { original }),
    ).toEqual({ service_id: null, source: 'phone' });
  });

  it('la edición: "quién" va entero si cambió (de contacto a paciente, el contacto en null)', () => {
    const original = detail({
      patient: null,
      client: null,
      contact_name: 'Rosa Benítez',
      contact_phone: '981100001',
    });
    const values = appointmentToForm(original);

    expect(
      appointmentFormToPayload({ ...values, contact_phone: '981 100 001' }, { original }),
    ).toEqual({});
    expect(
      appointmentFormToPayload({ ...values, who: 'patient', patient: LUNA }, { original }),
    ).toEqual({
      patient_id: 9,
      client_id: 3,
      contact_name: null,
      contact_phone: null,
    });
  });

  it('reprogramar: solo lo que cambió', () => {
    const original = detail();
    const values = appointmentToForm(original);

    expect(appointmentFormToPayload({ ...values, time: '11:00' }, { original })).toEqual({
      starts_at: '2026-11-17T11:00:00.000-03:00',
      ends_at: '2026-11-17T11:30:00.000-03:00',
    });
    const longer = appointmentFormToPayload({ ...values, duration_minutes: '45' }, { original });

    expect(longer).not.toHaveProperty('starts_at');
    expect(longer.ends_at).toBe('2026-11-17T10:15:00.000-03:00');
    expect(
      appointmentFormToPayload({ ...values, schedule_id: '8' }, { original }).schedule_id,
    ).toBe(8);
  });

  it('el turno guardado como formulario: la hora de pared y la duración', () => {
    expect(appointmentToForm(detail({ notes: 'Trae análisis' }))).toEqual({
      ...EMPTY_APPOINTMENT_FORM,
      who: 'patient',
      patient: LUNA,
      client: null,
      schedule_id: '7',
      service_id: '3',
      duration_minutes: '30',
      date: '2026-11-17',
      time: '09:30',
      notes: 'Trae análisis',
    });
    expect(
      appointmentToForm(
        detail({ patient: null, client: null, contact_name: 'Pedro', contact_phone: '981123456' }),
      ),
    ).toMatchObject({ who: 'contact', contact_name: 'Pedro', contact_phone: '981123456' });
    expect(appointmentToForm(detail({ patient: null }))).toMatchObject({
      who: 'client',
      client: { id: 3, display_name: 'Laura Martínez' },
    });
  });
});

function validation(errors: Record<string, string[]>) {
  return new ApiError({
    status: 422,
    errorCode: 'VALIDATION_ERROR',
    message: 'Datos inválidos.',
    errors,
  });
}

describe('los 422 de la API en el formulario del turno', () => {
  it('cada clave a su campo: starts_at a la hora, ends_at a la duración, schedule a la agenda', () => {
    expect(
      mapAppointmentErrors(
        validation({
          starts_at: ['No podés dar un turno en un día que ya pasó.'],
          ends_at: ['El turno no puede durar más de 720 minutos.'],
          schedule_id: ['El veterinario de la agenda está inactivo.'],
          service_id: ['El servicio no existe o está inactivo.'],
        }),
        'patient',
      ),
    ).toEqual({
      fields: [
        ['time', 'No podés dar un turno en un día que ya pasó.'],
        ['duration_minutes', 'El turno no puede durar más de 720 minutos.'],
        ['schedule_id', 'El veterinario de la agenda está inactivo.'],
        ['service_id', 'El servicio no existe o está inactivo.'],
      ],
      others: [],
    });
    expect(
      mapAppointmentErrors(
        validation({ schedule: ['La agenda no existe o está inactiva.'] }),
        'patient',
      )?.fields,
    ).toEqual([['schedule_id', 'La agenda no existe o está inactiva.']]);
  });

  it('el paciente fallecido o de otro dueño, al paciente; client_id con un paciente elegido, también', () => {
    expect(
      mapAppointmentErrors(
        validation({
          patient_id: ['El paciente falleció: no se le pueden dar turnos.'],
          client_id: ['El cliente no existe.'],
        }),
        'patient',
      )?.fields,
    ).toEqual([
      ['patient', 'El paciente falleció: no se le pueden dar turnos. El cliente no existe.'],
    ]);
  });

  it('un campo que no se ve con ese "quién", y las claves desconocidas, a la alerta', () => {
    expect(
      mapAppointmentErrors(
        validation({
          patient_id: ['Ingresá el paciente, el cliente o un nombre y un teléfono de contacto.'],
          branch_id: ['La sucursal sale de la agenda: no se envía.'],
          contact_phone: ['El teléfono de contacto debe tener un formato válido.'],
        }),
        'contact',
      ),
    ).toEqual({
      fields: [['contact_phone', 'El teléfono de contacto debe tener un formato válido.']],
      others: [
        'Ingresá el paciente, el cliente o un nombre y un teléfono de contacto.',
        'La sucursal sale de la agenda: no se envía.',
      ],
    });
  });

  it('no es de validación: lo trata quien llama', () => {
    expect(
      mapAppointmentErrors(
        new ApiError({
          status: 409,
          errorCode: 'APPOINTMENT_OVERLAP',
          message: 'El horario ya está ocupado.',
        }),
        'patient',
      ),
    ).toBeNull();
  });
});

describe('la serie en el formulario (3.6)', () => {
  const SERIES: Partial<AppointmentFormValues> = {
    repeat: true,
    every_weeks: '1',
    series_end: 'count',
    series_count: '8',
  };

  it('sin "Repetir", los campos de la serie no se validan', () => {
    expect(errors({ repeat: false, series_count: '99' })).toEqual({});
  });

  it('con "Repetir", las reglas de la API en su campo', () => {
    expect(errors({ ...SERIES })).toEqual({});
    expect(errors({ ...SERIES, series_count: '53' })).toEqual({
      series_count: SERIES_MESSAGES.tooMany,
    });
    expect(errors({ ...SERIES, every_weeks: '13' })).toEqual({
      every_weeks: SERIES_MESSAGES.everyWeeks,
    });
    expect(
      errors({ ...SERIES, series_end: 'until', series_until: '2027-11-18', every_weeks: '2' }),
    ).toEqual({
      series_until: SERIES_MESSAGES.untilTooFar,
    });
  });

  it('la edición no valida la serie (la API la rechaza en el PATCH)', () => {
    const original = appointmentToForm(detail());
    const result = appointmentFormSchema('2026-11-17', original).safeParse({
      ...original,
      ...SERIES,
      series_count: '99',
    });

    expect(result.success).toBe(true);
  });

  it('el cuerpo del alta de la serie: el del turno y recurrence con uno de count o until', () => {
    const body = appointmentSeriesPayload({ ...VALID, ...SERIES });

    expect(body.starts_at).toBe('2026-11-17T09:30:00.000-03:00');
    expect(body.recurrence).toEqual({ every_weeks: 1, count: 8, until: null });
    expect(body).not.toHaveProperty('overbook');
    expect(
      appointmentSeriesPayload(
        { ...VALID, ...SERIES, every_weeks: '2', series_end: 'until', series_until: '2027-01-12' },
        { overbook: true },
      ),
    ).toMatchObject({
      overbook: true,
      recurrence: { every_weeks: 2, count: null, until: '2027-01-12' },
    });
  });

  it('el turno único no lleva recurrence', () => {
    expect(appointmentFormToPayload(VALID)).not.toHaveProperty('recurrence');
  });

  it('la serie leída solo con "Repetir" y válida', () => {
    expect(seriesInputOf({ ...VALID, ...SERIES })).toEqual({
      firstDay: '2026-11-17',
      everyWeeks: 1,
      end: 'count',
      count: 8,
      until: null,
    });
    expect(seriesInputOf({ ...VALID, ...SERIES, series_count: '1' })).toBeNull();
    expect(seriesInputOf({ ...VALID, repeat: false })).toBeNull();
  });

  it('los 422 de recurrence a su campo; recurrence suelto, a la alerta', () => {
    const error = new ApiError({
      status: 422,
      errorCode: 'VALIDATION_ERROR',
      message: 'Los datos no son válidos.',
      errors: {
        'recurrence.count': [SERIES_MESSAGES.countTooFar],
        'recurrence.until': [SERIES_MESSAGES.untilBefore],
        'recurrence.every_weeks': [SERIES_MESSAGES.everyWeeks],
        recurrence: ['La serie solo admite every_weeks, count y until.'],
      },
    });

    expect(mapAppointmentErrors(error, 'patient')).toEqual({
      fields: [
        ['series_count', SERIES_MESSAGES.countTooFar],
        ['series_until', SERIES_MESSAGES.untilBefore],
        ['every_weeks', SERIES_MESSAGES.everyWeeks],
      ],
      others: ['La serie solo admite every_weeks, count y until.'],
    });
  });
});

describe('deuda 28: los invisibles de los bordes se recortan como en la API (trimLikeApi)', () => {
  it('el nombre de contacto "\\u200BP" tiene un solo carácter para la API', () => {
    expect(
      errors({
        who: 'contact',
        patient: null,
        contact_name: '\u200BP',
        contact_phone: '981123456',
      }),
    ).toEqual({ contact_name: M.contactNameMin });
  });
});
