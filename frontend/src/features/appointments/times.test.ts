import { describe, expect, it } from 'vitest';
import {
  appointmentTimes,
  crossesMidnight,
  instantToWall,
  minutesBetween,
  parseInicio,
  sameInstant,
  wallToInstant,
} from './times';

// Corren en UTC (la PC del test no es la de Asunción): toda cuenta va por la zona de la clínica.

describe('la conversión de horarios del turno', () => {
  it('manda la hora de pared de Asunción con su desplazamiento (UTC-3 desde el 15/10/2024)', () => {
    expect(appointmentTimes('2026-11-17', '09:30', 30)).toEqual({
      starts_at: '2026-11-17T09:30:00.000-03:00',
      ends_at: '2026-11-17T10:00:00.000-03:00',
      endDay: '2026-11-17',
      endClock: '10:00',
    });
    expect(
      new Date(appointmentTimes('2026-11-17', '09:30', 30)?.starts_at ?? '').toISOString(),
    ).toBe('2026-11-17T12:30:00.000Z');
  });

  it('usa el desplazamiento de cada fecha, nunca un -03:00 fijo (el horario de verano de antes de 2024)', () => {
    // Invierno de 2023: UTC-4.
    expect(appointmentTimes('2023-07-01', '09:00', 30)?.starts_at).toBe(
      '2023-07-01T09:00:00.000-04:00',
    );
    // El 30/09/2023 todavía UTC-4; el 01/10/2023 (23 horas: de las 00:00 se pasó a la 01:00), UTC-3.
    expect(appointmentTimes('2023-09-30', '09:00', 30)?.starts_at).toBe(
      '2023-09-30T09:00:00.000-04:00',
    );
    expect(appointmentTimes('2023-10-01', '09:00', 30)?.starts_at).toBe(
      '2023-10-01T09:00:00.000-03:00',
    );
    // El 23/03/2024 (25 horas: a las 24:00 se volvió a las 23:00), UTC-3; el 24, UTC-4.
    expect(appointmentTimes('2024-03-23', '09:00', 30)?.starts_at).toBe(
      '2024-03-23T09:00:00.000-03:00',
    );
    expect(appointmentTimes('2024-03-24', '09:00', 30)?.starts_at).toBe(
      '2024-03-24T09:00:00.000-04:00',
    );
  });

  it('el fin es el inicio más la duración en instantes: en el día de 25 horas, el turno que pasa por la hora repetida termina el mismo día', () => {
    // 22:30 (UTC-3) + 120 minutos = 01:30 UTC del 24 + 2 h = 03:30 UTC = 23:30 (UTC-4) del 23: el mismo día, como la API.
    const times = appointmentTimes('2024-03-23', '22:30', 120);

    expect(times?.endDay).toBe('2024-03-23');
    expect(times?.endClock).toBe('23:30');
    expect(times?.ends_at).toBe('2024-03-23T23:30:00.000-04:00');
    expect(crossesMidnight('2024-03-23', '22:30', 120)).toBe(false);
    // Una hora más y sí pasa al 24.
    expect(crossesMidnight('2024-03-23', '22:30', 180)).toBe(true);
  });

  it('en el día de 23 horas, la medianoche llega una hora antes por la pared', () => {
    // 2023-09-30 23:00 (UTC-4) + 60 minutos = 2023-10-01 01:00 (UTC-3): ya es el día siguiente.
    expect(crossesMidnight('2023-09-30', '23:00', 60)).toBe(true);
    expect(appointmentTimes('2023-09-30', '23:00', 55)?.endClock).toBe('23:55');
    // El 01/10/2023 dura 23 horas: de la 01:00 a las 23:55 entran 1375 minutos (no 1435).
    expect(crossesMidnight('2023-10-01', '01:00', 1375)).toBe(false);
  });

  it('un turno que termina a las 00:00 del día siguiente cruza la medianoche (H10)', () => {
    expect(crossesMidnight('2026-11-17', '23:30', 30)).toBe(true);
    expect(crossesMidnight('2026-11-17', '23:30', 25)).toBe(false);
    expect(appointmentTimes('2026-11-17', '23:55', 5)?.endDay).toBe('2026-11-18');
  });

  it('de la API al formulario: el día y la hora de pared de Asunción, y la duración', () => {
    expect(instantToWall('2026-11-17T12:30:00.000000Z')).toEqual({
      day: '2026-11-17',
      clock: '09:30',
    });
    // A las 01:30 UTC del 18 todavía es el 17 en Asunción.
    expect(instantToWall('2026-11-18T01:30:00.000000Z')).toEqual({
      day: '2026-11-17',
      clock: '22:30',
    });
    // La hora repetida del 23/03/2024: las dos 23:00 son del 23.
    expect(instantToWall('2024-03-24T02:00:00Z')).toEqual({ day: '2024-03-23', clock: '23:00' });
    expect(instantToWall('2024-03-24T03:00:00Z')).toEqual({ day: '2024-03-23', clock: '23:00' });
    expect(minutesBetween('2024-03-24T01:30:00Z', '2024-03-24T03:30:00Z')).toBe(120);
  });

  it('la ida y la vuelta conservan la hora de pared en las fechas de los dos horarios', () => {
    for (const [day, clock] of [
      ['2023-07-01', '08:15'],
      ['2023-10-01', '09:00'],
      ['2024-03-23', '18:40'],
      ['2024-03-24', '07:05'],
      ['2026-11-17', '23:55'],
    ] as const) {
      const times = appointmentTimes(day, clock, 30);

      expect(instantToWall(times?.starts_at ?? '')).toEqual({ day, clock });
    }
  });

  it('rechaza lo mal formado en lugar de inventar una hora', () => {
    expect(wallToInstant('2026-02-30', '09:00')).toBeNull();
    expect(wallToInstant('2026-11-17', '9:00')).toBeNull();
    expect(wallToInstant('2026-11-17', '24:00')).toBeNull();
    expect(appointmentTimes('2026-11-17', '09:00', 0)).toBeNull();
    expect(appointmentTimes('2026-11-17', '09:00', 2.5)).toBeNull();
  });

  it('compara instantes con zona distinta', () => {
    expect(sameInstant('2026-11-17T12:30:00.000000Z', '2026-11-17T09:30:00.000-03:00')).toBe(true);
    expect(sameInstant('2026-11-17T12:30:00.000000Z', '2026-11-17T09:35:00.000-03:00')).toBe(false);
  });

  it('lee el parámetro inicio de /turnos/nuevo', () => {
    expect(parseInicio('2026-11-17T09:30')).toEqual({ day: '2026-11-17', clock: '09:30' });
    expect(parseInicio('2026-11-17')).toBeNull();
    expect(parseInicio('2026-11-17T9:30')).toBeNull();
    expect(parseInicio('2026-13-01T09:30')).toBeNull();
    expect(parseInicio('2026-11-17T09:30T')).toBeNull();
  });
});
