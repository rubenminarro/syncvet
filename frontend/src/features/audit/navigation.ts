import { useLocation } from '@tanstack/react-router';
import { validateAuditListSearch, type AuditListSearch } from './search';

/**
 * La búsqueda del listado de donde se vino, en el estado del historial (no en la URL), como en usuarios: pasa del listado al
 * detalle y de vuelta, con los filtros.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    auditList?: AuditListSearch;
  }
}

/** La búsqueda guardada, validada como la de la URL; sin estado, la búsqueda vacía. */
export function useAuditListSearch(): AuditListSearch {
  const saved = useLocation({ select: (location) => location.state.auditList });

  return validateAuditListSearch({ ...saved });
}

/** El estado para un `<Link>`: lleva la búsqueda del listado. */
export function withAuditList(search: AuditListSearch) {
  return { auditList: search };
}
