import { useLocation } from '@tanstack/react-router';
import { validateUserListSearch, type UserListSearch } from './search';

/**
 * La búsqueda del listado de donde se vino, en el estado del historial (no en la URL), como en clientes y sucursales: pasa
 * del listado al detalle, al formulario y de vuelta.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    userList?: UserListSearch;
  }
}

/** La búsqueda guardada, validada como la de la URL; sin estado, la búsqueda vacía. */
export function useUserListSearch(): UserListSearch {
  const saved = useLocation({ select: (location) => location.state.userList });

  return validateUserListSearch({ ...saved });
}

/** El estado para un `<Link>` o un `navigate`: lleva la búsqueda del listado. */
export function withUserList(search: UserListSearch) {
  return { userList: search };
}
