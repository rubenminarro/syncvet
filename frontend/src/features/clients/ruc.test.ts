import { describe, expect, it } from 'vitest';
import { hasValidRucCheckDigit, rucCheckDigit } from './ruc';

/**
 * Los casos de backend/tests/Unit/RucCheckDigitTest.php, copiados tal cual (no inventados). Los RUC publicados son reales:
 * el backend los permite solo en ese test (R-010); este es su espejo, para que las dos cuentas no se separen.
 */
describe('rucCheckDigit: los casos de RucCheckDigitTest del backend', () => {
  it.each([
    ['Telecel', '80000519', 8],
    ['Petropar', '80002675', 6],
    ['Banco Itaú', '80002201', 7],
    // Resto 0 y resto 1: los dos dan 0.
    ['Cervecería Paraguaya (resto 0)', '80003457', 0],
    ['DHL Paraguay (resto 0)', '80004866', 0],
    ['BBVA Paraguay (resto 1)', '80022955', 0],
    ['Agrotec (resto 1)', '80020753', 0],
  ])('%s: %s da %i', (_name, ruc, digit) => {
    expect(rucCheckDigit(ruc)).toBe(digit);
  });

  it('con 11 dígitos o más el factor vuelve a 2 después de 11', () => {
    expect(rucCheckDigit('12345678901')).toBe(5);
  });

  it('9 y 10 dígitos llegan a los factores 10 y 11 sin volver a 2', () => {
    expect(rucCheckDigit('123456789')).toBe(0);
    expect(rucCheckDigit('1234567890')).toBe(9);
  });
});

/** Los documentos de ClientStoreTest y ClientUpdateTest (ficticios, R-011). */
describe('hasValidRucCheckDigit: la regla de RucCheckDigit', () => {
  it.each(['99999903-6', '99999904-4', '99999905-2'])('%s, con su dígito, pasa', (document) => {
    expect(hasValidRucCheckDigit(document)).toBe(true);
  });

  it.each(['99999903', '99999904', '99999905'])('%s con el dígito equivocado no pasa', (number) => {
    // Como wrongRuc() del backend: el dígito correcto más uno.
    const wrong = `${number}-${String((rucCheckDigit(number) + 1) % 10)}`;
    expect(hasValidRucCheckDigit(wrong)).toBe(false);
  });

  it.each(['99400003', 'AB-12345-9', 'X99999903-1'])(
    '%s no tiene la forma de un RUC: no se revisa',
    (document) => {
      expect(hasValidRucCheckDigit(document)).toBe(true);
    },
  );
});
