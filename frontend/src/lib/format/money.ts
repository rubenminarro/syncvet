/**
 * Guaraníes: sin decimales y con separador de miles ("Gs. 1.234.567"). La API puede mandar el importe como número o como
 * texto decimal ("150000.00", BCMath): el texto se valida y se formatea tal cual, sin pasar por un número de coma flotante
 * (Intl.NumberFormat acepta el texto decimal y redondea al guaraní de forma exacta).
 *
 * Un valor vacío (null, undefined o '') o inválido ("abc", "12abc", "1e3", " ") se muestra vacío (''), nunca "Gs. 0".
 */
const GUARANIES = new Intl.NumberFormat('es-PY', {
  style: 'currency',
  currency: 'PYG',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
});

/** Un importe en texto decimal: signo opcional, dígitos y, si hay, punto y decimales. */
const DECIMAL = /^-?\d+(?:\.\d+)?$/;

export function formatGuaranies(value: number | string | null | undefined): string {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? GUARANIES.format(value) : '';
  }

  if (typeof value !== 'string' || !DECIMAL.test(value)) {
    return '';
  }

  return GUARANIES.format(value as `${number}`);
}
