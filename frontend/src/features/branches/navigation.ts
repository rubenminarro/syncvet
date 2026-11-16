import { useLocation } from '@tanstack/react-router';
import { validateBranchListSearch, type BranchListSearch } from './search';

/**
 * La búsqueda del listado de donde se vino, en el estado del historial (no en la URL), como en clientes: pasa del listado al
 * detalle, al formulario y de vuelta.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    branchList?: BranchListSearch;
  }
}

/** La búsqueda guardada, validada como la de la URL; sin estado, la búsqueda vacía. */
export function useBranchListSearch(): BranchListSearch {
  const saved = useLocation({ select: (location) => location.state.branchList });

  return validateBranchListSearch({ ...saved });
}

/** El estado para un `<Link>` o un `navigate`: lleva la búsqueda del listado. */
export function withBranchList(search: BranchListSearch) {
  return { branchList: search };
}
