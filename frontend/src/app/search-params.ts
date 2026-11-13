import { defaultParseSearch, defaultStringifySearch } from '@tanstack/react-router';

/**
 * Los parámetros de la URL que son siempre texto (lo escrito en una búsqueda). El router, por defecto, lee cada valor como
 * JSON: `?buscar=1e3` llegaba como el número 1000, `?buscar=12345678901234567890` perdía dígitos y `?buscar=null` se
 * descartaba; para escribirlos, ponía entre comillas lo que parecía JSON (`?buscar=%221e3%22`). Estas claves se leen y se
 * escriben tal cual; las demás (`pagina`, `tipo`…) siguen con el JSON del router.
 */
export const TEXT_SEARCH_PARAMS: ReadonlySet<string> = new Set(['buscar']);

function withoutQuestionMark(searchStr: string): string {
  return searchStr.startsWith('?') ? searchStr.slice(1) : searchStr;
}

/** `parseSearch` del router: como el de TanStack, pero las claves de texto llegan siempre como texto (la primera, si se repiten). */
export function parseSearch(searchStr: string): Record<string, unknown> {
  const parsed: Record<string, unknown> = defaultParseSearch(searchStr);
  const raw = new URLSearchParams(withoutQuestionMark(searchStr));

  for (const key of TEXT_SEARCH_PARAMS) {
    const value = raw.get(key);

    if (value !== null) {
      parsed[key] = value;
    }
  }

  return parsed;
}

/** `stringifySearch` del router: las claves de texto van sin comillas; las demás, como las escribe TanStack. Respeta el orden. */
export function stringifySearch(search: Record<string, unknown>): string {
  const parts: string[] = [];

  for (const [key, value] of Object.entries(search)) {
    if (value === undefined) {
      continue;
    }

    const part =
      TEXT_SEARCH_PARAMS.has(key) && typeof value === 'string'
        ? new URLSearchParams([[key, value]]).toString()
        : withoutQuestionMark(defaultStringifySearch({ [key]: value }));

    if (part !== '') {
      parts.push(part);
    }
  }

  return parts.length === 0 ? '' : `?${parts.join('&')}`;
}
