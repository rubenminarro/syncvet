import { trimLikeApi } from './format/text';

/** El largo máximo de `search` en la API (ListFilters): vale para todos los listados. */
export const SEARCH_MAX_LENGTH = 100;

/** Lo escrito en la búsqueda, como se manda: sin los espacios ni los invisibles de los extremos (como la API) y hasta 100 caracteres. */
export function normalizeSearchText(text: string): string {
  return trimLikeApi(Array.from(trimLikeApi(text)).slice(0, SEARCH_MAX_LENGTH).join(''));
}

/** El id de una ruta de detalle (`/clientes/$id`, `/pacientes/$id`): solo un entero positivo, sin ceros adelante; si no, null. */
export function parseRouteId(param: string): number | null {
  if (!/^[1-9]\d{0,15}$/.test(param)) {
    return null;
  }

  const id = Number(param);

  return Number.isSafeInteger(id) ? id : null;
}
