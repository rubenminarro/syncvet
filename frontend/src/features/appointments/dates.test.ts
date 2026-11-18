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
  weekStartOf,
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
