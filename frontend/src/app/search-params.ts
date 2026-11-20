import { defaultParseSearch, defaultStringifySearch } from '@tanstack/react-router';

/**
 * Los parámetros de la URL que son siempre texto (lo escrito en una búsqueda). El router, por defecto, lee cada valor como
 * JSON: `?buscar=1e3` llegaba como el número 1000, `?buscar=12345678901234567890` perdía dígitos y `?buscar=null` se
 * descartaba; para escribirlos, ponía entre comillas lo que parecía JSON (`?buscar=%221e3%22`). Estas claves se leen y se
 * escriben tal cual; las demás (`pagina`, `tipo`…) siguen con el JSON del router. Desde la 2.6, también las fechas de la
 * auditoría (`desde`, `hasta`: `2026-11-09T13:00`), que son texto aunque parezcan otra cosa; desde la 3.2, la `fecha`
 * de la agenda (`2026-11-17`); desde la 3.6, la `serie` de /turnos (un UUID).
 */
export const TEXT_SEARCH_PARAMS: ReadonlySet<string> = new Set([
  'buscar',
  'desde',
  'hasta',
  'fecha',
  'inicio',
  'serie',
]);

/**
 * Los parámetros de la URL que son una lista de textos (desde la 3.3: los `estados` de /turnos), separados por comas y sin
 * comillas: `?estados=confirmado,fichado`. Se leen siempre como lista (la primera aparición, sin los elementos vacíos) y una
 * lista vacía no se escribe. Sin esto, el router los escribiría como JSON (`?estados=%5B%22confirmado%22%5D`).
 */
export const LIST_SEARCH_PARAMS: ReadonlySet<string> = new Set(['estados']);

function withoutQuestionMark(searchStr: string): string {
  return searchStr.startsWith('?') ? searchStr.slice(1) : searchStr;
}

/**
 * `parseSearch` del router: como el de TanStack, pero las claves de texto llegan siempre como texto y las de lista, como una
 * lista de textos (la primera, si se repiten).
 */
export function parseSearch(searchStr: string): Record<string, unknown> {
  const parsed: Record<string, unknown> = defaultParseSearch(searchStr);
  const raw = new URLSearchParams(withoutQuestionMark(searchStr));

  for (const key of TEXT_SEARCH_PARAMS) {
    const value = raw.get(key);

    if (value !== null) {
      parsed[key] = value;
    }
  }

  for (const key of LIST_SEARCH_PARAMS) {
    const value = raw.get(key);

    if (value !== null) {
      parsed[key] = value.split(',').filter((item) => item !== '');
    }
  }

  return parsed;
}

/** Una lista de la URL: cada elemento codificado, separados por comas (la coma queda legible). */
function listPart(key: string, value: readonly unknown[]): string {
  const items = value.map(String).filter((item) => item !== '');

  return items.length === 0
    ? ''
    : `${encodeURIComponent(key)}=${items.map((item) => encodeURIComponent(item)).join(',')}`;
}

/**
 * `stringifySearch` del router: las claves de texto van sin comillas; las de lista, separadas por comas; las demás, como las
 * escribe TanStack. Respeta el orden.
 */
export function stringifySearch(search: Record<string, unknown>): string {
  const parts: string[] = [];

  for (const [key, value] of Object.entries(search)) {
    if (value === undefined) {
      continue;
    }

    let part: string;

    if (LIST_SEARCH_PARAMS.has(key) && Array.isArray(value)) {
      part = listPart(key, value);
    } else if (TEXT_SEARCH_PARAMS.has(key) && typeof value === 'string') {
      part = new URLSearchParams([[key, value]]).toString();
    } else {
      part = withoutQuestionMark(defaultStringifySearch({ [key]: value }));
    }

    if (part !== '') {
      parts.push(part);
    }
  }

  return parts.length === 0 ? '' : `?${parts.join('&')}`;
}
