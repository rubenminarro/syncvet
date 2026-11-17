import { describe, expect, it } from 'vitest';
import {
  isAuditDate,
  isAuditRangeReversed,
  joinAuditDate,
  splitAuditDate,
  toAuditApiDate,
} from './dates';

// Los tests corren con la PC en UTC (vite.config.ts): una conversión con la zona de la PC daría otra hora y fallaría.

describe('toAuditApiDate: una fecha sola es el día completo de Asunción, en UTC', () => {
  it('"desde" a las 00:00:00 de Asunción (03:00 UTC)', () => {
    expect(toAuditApiDate('2026-11-09', 'from')).toBe('2026-11-09T03:00:00.000Z');
  });

  it('"hasta" a las 23:59:59 de Asunción (02:59:59 UTC del día siguiente)', () => {
    expect(toAuditApiDate('2026-11-09', 'to')).toBe('2026-11-10T02:59:59.000Z');
  });

  it('el último día del año y el 29 de febrero', () => {
    expect(toAuditApiDate('2026-12-31', 'to')).toBe('2027-01-01T02:59:59.000Z');
    expect(toAuditApiDate('2028-02-29', 'from')).toBe('2028-02-29T03:00:00.000Z');
  });
});

describe('toAuditApiDate: con hora', () => {
  it('"desde" en ese minuto, con los segundos en 0', () => {
    expect(toAuditApiDate('2026-11-09T13:00', 'from')).toBe('2026-11-09T16:00:00.000Z');
  });

  it('"hasta" con los segundos en 59: "hasta las 18:00" incluye todo ese minuto (H7)', () => {
    expect(toAuditApiDate('2026-11-09T18:00', 'to')).toBe('2026-11-09T21:00:59.000Z');
  });

  it('"hasta" a las 23:59 de Asunción cae en el día siguiente en UTC', () => {
    expect(toAuditApiDate('2026-11-09T23:59', 'to')).toBe('2026-11-10T02:59:59.000Z');
  });

  it('"desde" a las 00:00 es lo mismo que la fecha sola', () => {
    expect(toAuditApiDate('2026-11-09T00:00', 'from')).toBe(toAuditApiDate('2026-11-09', 'from'));
  });
});

describe('toAuditApiDate: el cambio de horario (Paraguay tuvo horario de verano hasta 2024)', () => {
  it('en invierno de 2024, Asunción era UTC-4', () => {
    expect(toAuditApiDate('2024-07-15', 'from')).toBe('2024-07-15T04:00:00.000Z');
    expect(toAuditApiDate('2024-07-15', 'to')).toBe('2024-07-16T03:59:59.000Z');
    expect(toAuditApiDate('2024-07-15T13:00', 'from')).toBe('2024-07-15T17:00:00.000Z');
  });

  it('el día del fin del verano (23/03/2024, de 25 horas): de las 00:00 en UTC-3 a las 23:59:59 en UTC-4', () => {
    expect(toAuditApiDate('2024-03-23', 'from')).toBe('2024-03-23T03:00:00.000Z');
    expect(toAuditApiDate('2024-03-23', 'to')).toBe('2024-03-24T03:59:59.000Z');
  });

  it('el día del comienzo del verano (01/10/2023, de 23 horas): el día empieza a la 01:00 (las 00:00 no existieron)', () => {
    expect(toAuditApiDate('2023-10-01', 'from')).toBe('2023-10-01T04:00:00.000Z');
    expect(toAuditApiDate('2023-10-01', 'to')).toBe('2023-10-02T02:59:59.000Z');
  });
});

describe('toAuditApiDate y isAuditDate: lo que no es un filtro', () => {
  it.each([
    'hoy',
    '2026-11-9',
    '2026-02-30',
    '2026-13-01',
    '2026-11-09T24:00',
    '2026-11-09T13:60',
    '2026-11-09T13:00:00',
    '2026-11-09T13:00Z',
    '2026-11-09 13:00',
    '',
  ])('%s', (value) => {
    expect(isAuditDate(value)).toBe(false);
    expect(toAuditApiDate(value, 'from')).toBeNull();
    expect(toAuditApiDate(value, 'to')).toBeNull();
  });
});

describe('isAuditRangeReversed: la hora de pared comparada como texto', () => {
  it.each([
    ['2026-11-09', '2026-11-09', false],
    ['2026-11-10', '2026-11-09', true],
    ['2026-11-09T18:00', '2026-11-09T18:00', false],
    ['2026-11-09T18:00', '2026-11-09T17:59', true],
    ['2026-11-09T23:59', '2026-11-09', false],
    ['2026-11-09', '2026-11-08T23:59', true],
    [undefined, '2026-11-09', false],
    ['2026-11-09', undefined, false],
    ['basura', '2020-01-01', false],
  ] as const)('desde %s, hasta %s → %s', (from, to, reversed) => {
    expect(isAuditRangeReversed(from, to)).toBe(reversed);
  });
});

describe('joinAuditDate y splitAuditDate: los dos campos y la URL', () => {
  it('sin fecha, nada; sin hora, la fecha sola; con hora, fechaThh:mm', () => {
    expect(joinAuditDate('', '13:00')).toBe('');
    expect(joinAuditDate('2026-11-09', '')).toBe('2026-11-09');
    expect(joinAuditDate('2026-11-09', '13:00')).toBe('2026-11-09T13:00');
  });

  it('de la URL a los campos; un valor inválido, vacío', () => {
    expect(splitAuditDate('2026-11-09T13:00')).toEqual({ date: '2026-11-09', time: '13:00' });
    expect(splitAuditDate('2026-11-09')).toEqual({ date: '2026-11-09', time: '' });
    expect(splitAuditDate('mañana')).toEqual({ date: '', time: '' });
    expect(splitAuditDate(undefined)).toEqual({ date: '', time: '' });
  });
});
