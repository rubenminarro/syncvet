/**
 * Guaraníes: sin decimales y con separador de miles ("Gs. 1.234.567"). La API puede mandar el importe como número o como
 * texto decimal ("150000.00", BCMath); se redondea al guaraní.
 */
const GUARANIES = new Intl.NumberFormat('es-PY', {
  style: 'currency',
  currency: 'PYG',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
});

export function formatGuaranies(value: number | string): string {
  const amount = typeof value === 'string' ? Number(value) : value;

  return Number.isFinite(amount) ? GUARANIES.format(amount) : '';
}
