/**
 * El teléfono con la regla de clientes (`ClientMessages`), que repiten las sucursales (`BranchMessages::PHONE_REGEX`) y la
 * configuración de la clínica: opcionalmente un `+`, seguido de 8 a 15 dígitos, sin empezar con 0. Común desde la 2.1.
 */
export const PHONE_PATTERN = /^\+?[1-9]\d{7,14}$/;

/** El mensaje de la API para un teléfono con otra forma (el mismo en clientes y sucursales). */
export const PHONE_MESSAGE =
  'El teléfono debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).';

/** El teléfono como se manda: sin los espacios y guiones que se escriban ("0981 123-456" → "0981123456"). */
export function normalizePhone(value: string): string {
  return value.replace(/[\s-]/g, '');
}
