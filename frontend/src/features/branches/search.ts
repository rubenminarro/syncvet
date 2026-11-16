import { z } from 'zod';
import { normalizeSearchText, parseRouteId } from '../../lib/search-text';
import type { BranchListFilters } from './types';

export { SEARCH_MAX_LENGTH } from '../../lib/search-text';

/**
 * La búsqueda de /configuracion/sucursales en la URL: `?buscar=cen&inactivas=1&pagina=2`. Sin un valor, no va (el valor por
 * defecto: sin búsqueda, solo las activas, la página 1).
 */
export interface BranchListSearch {
  buscar?: string | undefined;
  inactivas?: 1 | undefined;
  pagina?: number | undefined;
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás (el modelo de /clientes).
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const inactivasSchema = z
  .union([z.literal(1), z.literal('1'), z.literal(true)])
  .transform(() => 1 as const);
const paginaSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(2).max(Number.MAX_SAFE_INTEGER));

/**
 * `validateSearch` de /configuracion/sucursales: un valor inválido se descarta y vuelve al valor por defecto. Las tres claves
 * van siempre, con `undefined` si el valor se descarta (el router mezcla lo devuelto sobre la búsqueda cruda).
 */
export function validateBranchListSearch(raw: Record<string, unknown>): BranchListSearch {
  const buscar = buscarSchema.safeParse(raw.buscar);
  const inactivas = inactivasSchema.safeParse(raw.inactivas);
  const pagina = paginaSchema.safeParse(raw.pagina);

  return {
    buscar: buscar.success ? buscar.data : undefined,
    inactivas: inactivas.success ? inactivas.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/** Los filtros de GET branches que salen de la búsqueda de la URL. */
export function toBranchListFilters(search: BranchListSearch): BranchListFilters {
  return {
    search: search.buscar ?? '',
    include_inactive: search.inactivas === 1,
    page: search.pagina ?? 1,
  };
}

/** El id de /configuracion/sucursales/$id: solo un entero positivo; si no, null (la página de no encontrado). */
export function parseBranchId(param: string): number | null {
  return parseRouteId(param);
}
