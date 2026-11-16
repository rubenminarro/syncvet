import { z } from 'zod';

/**
 * La búsqueda de /especies en la URL: `?inactivas=1` incluye las especies y razas inactivas (solo para quien puede
 * activarlas, `species.update`). Sin el valor, no va: por defecto, solo las activas, como los demás listados.
 */
export interface SpeciesListSearch {
  inactivas?: 1 | undefined;
}

const inactivasSchema = z
  .union([z.literal(1), z.literal('1'), z.literal(true)])
  .transform(() => 1 as const);

/**
 * `validateSearch` de /especies: un valor distinto de 1 se descarta (vuelve a solo las activas). La clave va siempre, con
 * `undefined` si se descarta: el router mezcla lo devuelto sobre la búsqueda cruda (frontend/CLAUDE.md §5).
 */
export function validateSpeciesListSearch(raw: Record<string, unknown>): SpeciesListSearch {
  const inactivas = inactivasSchema.safeParse(raw.inactivas);

  return { inactivas: inactivas.success ? inactivas.data : undefined };
}
