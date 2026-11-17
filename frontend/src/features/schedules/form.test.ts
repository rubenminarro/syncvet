import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { ROOM_ONE } from '../../test/schedules';
import {
  copyMondayToWeekdays,
  EMPTY_SCHEDULE_FORM,
  formatDayHours,
  hoursErrors,
  SCHEDULE_MESSAGES,
  scheduleFormErrors,
  scheduleFormToPayload,
  scheduleToForm,
  type HourFormValue,
  type ScheduleFormValues,
} from './form';

const valid: ScheduleFormValues = {
  ...EMPTY_SCHEDULE_FORM,
  branch_id: '10',
  name: 'Consultorio 1',
  veterinarian_id: '4',
};

const hour = (weekday: number, starts_at: string, ends_at: string): HourFormValue => ({
  weekday,
  starts_at,
  ends_at,
});

describe('las reglas de la agenda (ScheduleMessages)', () => {
  it('el formulario mínimo pasa', () => {
    expect(scheduleFormErrors(valid)).toEqual({});
  });

  it('sin veterinario ni servicio: el mensaje de la API en el veterinario; con uno de los dos, pasa', () => {
    expect(scheduleFormErrors({ ...valid, veterinarian_id: '' })).toEqual({
      veterinarian_id: 'Elegí un veterinario, un servicio o los dos.',
    });
    expect(scheduleFormErrors({ ...valid, veterinarian_id: '', service_id: '3' })).toEqual({});
  });

  it('la sucursal es obligatoria', () => {
    expect(scheduleFormErrors({ ...valid, branch_id: '' }).branch_id).toBe(
      'La sucursal es obligatoria.',
    );
  });

  it.each([
    ['', SCHEDULE_MESSAGES.name.required],
    ['A', SCHEDULE_MESSAGES.name.min],
    ['Ab', null],
    ['a'.repeat(100), null],
    ['a'.repeat(101), SCHEDULE_MESSAGES.name.max],
    ['1234', SCHEDULE_MESSAGES.name.regex],
    ['Consultorio 1 (planta baja)', null],
    ["Box d'Ávila - 2.", null],
    ['Consultorio/1', SCHEDULE_MESSAGES.name.regex],
    ['Ñandutí 🐾', SCHEDULE_MESSAGES.name.regex],
  ])('el nombre %j', (name, expected) => {
    expect(scheduleFormErrors({ ...valid, name }).name ?? null).toBe(expected);
  });

  it('el nombre cuenta caracteres, no unidades de UTF-16 (100 letras fuera del plano básico pasan)', () => {
    expect(scheduleFormErrors({ ...valid, name: '𝒜'.repeat(100) }).name).toBeUndefined();
    expect(scheduleFormErrors({ ...valid, name: '𝒜'.repeat(101) }).name).toBe(
      SCHEDULE_MESSAGES.name.max,
    );
  });

  it.each([
    ['', SCHEDULE_MESSAGES.slot_minutes.required],
    ['30.5', SCHEDULE_MESSAGES.slot_minutes.integer],
    ['0', SCHEDULE_MESSAGES.slot_minutes.between],
    ['5', null],
    ['240', null],
    ['245', SCHEDULE_MESSAGES.slot_minutes.between],
    ['32', SCHEDULE_MESSAGES.slot_minutes.multiple_of],
  ])('la duración del turno %j', (slot, expected) => {
    expect(scheduleFormErrors({ ...valid, slot_minutes: slot }).slot_minutes ?? null).toBe(
      expected,
    );
  });

  it('el color: solo con "Con color", en #RRGGBB', () => {
    expect(scheduleFormErrors({ ...valid, use_color: false, color: 'rojo' })).toEqual({});
    expect(scheduleFormErrors({ ...valid, use_color: true, color: '#abc' }).color).toBe(
      'El color debe tener el formato #RRGGBB.',
    );
    expect(scheduleFormErrors({ ...valid, use_color: true, color: '#a1b2c3' })).toEqual({});
  });
});

