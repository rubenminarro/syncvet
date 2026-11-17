import { z } from 'zod';
import { normalizeSearchText, parseRouteId } from '../../lib/search-text';
import type { ScheduleListFilters } from './types';

export { SEARCH_MAX_LENGTH } from '../../lib/search-text';

/**
 * La búsqueda de /configuracion/agendas en la URL: `?buscar=consultorio&sucursal=10&inactivas=1&pagina=2`. Sin un valor, no
 * va (sin búsqueda, todas las sucursales, solo las activas, la página 1).
 */
export interface ScheduleListSearch {
  buscar?: string | undefined;
  sucursal?: number | undefined;
  inactivas?: 1 | undefined;
  pagina?: number | undefined;
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás (el modelo de /clientes).
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const sucursalSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(1).max(Number.MAX_SAFE_INTEGER));
const inactivasSchema = z
  .union([z.literal(1), z.literal('1'), z.literal(true)])
  .transform(() => 1 as const);
const paginaSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(2).max(Number.MAX_SAFE_INTEGER));

/**
 * `validateSearch` de /configuracion/agendas: un valor inválido (una sucursal que no es un entero positivo, `inactivas`
 * distinto de 1, una página que no es un entero mayor que 1) se descarta. Las cuatro claves van siempre, con `undefined` si
 * el valor se descarta (el router mezcla lo devuelto sobre la búsqueda cruda).
 */
export function validateScheduleListSearch(raw: Record<string, unknown>): ScheduleListSearch {
  const buscar = buscarSchema.safeParse(raw.buscar);
  const sucursal = sucursalSchema.safeParse(raw.sucursal);
  const inactivas = inactivasSchema.safeParse(raw.inactivas);
  const pagina = paginaSchema.safeParse(raw.pagina);

  return {
    buscar: buscar.success ? buscar.data : undefined,
    sucursal: sucursal.success ? sucursal.data : undefined,
    inactivas: inactivas.success ? inactivas.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/** Los filtros de GET schedules que salen de la búsqueda de la URL. */
export function toScheduleListFilters(search: ScheduleListSearch): ScheduleListFilters {
  return {
    search: search.buscar ?? '',
    branch_id: search.sucursal ?? null,
    include_inactive: search.inactivas === 1,
    page: search.pagina ?? 1,
  };
}

/** El id de /configuracion/agendas/$id: solo un entero positivo; si no, null (la página de no encontrado). */
export function parseScheduleId(param: string): number | null {
  return parseRouteId(param);
}
