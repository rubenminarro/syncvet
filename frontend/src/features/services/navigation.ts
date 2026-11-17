import { useLocation } from '@tanstack/react-router';
import { validateServiceListSearch, type ServiceListSearch } from './search';

/**
 * La búsqueda del listado de donde se vino, en el estado del historial (no en la URL), como en clientes y veterinarios: pasa
 * del listado al detalle, al formulario y de vuelta.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    serviceList?: ServiceListSearch;
  }
}

/** La búsqueda guardada, validada como la de la URL; sin estado, la búsqueda vacía. */
export function useServiceListSearch(): ServiceListSearch {
  const saved = useLocation({ select: (location) => location.state.serviceList });

  return validateServiceListSearch({ ...saved });
}

/** El estado para un `<Link>` o un `navigate`: lleva la búsqueda del listado. */
export function withServiceList(search: ServiceListSearch) {
  return { serviceList: search };
}
