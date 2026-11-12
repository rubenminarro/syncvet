import { describe, expect, it } from 'vitest';
import {
  formatDate,
  formatDateOnly,
  formatDateTime,
  formatTime,
  todayInAppZone,
  toApiDateTime,
} from './dates';

describe('fechas en America/Asuncion', () => {
  /**
   * Como AsuncionTimezoneTest del backend: desde el 15/10/2024 Paraguay es UTC-3 todo el año (base de zonas IANA 2025a). Un
   * navegador o un Node con datos de zonas viejos todavía aplica el horario de invierno (UTC-4) de marzo a octubre y muestra
   * las 08:00.
   */
  function expectUtcMinusThree(utc: string) {
    expect(
      formatTime(utc),
      `America/Asuncion no es UTC-3 el ${utc}: la base de zonas de este entorno ` +
        `(Node ${process.versions.node}, tz ${process.versions.tz ?? 'desconocida'}) es anterior a la 2025a. ` +
        'Actualizá Node o el navegador.',
    ).toBe('09:00');
  }

  it('Asunción es UTC-3 en invierno: 2026-07-01T12:00:00Z son las 09:00', () => {
    expectUtcMinusThree('2026-07-01T12:00:00Z');
  });

  it('Asunción es UTC-3 en verano', () => {
    expectUtcMinusThree('2026-01-15T12:00:00Z');
  });

  it('los tests corren en UTC (si no, mostrar con la zona de la PC podría pasar inadvertido)', () => {
    expect(Intl.DateTimeFormat().resolvedOptions().timeZone).toBe('UTC');
  });

  it('una hora UTC de las 02:00 se muestra el día anterior a las 23:00', () => {
    expect(formatDateTime('2026-03-10T02:00:00Z')).toBe('09/03/2026 23:00');
    expect(formatDate('2026-03-10T02:00:00Z')).toBe('09/03/2026');
  });

  it('acepta Date y milisegundos', () => {
    const instant = Date.parse('2026-12-31T23:30:00Z');

    expect(formatDateTime(instant)).toBe('31/12/2026 20:30');
    expect(formatDateTime(new Date(instant))).toBe('31/12/2026 20:30');
  });

  it('manda a la API la hora de pared de Asunción con su desplazamiento', () => {
    const sent = toApiDateTime(2026, 7, 1, 9, 0);

    expect(sent).toBe('2026-07-01T09:00:00.000-03:00');
    expect(new Date(sent).toISOString()).toBe('2026-07-01T12:00:00.000Z');
  });

  it('"hoy" es el día de Asunción, no el de UTC', () => {
    // 01:30 UTC del 1 de julio son las 22:30 del 30 de junio en Asunción.
    expect(todayInAppZone('2026-07-01T01:30:00Z')).toBe('2026-06-30');
    expect(todayInAppZone('2026-07-01T03:00:00Z')).toBe('2026-07-01');
  });

  it('"hoy" cambia justo a la medianoche de Asunción (03:00 UTC)', () => {
    expect(todayInAppZone('2026-07-01T02:59:59.999Z')).toBe('2026-06-30');
    expect(todayInAppZone('2026-07-01T03:00:00.000Z')).toBe('2026-07-01');
  });

  it('una fecha sin hora (birth_date) se muestra tal cual, sin convertirla de zona', () => {
    expect(formatDateOnly('2020-05-10')).toBe('10/05/2020');
    expect(formatDateOnly('2024-02-29')).toBe('29/02/2024');
    // formatDate también la reconoce: new Date('2020-05-10') sería medianoche UTC, el 9 en Asunción.
    expect(formatDate('2020-05-10')).toBe('10/05/2020');
  });

  it.each([
    null,
    undefined,
    '',
    '2023-02-29',
    '2020-13-01',
    '2020-04-31',
    '10/05/2020',
    '2020-05-10T00:00:00Z',
  ])('una fecha sin hora vacía o inválida (%j) se muestra vacía', (value) => {
    expect(formatDateOnly(value)).toBe('');
  });

  it('una fecha con hora vacía o inválida se muestra vacía, sin romper el render', () => {
    expect(formatDate('basura')).toBe('');
    expect(formatDateTime(null)).toBe('');
    expect(formatTime(undefined)).toBe('');
    expect(formatDateTime('')).toBe('');
    expect(formatDateTime(Number.NaN)).toBe('');
  });
});
