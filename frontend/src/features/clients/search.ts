import { z } from 'zod';
import type { ClientKind, ClientListFilters } from './types';

/** El largo máximo de `search` en la API (ListFilters). */
export const SEARCH_MAX_LENGTH = 100;

/** El tipo en la URL, en castellano, y su valor en la API. */
const KIND_BY_PARAM = { persona: 'person', empresa: 'company' } as const satisfies Record<
  string,
  ClientKind
>;

export type KindParam = keyof typeof KIND_BY_PARAM;

/** La búsqueda de /clientes en la URL: `?buscar=perez&tipo=empresa&pagina=2`. Sin un valor, no va (el valor por defecto). */
export interface ClientListSearch {
  buscar?: string | undefined;
  tipo?: KindParam | undefined;
  pagina?: number | undefined;
}

/** Lo escrito en la búsqueda, como se manda: sin los espacios de los extremos y hasta 100 caracteres. */
export function normalizeSearchText(text: string): string {
  return text.trim().slice(0, SEARCH_MAX_LENGTH).trim();
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás. El router ya convierte
// `?pagina=2` en el número 2 (lo lee como JSON); un texto ("2") también se acepta.
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const tipoSchema = z.enum(['persona', 'empresa']);
const paginaSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(2).max(Number.MAX_SAFE_INTEGER));

/**
 * `validateSearch` de /clientes: un valor inválido (una página que no es un entero positivo, un tipo desconocido) se
 * descarta y vuelve al valor por defecto; nunca rompe la página. La búsqueda se recorta a 100 caracteres. Los valores por
 * defecto (sin búsqueda, todos los tipos, la página 1) no van en la URL.
 *
 * Las tres claves van siempre, con `undefined` si el valor se descarta: el router mezcla lo que devuelve esta función sobre
 * la búsqueda cruda de la URL, así que una clave que faltara dejaría pasar el valor inválido (`pagina=-4`).
 */
export function validateClientListSearch(raw: Record<string, unknown>): ClientListSearch {
  const buscar = buscarSchema.safeParse(raw.buscar);
  const tipo = tipoSchema.safeParse(raw.tipo);
  const pagina = paginaSchema.safeParse(raw.pagina);

  return {
    buscar: buscar.success ? buscar.data : undefined,
    tipo: tipo.success ? tipo.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/** Los filtros de GET clients que salen de la búsqueda de la URL. */
export function toListFilters(search: ClientListSearch): ClientListFilters {
  return {
    search: search.buscar ?? '',
    kind: search.tipo === undefined ? null : KIND_BY_PARAM[search.tipo],
    page: search.pagina ?? 1,
  };
}

/** El id de /clientes/$id: solo un entero positivo (sin ceros adelante); si no, null (la página de no encontrado). */
export function parseClientId(param: string): number | null {
  if (!/^[1-9]\d{0,15}$/.test(param)) {
    return null;
  }

  const id = Number(param);

  return Number.isSafeInteger(id) ? id : null;
}
