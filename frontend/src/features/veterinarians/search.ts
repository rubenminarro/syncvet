import { z } from 'zod';
import { normalizeSearchText, parseRouteId } from '../../lib/search-text';
import type { VeterinarianListFilters } from './types';

export { SEARCH_MAX_LENGTH } from '../../lib/search-text';

/** El filtro de estado del listado, en castellano en la URL; "activos" es el valor por defecto (no va en la URL). */
export const STATUS_FILTERS = ['activos', 'inactivos', 'todos'] as const;

export type StatusFilter = (typeof STATUS_FILTERS)[number];

/**
 * La búsqueda de /veterinarios en la URL: `?buscar=acosta&estado=todos&sucursal=10&pagina=2`. Sin un valor, no va (sin
 * búsqueda, solo los activos, todas las sucursales, la página 1).
 */
export interface VeterinarianListSearch {
  buscar?: string | undefined;
  estado?: Exclude<StatusFilter, 'activos'> | undefined;
  sucursal?: number | undefined;
  pagina?: number | undefined;
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás (el modelo de /clientes).
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const estadoSchema = z.enum(['inactivos', 'todos']);
const positiveIntSchema = (min: number) =>
  z
    .union([z.number(), z.string().regex(/^\d+$/)])
    .transform(Number)
    .pipe(z.number().int().min(min).max(Number.MAX_SAFE_INTEGER));
const sucursalSchema = positiveIntSchema(1);
const paginaSchema = positiveIntSchema(2);

/**
 * `validateSearch` de /veterinarios: un valor inválido (un estado desconocido, "activos", que es el por defecto, una
 * sucursal que no es un entero positivo) se descarta. Las cuatro claves van siempre, con `undefined` si el valor se
 * descarta (el router mezcla lo devuelto sobre la búsqueda cruda).
 */
export function validateVeterinarianListSearch(
  raw: Record<string, unknown>,
): VeterinarianListSearch {
  const buscar = buscarSchema.safeParse(raw.buscar);
  const estado = estadoSchema.safeParse(raw.estado);
  const sucursal = sucursalSchema.safeParse(raw.sucursal);
  const pagina = paginaSchema.safeParse(raw.pagina);

  return {
    buscar: buscar.success ? buscar.data : undefined,
    estado: estado.success ? estado.data : undefined,
    sucursal: sucursal.success ? sucursal.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/**
 * Los filtros de GET veterinarians que salen de la URL: "Inactivos" es `status=inactive`; "Todos", `include_inactive=1`
 * (sin `status`, que manda sobre `include_inactive`); "Activos", ninguno de los dos (lo que la API lista sin filtro).
 */
export function toVeterinarianListFilters(search: VeterinarianListSearch): VeterinarianListFilters {
  return {
    search: search.buscar ?? '',
    status: search.estado === 'inactivos' ? 'inactive' : null,
    include_inactive: search.estado === 'todos',
    branch_id: search.sucursal ?? null,
    page: search.pagina ?? 1,
  };
}

/** El id de /veterinarios/$id: solo un entero positivo; si no, null (la página de no encontrado). */
export function parseVeterinarianId(param: string): number | null {
  return parseRouteId(param);
}

/** `?usuario=` de /veterinarios/nuevo: el usuario elegido de antemano (desde su detalle), un entero positivo. */
export interface VeterinarianCreateSearch {
  usuario?: number | undefined;
}

export function validateVeterinarianCreateSearch(
  raw: Record<string, unknown>,
): VeterinarianCreateSearch {
  const usuario = sucursalSchema.safeParse(raw.usuario);

  return { usuario: usuario.success ? usuario.data : undefined };
}
