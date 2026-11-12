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

  it('un valor que no es un número queda vacío', () => {
    expect(formatGuaranies('abc')).toBe('');
  });
});
