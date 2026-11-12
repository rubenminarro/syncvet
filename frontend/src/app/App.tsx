import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import { RouterProvider, type RouterHistory } from '@tanstack/react-router';
import { useState } from 'react';
import { ToastProvider } from '../components/ui';
import { createQueryClient } from '../lib/api/query';
import { createAppRouter } from './router';

interface AppProps {
  queryClient?: QueryClient;
  history?: RouterHistory;
}

/** Los proveedores de la aplicación: TanStack Query, los avisos y el router. */
export function App({ queryClient, history }: AppProps) {
  const [client] = useState(() => queryClient ?? createQueryClient());
  const [router] = useState(() => createAppRouter(client, history));

  return (
    <QueryClientProvider client={client}>
      <ToastProvider>
        <RouterProvider router={router} />
      </ToastProvider>
    </QueryClientProvider>
  );
}
