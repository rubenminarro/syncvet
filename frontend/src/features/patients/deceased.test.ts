import { describe, expect, it } from 'vitest';
import { DECEASED_MESSAGES, deceasedFormErrors, deceasedToApi, deceasedToForm } from './deceased';

// La suite corre en UTC: 2026-11-14T01:30Z son las 22:30 del 13 en Asunción (UTC-3).
const NOW = Date.parse('2026-11-14T01:30:00Z');

describe('deceasedToForm: el momento en la hora de pared de Asunción', () => {
  it('ahora, con la PC en UTC, es el día y la hora de Asunción (no los de la PC)', () => {
    expect(deceasedToForm(NOW)).toEqual({ date: '2026-11-13', time: '22:30' });
  });

  it('el deceased_at guardado (UTC con Z) vuelve a la hora de Asunción', () => {
    expect(deceasedToForm('2026-11-12T13:30:00.000000Z')).toEqual({
      date: '2026-11-12',
      time: '10:30',
    });
  });
});

describe('deceasedToApi: el cuerpo con -03:00 (desvío 8 de la 0.1)', () => {
  it('la hora de pared elegida, con el desplazamiento de Asunción', () => {
    expect(deceasedToApi({ date: '2026-11-12', time: '10:30' })).toBe(
      '2026-11-12T10:30:00.000-03:00',
    );
  });

  it('cerca de medianoche no se corre de día', () => {
    expect(deceasedToApi({ date: '2026-11-12', time: '23:59' })).toBe(
      '2026-11-12T23:59:00.000-03:00',
    );
  });

  it('una fecha inválida no se manda', () => {
    expect(() => deceasedToApi({ date: '2026-02-30', time: '10:00' })).toThrow();
  });
});

describe('deceasedFormErrors', () => {
  it('ahora mismo (el valor por defecto) se puede mandar', () => {
    expect(deceasedFormErrors(deceasedToForm(NOW), { now: NOW })).toEqual({});
  });

  it('un minuto después de ahora es futuro', () => {
    expect(deceasedFormErrors({ date: '2026-11-13', time: '22:31' }, { now: NOW })).toEqual({
      date: DECEASED_MESSAGES.future,
    });
  });

  it('el 14, que ya es "hoy" en UTC pero mañana en Asunción, es futuro', () => {
    expect(deceasedFormErrors({ date: '2026-11-14', time: '00:00' }, { now: NOW })).toEqual({
      date: DECEASED_MESSAGES.future,
    });
  });

  it('el mismo día del nacimiento se acepta; el día anterior, no', () => {
    const birthDate = '2026-11-10';

    expect(
      deceasedFormErrors({ date: '2026-11-10', time: '00:00' }, { now: NOW, birthDate }),
    ).toEqual({});
    expect(
      deceasedFormErrors({ date: '2026-11-09', time: '23:59' }, { now: NOW, birthDate }),
    ).toEqual({ date: DECEASED_MESSAGES.beforeBirth });
  });

  it.each([
    [{ date: '', time: '10:00' }, { date: DECEASED_MESSAGES.date }],
    [{ date: '2026-02-30', time: '10:00' }, { date: DECEASED_MESSAGES.date }],
    [{ date: '2026-11-01', time: '' }, { time: DECEASED_MESSAGES.time }],
    [{ date: '2026-11-01', time: '24:00' }, { time: DECEASED_MESSAGES.time }],
  ])('%j → %j', (values, expected) => {
    expect(deceasedFormErrors(values, { now: NOW })).toEqual(expected);
  });
});
