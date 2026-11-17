import { z } from 'zod';
import { normalizeSearchText, parseRouteId } from '../../lib/search-text';
import { ROLE_NAMES, type RoleName, type UserListFilters } from './types';

export { SEARCH_MAX_LENGTH } from '../../lib/search-text';

/**
 * La búsqueda de /configuracion/usuarios en la URL: `?buscar=gomez&rol=veterinario&sucursal=10&inactivos=1&pagina=2`. Sin
 * un valor, no va (el valor por defecto: sin búsqueda, todos los roles y sucursales, solo los activos, la página 1). El rol
 * va con el nombre de la API (ya está en castellano).
 */
export interface UserListSearch {
  buscar?: string | undefined;
  rol?: RoleName | undefined;
  sucursal?: number | undefined;
  inactivos?: 1 | undefined;
  pagina?: number | undefined;
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás (el modelo de /clientes).
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const rolSchema = z.enum(ROLE_NAMES);
const sucursalSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(1).max(Number.MAX_SAFE_INTEGER));
const inactivosSchema = z
  .union([z.literal(1), z.literal('1'), z.literal(true)])
  .transform(() => 1 as const);
const paginaSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(2).max(Number.MAX_SAFE_INTEGER));

/**
 * `validateSearch` de /configuracion/usuarios: un valor inválido (un rol desconocido, una sucursal que no es un entero
 * positivo) se descarta y vuelve al valor por defecto. Las cinco claves van siempre, con `undefined` si el valor se descarta
 * (el router mezcla lo devuelto sobre la búsqueda cruda).
 */
export function validateUserListSearch(raw: Record<string, unknown>): UserListSearch {
  const buscar = buscarSchema.safeParse(raw.buscar);
  const rol = rolSchema.safeParse(raw.rol);
  const sucursal = sucursalSchema.safeParse(raw.sucursal);
  const inactivos = inactivosSchema.safeParse(raw.inactivos);
  const pagina = paginaSchema.safeParse(raw.pagina);

  return {
    buscar: buscar.success ? buscar.data : undefined,
    rol: rol.success ? rol.data : undefined,
    sucursal: sucursal.success ? sucursal.data : undefined,
    inactivos: inactivos.success ? inactivos.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/** Los filtros de GET users que salen de la búsqueda de la URL. */
export function toUserListFilters(search: UserListSearch): UserListFilters {
  return {
    search: search.buscar ?? '',
    role: search.rol ?? null,
    branch_id: search.sucursal ?? null,
    include_inactive: search.inactivos === 1,
    page: search.pagina ?? 1,
  };
}

/** El id de /configuracion/usuarios/$id: solo un entero positivo; si no, null (la página de no encontrado). */
export function parseUserId(param: string): number | null {
  return parseRouteId(param);
}

/** ¿Es un rol de la clínica? (el valor de un `<select>` o de una casilla). */
export function isRoleName(value: string): value is RoleName {
  return (ROLE_NAMES as readonly string[]).includes(value);
}
