import { createRootRouteWithContext, Outlet } from '@tanstack/react-router';
import { NotFoundPage } from '../app/NotFoundPage';
import type { RouterContext } from '../app/router';

export const Route = createRootRouteWithContext<RouterContext>()({
  component: Outlet,
  notFoundComponent: NotFoundPage,
});
