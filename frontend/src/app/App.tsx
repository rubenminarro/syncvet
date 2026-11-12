import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import { RouterProvider, type RouterHistory } from '@tanstack/react-router';
import { useState } from 'react';
import { ToastProvider } from '../components/ui';
import type { SessionManager } from '../lib/auth/session';
import { SessionContext } from '../lib/auth/use-session';
import { createAppRouter } from './router';

interface AppProps {
  queryClient: QueryClient;
  /** La sesión de la pestaña (main.tsx la crea una vez; cada test, la suya). */
  session: SessionManager;
  history?: RouterHistory;
}

/** Los proveedores de la aplicación: TanStack Query, la sesión, los avisos y el router. */
export function App({ queryClient, session, history }: AppProps) {
  const [router] = useState(() => createAppRouter({ queryClient, session }, history));

  return (
    <QueryClientProvider client={queryClient}>
      <SessionContext value={session}>
        <ToastProvider>
          <RouterProvider router={router} />
        </ToastProvider>
      </SessionContext>
    </QueryClientProvider>
  );
}
