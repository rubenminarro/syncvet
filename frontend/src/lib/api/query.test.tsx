import { QueryClientProvider, useMutation, useQuery } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { useEffect } from 'react';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { server } from '../../test/server';
import { api } from './client';
import { createQueryClient } from './query';

function Reader() {
  const query = useQuery({ queryKey: ['me'], queryFn: () => api.get<{ id: number }>('me') });

  return <p>{query.status}</p>;
}

function Writer() {
  const mutation = useMutation({ mutationFn: () => api.post('clients', {}) });
  const { mutate } = mutation;

  useEffect(() => {
    mutate();
  }, [mutate]);

  return <p>{mutation.status}</p>;
}

function renderWith(ui: React.ReactNode) {
  return render(
    <QueryClientProvider client={createQueryClient({ retryDelay: 0 })}>{ui}</QueryClientProvider>,
  );
}

function countCalls(method: 'get' | 'post', path: string, respond: () => Response) {
  const calls = { count: 0 };
  server.use(
    http[method](path, () => {
      calls.count++;
      return respond();
    }),
  );

  return calls;
}

describe('reintentos de TanStack Query', () => {
  it('una lectura con 500 se reintenta una vez', async () => {
    const calls = countCalls('get', '/api/me', () =>
      failure(500, 'INTERNAL_SERVER_ERROR', 'Error interno.'),
    );

    renderWith(<Reader />);

    expect(await screen.findByText('error')).toBeInTheDocument();
    expect(calls.count).toBe(2);
  });

  it('una lectura sin red se reintenta una vez', async () => {
    const calls = countCalls('get', '/api/me', () => HttpResponse.error());

    renderWith(<Reader />);

    expect(await screen.findByText('error')).toBeInTheDocument();
    expect(calls.count).toBe(2);
  });

  it('una lectura que se recupera en el reintento termina bien', async () => {
    let first = true;
    const calls = countCalls('get', '/api/me', () => {
      if (first) {
        first = false;
        return failure(503, 'SERVICE_UNAVAILABLE', 'No disponible.');
      }
      return success({ id: 1 });
    });

    renderWith(<Reader />);

    expect(await screen.findByText('success')).toBeInTheDocument();
    expect(calls.count).toBe(2);
  });

  async function expectNoRetry(status: number, code: string) {
    const calls = countCalls('get', '/api/me', () => failure(status, code, 'No.'));

    renderWith(<Reader />);

    expect(await screen.findByText('error')).toBeInTheDocument();
    expect(calls.count).toBe(1);
  }

  it('una lectura con 403 no se reintenta', () => expectNoRetry(403, 'FORBIDDEN'));
  it('una lectura con 404 no se reintenta', () => expectNoRetry(404, 'RESOURCE_NOT_FOUND'));
  it('una lectura con 422 no se reintenta', () => expectNoRetry(422, 'VALIDATION_ERROR'));
  it('una lectura con 429 no se reintenta', () => expectNoRetry(429, 'TOO_MANY_REQUESTS'));

  it('una mutación no se reintenta nunca, ni con un 500', async () => {
    const calls = countCalls('post', '/api/clients', () =>
      failure(500, 'INTERNAL_SERVER_ERROR', 'Error interno.'),
    );

    renderWith(<Writer />);

    expect(await screen.findByText('error')).toBeInTheDocument();
    await waitFor(() => {
      expect(calls.count).toBe(1);
    });
  });
});
