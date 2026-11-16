import { z } from 'zod';
import { normalizeSearchText, parseRouteId } from '../../lib/search-text';
import type { PatientListFilters } from './types';

/**
 * La búsqueda de /pacientes en la URL: `?buscar=toby&especie=3&fallecidos=1&pagina=2`. Sin un valor, no va (el valor por
 * defecto: sin búsqueda, todas las especies, sin los fallecidos, la página 1).
 */
export interface PatientListSearch {
  buscar?: string | undefined;
  especie?: number | undefined;
  fallecidos?: 1 | undefined;
  pagina?: number | undefined;
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás. El router ya convierte
// `?pagina=2` en el número 2 (lo lee como JSON); un texto ("2") también se acepta. `buscar` se lee siempre como texto
// (TEXT_SEARCH_PARAMS de search-params.ts).
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const positiveInteger = (min: number) =>
  z
    .union([z.number(), z.string().regex(/^\d+$/)])
    .transform(Number)
    .pipe(z.number().int().min(min).max(Number.MAX_SAFE_INTEGER));
const especieSchema = positiveInteger(1);
const paginaSchema = positiveInteger(2);
const fallecidosSchema = z
  .union([z.literal(1), z.literal('1'), z.literal(true)])
  .transform(() => 1 as const);

/**
 * `validateSearch` de /pacientes: un valor inválido (una especie o una página que no es un entero positivo, `fallecidos`
 * distinto de 1) se descarta y vuelve al valor por defecto; nunca rompe la página. La búsqueda se recorta a 100 caracteres.
 *
 * Las cuatro claves van siempre, con `undefined` si el valor se descarta: el router mezcla lo que devuelve esta función sobre
 * la búsqueda cruda de la URL, así que una clave que faltara dejaría pasar el valor inválido (frontend/CLAUDE.md §5).
 */
export function validatePatientListSearch(raw: Record<string, unknown>): PatientListSearch {
  const buscar = buscarSchema.safeParse(raw.buscar);
  const especie = especieSchema.safeParse(raw.especie);
  const fallecidos = fallecidosSchema.safeParse(raw.fallecidos);
  const pagina = paginaSchema.safeParse(raw.pagina);

  return {
    buscar: buscar.success ? buscar.data : undefined,
    especie: especie.success ? especie.data : undefined,
    fallecidos: fallecidos.success ? fallecidos.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/** Los filtros de GET patients que salen de la búsqueda de la URL. */
export function toPatientListFilters(search: PatientListSearch): PatientListFilters {
  return {
    search: search.buscar ?? '',
    species_id: search.especie ?? null,
    include_deceased: search.fallecidos === 1,
    page: search.pagina ?? 1,
  };
}

/** El id de /pacientes/$id: solo un entero positivo (sin ceros adelante); si no, null (la página de no encontrado). */
export function parsePatientId(param: string): number | null {
  return parseRouteId(param);
}
