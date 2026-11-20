import { describe, expect, it } from 'vitest';
import {
  BLOCK_MESSAGES,
  blockFormErrors,
  blockFormToPayload,
  emptyBlockForm,
  formatBlockPeriod,
  type BlockFormValues,
} from './blocks';

// Corre en UTC (src/test/setup y el proyecto node): una conversión con la zona de la PC daría otra hora.

function form(overrides: Partial<BlockFormValues> = {}): BlockFormValues {
  return {
    fromDate: '2026-11-20',
    fromTime: '09:00',
    toDate: '2026-11-20',
    toTime: '10:00',
    allDay: false,
    reason: '',
    ...overrides,
  };
}

const hours = (payload: { starts_at: string; ends_at: string }) =>
  (Date.parse(payload.ends_at) - Date.parse(payload.starts_at)) / 3_600_000;

describe('blockFormToPayload: la hora de pared de Asunción, con su desplazamiento', () => {
  it('con la PC en UTC, 09:00 a 10:00 van con -03:00 (12:00 y 13:00 en UTC)', () => {
    const payload = blockFormToPayload(form({ reason: '  Capacitación  ' }));

    expect(payload).toEqual({
      starts_at: '2026-11-20T09:00:00.000-03:00',
      ends_at: '2026-11-20T10:00:00.000-03:00',
      reason: 'Capacitación',
    });
    expect(new Date(payload.starts_at).toISOString()).toBe('2026-11-20T12:00:00.000Z');
  });

  it('el motivo vacío va en null', () => {
    expect(blockFormToPayload(form({ reason: '   ' })).reason).toBeNull();
  });

  it('puede cruzar la medianoche (un turno no; un bloqueo sí)', () => {
    const values = form({ fromTime: '22:00', toDate: '2026-11-21', toTime: '06:00' });

    expect(blockFormErrors(values)).toEqual({});
    expect(hours(blockFormToPayload(values))).toBe(8);
  });

  it('"Todo el día": de las 00:00 de la fecha desde a las 00:00 del día siguiente a la fecha hasta; las horas no cuentan', () => {
    const values = form({ allDay: true, fromTime: '', toTime: '', toDate: '2026-11-22' });

    expect(blockFormErrors(values)).toEqual({});
    expect(blockFormToPayload(values)).toMatchObject({
      starts_at: '2026-11-20T00:00:00.000-03:00',
      ends_at: '2026-11-23T00:00:00.000-03:00',
    });
  });

  it('"Todo el día" el 31/12 termina el 01/01 del año siguiente', () => {
    const values = form({ allDay: true, fromDate: '2026-12-31', toDate: '2026-12-31' });

    expect(blockFormToPayload(values).ends_at).toBe('2027-01-01T00:00:00.000-03:00');
  });

  it('un día de 23 horas (01/10/2023, empezó el horario de verano a las 00:00): "Todo el día" dura 23 horas', () => {
    const payload = blockFormToPayload(
      form({ allDay: true, fromDate: '2023-10-01', toDate: '2023-10-01' }),
    );

    // Las 00:00 no existieron ese día: empieza a la 01:00 (-03:00), el mismo instante que las 00:00 de -04:00.
    expect(new Date(payload.starts_at).toISOString()).toBe('2023-10-01T04:00:00.000Z');
    expect(new Date(payload.ends_at).toISOString()).toBe('2023-10-02T03:00:00.000Z');
    expect(hours(payload)).toBe(23);
  });

  it('un día de 25 horas (23/03/2024, terminó el horario de verano): "Todo el día" dura 25 horas', () => {
    const payload = blockFormToPayload(
      form({ allDay: true, fromDate: '2024-03-23', toDate: '2024-03-23' }),
    );

    expect(payload.starts_at).toBe('2024-03-23T00:00:00.000-03:00');
    expect(payload.ends_at).toBe('2024-03-24T00:00:00.000-04:00');
    expect(hours(payload)).toBe(25);
  });
});