describe('el horario (ScheduleMessages::validateHours)', () => {
  it('dos tramos del lunes que se tocan pasan; superpuestos, el error del día en el tramo que choca', () => {
    expect(hoursErrors([hour(1, '08:00', '12:00'), hour(1, '12:00', '16:00')])).toEqual({});
    expect(hoursErrors([hour(1, '08:00', '12:00'), hour(1, '11:55', '16:00')])).toEqual({
      'hours.1.starts_at': 'El horario del lunes tiene tramos que se superponen.',
    });
  });

  it('la superposición se busca ordenando por el inicio (el tramo de más tarde, escrito primero)', () => {
    expect(hoursErrors([hour(2, '10:00', '12:00'), hour(2, '08:00', '10:05')])).toEqual({
      'hours.0.starts_at': 'El horario del martes tiene tramos que se superponen.',
    });
  });

  it('el mismo horario en dos días distintos pasa', () => {
    expect(hoursErrors([hour(1, '08:00', '12:00'), hour(2, '08:00', '12:00')])).toEqual({});
  });

  it('6 tramos en un día pasan; 7, el error del día en el séptimo', () => {
    const six = ['00:00', '01:00', '02:00', '03:00', '04:00', '05:00'].map((start, i) =>
      hour(6, start, `0${String(i)}:30`),
    );

    expect(hoursErrors(six)).toEqual({});
    expect(hoursErrors([...six, hour(6, '07:00', '08:00')])).toEqual({
      'hours.6.starts_at': 'El horario del sábado tiene más de 6 tramos.',
    });
  });

  it('23:55 es una hora válida (el último tramo del día); 24:00 y 23:57, no', () => {
    expect(hoursErrors([hour(7, '20:00', '23:55')])).toEqual({});
    expect(hoursErrors([hour(7, '20:00', '24:00')])).toEqual({
      'hours.0.ends_at': SCHEDULE_MESSAGES.hour.endsFormat,
    });
    expect(hoursErrors([hour(7, '20:00', '23:57')])).toEqual({
      'hours.0.ends_at': SCHEDULE_MESSAGES.hour.endsFormat,
    });
  });

  it('el fin antes del inicio, o igual: en el fin de su tramo (y no cuenta para su día)', () => {
    expect(hoursErrors([hour(1, '12:00', '08:00'), hour(1, '09:00', '10:00')])).toEqual({
      'hours.0.ends_at': 'La hora de fin del tramo debe ser posterior a la de inicio.',
    });
    expect(hoursErrors([hour(1, '12:00', '12:00')])).toEqual({
      'hours.0.ends_at': 'La hora de fin del tramo debe ser posterior a la de inicio.',
    });
  });

  it('una hora vacía o mal escrita: solo los errores de forma, sin buscar superposiciones', () => {
    expect(
      hoursErrors([hour(1, '', '12:00'), hour(1, '08:03', ''), hour(1, '08:00', '12:00')]),
    ).toEqual({
      'hours.0.starts_at': 'La hora de inicio del tramo es obligatoria.',
      'hours.1.starts_at': SCHEDULE_MESSAGES.hour.startsFormat,
      'hours.1.ends_at': 'La hora de fin del tramo es obligatoria.',
    });
  });

  it('los mensajes son los de ScheduleMessages del backend', () => {
    const php = readFileSync(
      new URL('../../../../backend/app/Http/Requests/ScheduleMessages.php', import.meta.url),
      'utf8',
    );

    for (const message of [
      SCHEDULE_MESSAGES.owner,
      SCHEDULE_MESSAGES.name.regex,
      SCHEDULE_MESSAGES.hour.endsBeforeStart,
      'debe ser una hora HH:MM de 00:00 a 23:55, en múltiplos de 5 minutos.',
      'tiene tramos que se superponen.',
    ]) {
      expect(php).toContain(message);
    }
  });
});

describe('"Copiar el lunes a los días hábiles"', () => {
  it('reemplaza los tramos del martes al viernes por los del lunes y no toca el sábado ni el domingo', () => {
    const copied = copyMondayToWeekdays([
      hour(3, '09:00', '10:00'),
      hour(1, '08:00', '12:00'),
      hour(1, '14:00', '18:00'),
      hour(6, '08:00', '12:00'),
      hour(7, '10:00', '11:00'),
    ]);

    expect(copied).toEqual([
      hour(1, '08:00', '12:00'),
      hour(1, '14:00', '18:00'),
      ...[2, 3, 4, 5].flatMap((weekday) => [
        hour(weekday, '08:00', '12:00'),
        hour(weekday, '14:00', '18:00'),
      ]),
      hour(6, '08:00', '12:00'),
      hour(7, '10:00', '11:00'),
    ]);
  });
});

describe('el cuerpo y la edición', () => {
  it('el alta: los ids como números, el nombre sin espacios de los extremos, sin color y el horario en el orden del formulario', () => {
    expect(
      scheduleFormToPayload({
        ...valid,
        name: '  Consultorio 1 ',
        service_id: '',
        slot_minutes: ' 20 ',
        hours: [hour(3, '14:00', '18:00'), hour(1, '08:00', '12:00')],
      }),
    ).toEqual({
      branch_id: 10,
      name: 'Consultorio 1',
      veterinarian_id: 4,
      service_id: null,
      slot_minutes: 20,
      color: null,
      hours: [
        { weekday: 3, starts_at: '14:00', ends_at: '18:00' },
        { weekday: 1, starts_at: '08:00', ends_at: '12:00' },
      ],
    });
  });

  it('el color en mayúsculas, como lo guarda la API', () => {
    expect(scheduleFormToPayload({ ...valid, use_color: true, color: '#a1b2c3' }).color).toBe(
      '#A1B2C3',
    );
  });

  it('sin tramos, `hours: []` (vacía el horario: nunca se omite la clave)', () => {
    const payload = scheduleFormToPayload({ ...scheduleToForm(ROOM_ONE), hours: [] });

    expect(payload).toHaveProperty('hours', []);
  });

  it('la agenda guardada como formulario, con su horario y su color', () => {
    expect(scheduleToForm(ROOM_ONE)).toEqual({
      branch_id: '10',
      name: 'Consultorio 1',
      veterinarian_id: '4',
      service_id: '3',
      slot_minutes: '30',
      use_color: true,
      color: '#206BC4',
      hours: [hour(1, '08:00', '12:00'), hour(1, '12:00', '16:00'), hour(3, '14:00', '18:00')],
    });
  });

  it('los tramos de un día para mostrar, ordenados; sin tramos, null', () => {
    expect(formatDayHours([hour(1, '14:00', '18:00'), hour(1, '08:00', '12:00')], 1)).toBe(
      '08:00 a 12:00, 14:00 a 18:00',
    );
    expect(formatDayHours(ROOM_ONE.hours, 2)).toBeNull();
  });
});
