import { QueryClient } from '@tanstack/react-query';
import { isRetryable } from './errors';

/**
 * TanStack Query con los reintentos de las convenciones: una lectura se reintenta una vez solo si falló la red o hubo un
 * 5xx (nunca con un 4xx); una mutación no se reintenta nunca.
 */
export function createQueryClient(options: { retryDelay?: number } = {}): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: (failureCount, error) => failureCount < 1 && isRetryable(error),
        ...(options.retryDelay === undefined ? {} : { retryDelay: options.retryDelay }),
        refetchOnWindowFocus: false,
      },
      mutations: {
        retry: false,
      },
    },
  });
}
