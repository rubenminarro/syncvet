import { describe, expect, it } from 'vitest';
import {
  addDaysToDay,
  dayBounds,
  dayLengthHours,
  dayName,
  dayTitle,
  isAgendaDay,
  minutesToClock,
  wallDay,
  wallMinutes,
  weekdayOf,
  daysBetween,
  shortDayName,
  weekBounds,
  weekDaysOf,
  weekEndOf,
  weekLengthHours,
  weekStartOf,
  weekTitle,
} from './dates';

// Los tests corren con la PC en UTC: una cuenta con la zona de la PC daría otro día u otra hora.

describe('el día de la agenda', () => {
  it('acepta solo fechas yyyy-MM-dd que existen', () => {
    expect(isAgendaDay('2026-11-17')).toBe(true);
    expect(isAgendaDay('2024-02-29')).toBe(true);
    expect(isAgendaDay('2026-02-29')).toBe(false);
    expect(isAgendaDay('2026-13-01')).toBe(false);
    expect(isAgendaDay('2026-11-7')).toBe(false);
    expect(isAgendaDay('17/11/2026')).toBe(false);
    expect(isAgendaDay('2026-11-17T09:00')).toBe(false);
  });

  it('suma y resta días en el calendario de Asunción, también al cambiar de mes y de año', () => {
    expect(addDaysToDay('2026-11-17', 1)).toBe('2026-11-18');
    expect(addDaysToDay('2026-11-30', 1)).toBe('2026-12-01');
    expect(addDaysToDay('2026-12-31', 1)).toBe('2027-01-01');
    expect(addDaysToDay('2026-03-01', -1)).toBe('2026-02-28');
  });

  it('suma días sobre los cambios de horario sin perder ni repetir un día', () => {
    expect(addDaysToDay('2023-09-30', 1)).toBe('2023-10-01');
    expect(addDaysToDay('2023-10-01', 1)).toBe('2023-10-02');
    expect(addDaysToDay('2024-03-23', 1)).toBe('2024-03-24');
    expect(addDaysToDay('2024-03-24', -1)).toBe('2024-03-23');
  });

  it('el día de la semana con el número de la API (1 = lunes, 7 = domingo) y la semana desde el lunes', () => {
    expect(weekdayOf('2026-11-16')).toBe(1);
    expect(weekdayOf('2026-11-17')).toBe(2);
    expect(weekdayOf('2026-11-22')).toBe(7);
    expect(weekStartOf('2026-11-17')).toBe('2026-11-16');
    expect(weekStartOf('2026-11-22')).toBe('2026-11-16');
    expect(weekStartOf('2026-11-16')).toBe('2026-11-16');
    expect(weekStartOf('2026-12-02')).toBe('2026-11-30');
  });

  it('un día común dura 24 horas, de las 00:00 a las 00:00 de Asunción (03:00 en UTC)', () => {
    expect(dayLengthHours('2026-11-17')).toBe(24);
    expect(new Date(dayBounds('2026-11-17').start).toISOString()).toBe('2026-11-17T03:00:00.000Z');
  });

  it('el día del comienzo del horario de verano (01/10/2023) dura 23 horas: empieza a la 01:00', () => {
    expect(dayLengthHours('2023-10-01')).toBe(23);
    expect(new Date(dayBounds('2023-10-01').start).toISOString()).toBe('2023-10-01T04:00:00.000Z');
  });

  it('el día del fin del horario de verano (23/03/2024) dura 25 horas', () => {
    expect(dayLengthHours('2024-03-23')).toBe(25);
    expect(dayLengthHours('2024-03-24')).toBe(24);
  });

  it('el nombre del día, en castellano', () => {
    expect(dayName('2026-11-17')).toBe('Martes 17 de noviembre');
    expect(dayTitle('2026-11-17')).toBe('Martes 17 de noviembre de 2026');
    expect(dayName('2026-11-22')).toBe('Domingo 22 de noviembre');
  });
});

describe('la hora de pared de un instante', () => {
  it('sale de Asunción y no de la PC (en UTC): 12:30Z es 09:30', () => {
    expect(wallMinutes('2026-11-17T12:30:00.000000Z')).toBe(9 * 60 + 30);
    expect(minutesToClock(wallMinutes('2026-11-17T12:30:00.000000Z'))).toBe('09:30');
  });

  it('el día de Asunción de un instante: 02:00Z del 18 es todavía el 17', () => {
    expect(wallDay('2026-11-18T02:00:00.000000Z')).toBe('2026-11-17');
    expect(wallDay('2026-11-18T03:00:00.000000Z')).toBe('2026-11-18');
  });

  it('en el día de 25 horas, la hora repetida (23:30 en UTC-3 y en UTC-4) cae en la misma fila', () => {
    expect(wallMinutes('2024-03-24T02:30:00.000Z')).toBe(23 * 60 + 30);
    expect(wallMinutes('2024-03-24T03:30:00.000Z')).toBe(23 * 60 + 30);
    expect(wallDay('2024-03-24T03:30:00.000Z')).toBe('2024-03-23');
  });

  it('en el día de 23 horas, la 01:00 es el comienzo del día', () => {
    expect(wallMinutes('2023-10-01T04:00:00.000Z')).toBe(60);
  });
});

