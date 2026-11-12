import type { QueryClient } from '@tanstack/react-query';
import { createRouter, type RouterHistory } from '@tanstack/react-router';
import type { SessionManager } from '../lib/auth/session';
import { routeTree } from '../routeTree.gen';

export interface RouterContext {
  queryClient: QueryClient;
  session: SessionManager;
}

export function createAppRouter(context: RouterContext, history?: RouterHistory) {
  return createRouter({
    routeTree,
    context,
    defaultPreload: 'intent',
    scrollRestoration: true,
    ...(history === undefined ? {} : { history }),
  });
}

declare module '@tanstack/react-router' {
  interface Register {
    router: ReturnType<typeof createAppRouter>;
  }
}
