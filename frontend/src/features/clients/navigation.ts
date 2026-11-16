import { useLocation } from '@tanstack/react-router';
import { validateClientListSearch, type ClientListSearch } from './search';

/**
 * La búsqueda del listado de donde se vino (`?buscar=&tipo=&pagina=`), en el estado del historial (no en la URL): pasa del
 * listado al detalle, al formulario y de vuelta, y la baja vuelve con ella a la misma página del listado (si quedó fuera de
 * rango, `DataTable` muestra "Esta página no existe.", la regla de la 1.1). Sobrevive a una recarga.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    clientList?: ClientListSearch;
  }
}

/**
 * La búsqueda guardada, validada como la de la URL (el estado del historial también puede venir tocado); sin estado, la
 * búsqueda vacía (la página 1 de todos).
 */
export function useClientListSearch(): ClientListSearch {
  const saved = useLocation({ select: (location) => location.state.clientList });

  return validateClientListSearch({ ...saved });
}

/** El estado para un `<Link>` o un `navigate`: lleva la búsqueda del listado. */
export function withClientList(search: ClientListSearch) {
  return { clientList: search };
}