describe('blockFormErrors: las reglas de StoreScheduleBlockRequest', () => {
  it('el formulario vacío pide el inicio y el fin, en la fecha y en la hora', () => {
    expect(blockFormErrors(form({ fromDate: '', fromTime: '', toDate: '', toTime: '' }))).toEqual({
      fromDate: BLOCK_MESSAGES.startsRequired,
      fromTime: BLOCK_MESSAGES.startsRequired,
      toDate: BLOCK_MESSAGES.endsRequired,
      toTime: BLOCK_MESSAGES.endsRequired,
    });
    expect(blockFormErrors(emptyBlockForm('2026-11-20'))).toEqual({
      fromTime: BLOCK_MESSAGES.startsRequired,
      toTime: BLOCK_MESSAGES.endsRequired,
    });
  });

  it('una fecha que no existe o una hora mal formada', () => {
    expect(blockFormErrors(form({ fromDate: '2026-02-30', toTime: '25:00' }))).toEqual({
      fromDate: BLOCK_MESSAGES.invalidDate,
      toTime: BLOCK_MESSAGES.invalidTime,
    });
  });

  it.each([
    ['09:03', '10:00', { fromTime: BLOCK_MESSAGES.offGrid }],
    ['09:00', '10:07', { toTime: BLOCK_MESSAGES.offGrid }],
    ['09:05', '10:55', {}],
  ])('múltiplos de 5 minutos: %s a %s', (fromTime, toTime, expected) => {
    expect(blockFormErrors(form({ fromTime, toTime }))).toEqual(expected);
  });

  it('el fin igual o antes del inicio, el mismo día: en la hora hasta', () => {
    expect(blockFormErrors(form({ toTime: '09:00' }))).toEqual({
      toTime: BLOCK_MESSAGES.endsBeforeStart,
    });
    expect(blockFormErrors(form({ toTime: '08:55' }))).toEqual({
      toTime: BLOCK_MESSAGES.endsBeforeStart,
    });
    expect(blockFormErrors(form({ toTime: '09:05' }))).toEqual({});
  });

  it('el fin en un día anterior: en la fecha hasta (también con "Todo el día")', () => {
    expect(blockFormErrors(form({ toDate: '2026-11-19', toTime: '23:00' }))).toEqual({
      toDate: BLOCK_MESSAGES.endsBeforeStart,
    });
    expect(blockFormErrors(form({ allDay: true, toDate: '2026-11-19' }))).toEqual({
      toDate: BLOCK_MESSAGES.endsBeforeStart,
    });
  });

  it('hasta 366 días: 366 pasa; 366 días y 5 minutos, y 367, no', () => {
    const base = { fromDate: '2026-11-20', fromTime: '10:00' };

    expect(blockFormErrors(form({ ...base, toDate: '2027-11-21', toTime: '10:00' }))).toEqual({});
    expect(blockFormErrors(form({ ...base, toDate: '2027-11-21', toTime: '10:05' }))).toEqual({
      toDate: BLOCK_MESSAGES.tooLong,
    });
    expect(blockFormErrors(form({ ...base, toDate: '2027-11-22', toTime: '10:00' }))).toEqual({
      toDate: BLOCK_MESSAGES.tooLong,
    });
  });

  it('hasta 366 días con "Todo el día": del 20/11/2026 al 20/11/2027 pasa (termina el 21 a las 00:00); al 21, no', () => {
    const base = { allDay: true, fromDate: '2026-11-20' };

    expect(blockFormErrors(form({ ...base, toDate: '2027-11-20' }))).toEqual({});
    expect(blockFormErrors(form({ ...base, toDate: '2027-11-21' }))).toEqual({
      toDate: BLOCK_MESSAGES.tooLong,
    });
  });

  it('el motivo: 255 caracteres pasan (un emoji es uno) y 256 no; se cuenta sin los espacios de los extremos', () => {
    expect(blockFormErrors(form({ reason: `${'a'.repeat(254)}🐶` }))).toEqual({});
    expect(blockFormErrors(form({ reason: `  ${'a'.repeat(255)}  ` }))).toEqual({});
    expect(blockFormErrors(form({ reason: 'a'.repeat(256) }))).toEqual({
      reason: BLOCK_MESSAGES.reasonMax,
    });
    expect(blockFormErrors(form({ fromTime: '', reason: 'a'.repeat(256) }))).toEqual({
      fromTime: BLOCK_MESSAGES.startsRequired,
      reason: BLOCK_MESSAGES.reasonMax,
    });
  });
});

describe('formatBlockPeriod: en Asunción, desde los instantes en UTC', () => {
  it.each([
    ['2026-11-20T12:00:00.000000Z', '2026-11-20T13:00:00.000000Z', '20/11/2026, de 09:00 a 10:00'],
    [
      '2026-11-21T01:00:00.000000Z',
      '2026-11-21T09:00:00.000000Z',
      'Del 20/11/2026 22:00 al 21/11/2026 06:00',
    ],
    ['2026-11-20T03:00:00.000000Z', '2026-11-21T03:00:00.000000Z', '20/11/2026, todo el día'],
    [
      '2026-11-20T03:00:00.000000Z',
      '2026-11-23T03:00:00.000000Z',
      'Del 20/11/2026 al 22/11/2026, todo el día',
    ],
    ['2024-03-23T03:00:00.000000Z', '2024-03-24T04:00:00.000000Z', '23/03/2024, todo el día'],
  ])('%s a %s', (starts_at, ends_at, expected) => {
    expect(formatBlockPeriod({ starts_at, ends_at })).toBe(expected);
  });
});

describe('deuda 28: los invisibles de los bordes se recortan como en la API (trimLikeApi)', () => {
  it('la fecha con un U+200B al borde se lee como la API', () => {
    expect(blockFormErrors(form({ fromDate: '\u200B2026-11-20' }))).toEqual({});
  });
});
