import { z } from 'zod';
import { normalizeSearchText, parseRouteId } from '../../lib/search-text';
import { SERVICE_CATEGORY_VALUES, type ServiceCategory, type ServiceListFilters } from './types';

export { SEARCH_MAX_LENGTH } from '../../lib/search-text';

/** El filtro de estado del listado, en castellano en la URL; "activos" es el valor por defecto (no va en la URL). */
export const STATUS_FILTERS = ['activos', 'inactivos', 'todos'] as const;

export type StatusFilter = (typeof STATUS_FILTERS)[number];

/**
 * La búsqueda de /servicios en la URL: `?buscar=vacuna&categoria=vaccination&estado=todos&pagina=2`. Sin un valor, no va
 * (sin búsqueda, todas las categorías, solo los activos, la página 1).
 */
export interface ServiceListSearch {
  buscar?: string | undefined;
  categoria?: ServiceCategory | undefined;
  estado?: Exclude<StatusFilter, 'activos'> | undefined;
  pagina?: number | undefined;
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás (el modelo de /clientes).
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const categoriaSchema = z.enum(SERVICE_CATEGORY_VALUES as [ServiceCategory, ...ServiceCategory[]]);
const estadoSchema = z.enum(['inactivos', 'todos']);
const paginaSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(2).max(Number.MAX_SAFE_INTEGER));

/**
 * `validateSearch` de /servicios: un valor inválido (una categoría desconocida, un estado desconocido o "activos", que es el
 * por defecto, una página que no es un entero mayor que 1) se descarta. Las cuatro claves van siempre, con `undefined` si el
 * valor se descarta (el router mezcla lo devuelto sobre la búsqueda cruda).
 */
export function validateServiceListSearch(raw: Record<string, unknown>): ServiceListSearch {
  const buscar = buscarSchema.safeParse(raw.buscar);
  const categoria = categoriaSchema.safeParse(raw.categoria);
  const estado = estadoSchema.safeParse(raw.estado);
  const pagina = paginaSchema.safeParse(raw.pagina);

  return {
    buscar: buscar.success ? buscar.data : undefined,
    categoria: categoria.success ? categoria.data : undefined,
    estado: estado.success ? estado.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/**
 * Los filtros de GET services que salen de la URL: "Inactivos" es `status=inactive`; "Todos", `include_inactive=1` (sin
 * `status`, que manda sobre `include_inactive`); "Activos", ninguno de los dos (lo que la API lista sin filtro).
 */
export function toServiceListFilters(search: ServiceListSearch): ServiceListFilters {
  return {
    search: search.buscar ?? '',
    category: search.categoria ?? null,
    status: search.estado === 'inactivos' ? 'inactive' : null,
    include_inactive: search.estado === 'todos',
    page: search.pagina ?? 1,
  };
}

/** El id de /servicios/$id: solo un entero positivo; si no, null (la página de no encontrado). */
export function parseServiceId(param: string): number | null {
  return parseRouteId(param);
}
