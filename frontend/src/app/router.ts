import type { QueryClient } from '@tanstack/react-query';
import { createRouter, type RouterHistory } from '@tanstack/react-router';
import type { SessionManager } from '../lib/auth/session';
import { routeTree } from '../routeTree.gen';
import { RouteErrorPage } from './RouteErrorPage';
import { parseSearch, stringifySearch } from './search-params';

export interface RouterContext {
  queryClient: QueryClient;
  session: SessionManager;
}

export function createAppRouter(context: RouterContext, history?: RouterHistory) {
  return createRouter({
    routeTree,
    context,
    defaultPreload: 'intent',
    // Un error al cargar una página (un 403 de la API, por ejemplo), dentro del layout; _sesion tiene el suyo.
    defaultErrorComponent: RouteErrorPage,
    scrollRestoration: true,
    // La búsqueda escrita va siempre como texto en la URL (?buscar=1e3 no es el número 1000).
    parseSearch,
    stringifySearch,
    ...(history === undefined ? {} : { history }),
  });
}

declare module '@tanstack/react-router' {
  interface Register {
    router: ReturnType<typeof createAppRouter>;
  }
}
