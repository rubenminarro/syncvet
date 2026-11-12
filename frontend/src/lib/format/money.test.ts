import { describe, expect, it } from 'vitest';
import { formatGuaranies } from './money';

// Intl separa "Gs." del importe con un espacio duro; se normaliza para comparar.
function plain(text: string): string {
  return text.replace(/\s/gu, ' ');
}

describe('guaraníes', () => {
  it('con separador de miles y sin decimales', () => {
    expect(plain(formatGuaranies(1234567))).toBe('Gs. 1.234.567');
    expect(plain(formatGuaranies(0))).toBe('Gs. 0');
  });

  it('redondea al guaraní y acepta el texto decimal de la API', () => {
    expect(plain(formatGuaranies('150000.00'))).toBe('Gs. 150.000');
    expect(plain(formatGuaranies(99999.6))).toBe('Gs. 100.000');
  });

  it('el texto decimal se formatea exacto, sin pasar por un número de coma flotante', () => {
    // Number('12345678901234567') es 12345678901234568.
    expect(plain(formatGuaranies('12345678901234567'))).toBe('Gs. 12.345.678.901.234.567');
    expect(plain(formatGuaranies('-2500.00'))).toBe('Gs. -2.500');
  });

  it.each(['', ' ', null, undefined, 'abc', '12abc', '1e3', '1.234.567', Number.NaN, Infinity])(
    'un valor vacío o inválido (%j) se muestra vacío, nunca "Gs. 0"',
    (value) => {
      expect(formatGuaranies(value)).toBe('');
    },
  );
});