describe('la semana de la agenda (3.3)', () => {
  it('va de lunes a domingo desde cualquier día, también al cambiar de mes y de año', () => {
    expect(weekDaysOf('2026-11-18')).toEqual([
      '2026-11-16',
      '2026-11-17',
      '2026-11-18',
      '2026-11-19',
      '2026-11-20',
      '2026-11-21',
      '2026-11-22',
    ]);
    expect(weekDaysOf('2026-11-22')[0]).toBe('2026-11-16');
    expect(weekDaysOf('2026-11-23')[0]).toBe('2026-11-23');
    expect(weekEndOf('2026-12-30')).toBe('2027-01-03');
    expect(weekStartOf('2027-01-03')).toBe('2026-12-28');
  });

  it('empieza el lunes a las 00:00 de Asunción (03:00 en UTC), con la PC en UTC', () => {
    const { start, end } = weekBounds('2026-11-18');

    expect(new Date(start).toISOString()).toBe('2026-11-16T03:00:00.000Z');
    expect(new Date(end).toISOString()).toBe('2026-11-23T03:00:00.000Z');
    expect(weekLengthHours('2026-11-18')).toBe(168);
  });

  it('el domingo 22:30 de Asunción (lunes 01:30 en UTC) es todavía de esa semana', () => {
    const sunday = new Date('2026-11-23T01:30:00.000Z').getTime();
    const { start, end } = weekBounds('2026-11-22');

    expect(sunday >= start && sunday < end).toBe(true);
  });

  it('la semana del 01/10/2023 (el día de 23 horas, un domingo) dura 167 horas, de lunes a domingo', () => {
    expect(weekDaysOf('2023-10-01')).toEqual([
      '2023-09-25',
      '2023-09-26',
      '2023-09-27',
      '2023-09-28',
      '2023-09-29',
      '2023-09-30',
      '2023-10-01',
    ]);
    expect(weekLengthHours('2023-09-27')).toBe(167);
    expect(new Date(weekBounds('2023-10-01').end).toISOString()).toBe('2023-10-02T03:00:00.000Z');
  });

  it('la semana del 23/03/2024 (el día de 25 horas, un sábado) dura 169 horas y termina el domingo 24', () => {
    expect(weekDaysOf('2024-03-23')).toEqual([
      '2024-03-18',
      '2024-03-19',
      '2024-03-20',
      '2024-03-21',
      '2024-03-22',
      '2024-03-23',
      '2024-03-24',
    ]);
    expect(weekLengthHours('2024-03-24')).toBe(169);
    expect(new Date(weekBounds('2024-03-18').start).toISOString()).toBe('2024-03-18T03:00:00.000Z');
    expect(new Date(weekBounds('2024-03-18').end).toISOString()).toBe('2024-03-25T04:00:00.000Z');
  });

  it('los días entre dos fechas, sin horas (también sobre los cambios de horario)', () => {
    expect(daysBetween('2026-11-01', '2026-11-01')).toBe(0);
    expect(daysBetween('2026-11-01', '2026-12-01')).toBe(30);
    expect(daysBetween('2026-11-01', '2026-12-02')).toBe(31);
    expect(daysBetween('2026-11-02', '2026-11-01')).toBe(-1);
    expect(daysBetween('2024-03-20', '2024-03-27')).toBe(7);
    expect(daysBetween('2023-09-28', '2023-10-04')).toBe(6);
  });

  it('el título y los encabezados de la semana', () => {
    expect(weekTitle('2026-11-18')).toBe('Semana del 16 al 22 de noviembre de 2026');
    expect(weekTitle('2026-12-02')).toBe('Semana del 30 de noviembre al 6 de diciembre de 2026');
    expect(weekTitle('2026-12-30')).toBe(
      'Semana del 28 de diciembre de 2026 al 3 de enero de 2027',
    );
    expect(shortDayName('2026-11-16')).toBe('Lun 16');
    expect(shortDayName('2026-11-18')).toBe('Mié 18');
  });
});
