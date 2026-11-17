import { useLocation } from '@tanstack/react-router';
import { validateVeterinarianListSearch, type VeterinarianListSearch } from './search';

/**
 * La búsqueda del listado de donde se vino, en el estado del historial (no en la URL), como en clientes y usuarios: pasa
 * del listado al detalle, al formulario y de vuelta.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    veterinarianList?: VeterinarianListSearch;
  }
}

/** La búsqueda guardada, validada como la de la URL; sin estado, la búsqueda vacía. */
export function useVeterinarianListSearch(): VeterinarianListSearch {
  const saved = useLocation({ select: (location) => location.state.veterinarianList });

  return validateVeterinarianListSearch({ ...saved });
}

/** El estado para un `<Link>` o un `navigate`: lleva la búsqueda del listado. */
export function withVeterinarianList(search: VeterinarianListSearch) {
  return { veterinarianList: search };
}
