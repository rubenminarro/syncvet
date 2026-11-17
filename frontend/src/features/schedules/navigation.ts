import { useLocation } from '@tanstack/react-router';
import { validateScheduleListSearch, type ScheduleListSearch } from './search';

/**
 * La búsqueda del listado de donde se vino, en el estado del historial (no en la URL), como en servicios: pasa
 * del listado al detalle, al formulario y de vuelta.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    scheduleList?: ScheduleListSearch;
  }
}

/** La búsqueda guardada, validada como la de la URL; sin estado, la búsqueda vacía. */
export function useScheduleListSearch(): ScheduleListSearch {
  const saved = useLocation({ select: (location) => location.state.scheduleList });

  return validateScheduleListSearch({ ...saved });
}

/** El estado para un `<Link>` o un `navigate`: lleva la búsqueda del listado. */
export function withScheduleList(search: ScheduleListSearch) {
  return { scheduleList: search };
}
