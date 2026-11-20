import { QueryClientProvider, useQuery } from '@tanstack/react-query';
import { act, render, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ConfirmDialog } from '../../components/ui';
import { createQueryClient } from '../api/query';
import {
  AUTO_REFRESH_MS,
  autoRefreshQueryOptions,
  pauseAutoRefresh,
  useAutoRefreshPaused,
} from './auto-refresh';

// Como el navegador: `visibilitychange` sube del document a window (ahí escucha TanStack Query la vuelta a la pestaña).
function setHidden(hidden: boolean) {
  Object.defineProperty(document, 'visibilityState', {
    configurable: true,
    get: () => (hidden ? 'hidden' : 'visible'),
  });
  document.dispatchEvent(new Event('visibilitychange', { bubbles: true }));
}

afterEach(() => {
  setHidden(false);
  vi.useRealTimers();
});

describe('la pausa de la actualización periódica', () => {
  it('sin pausas y con la pestaña visible, no está en pausa', () => {
    const { result } = renderHook(() => useAutoRefreshPaused());

    expect(result.current).toBe(false);
  });

  it('cada pausa se suelta una sola vez; con dos, sigue en pausa hasta soltar las dos', () => {
    const { result } = renderHook(() => useAutoRefreshPaused());
    let first: () => void = () => undefined;
    let second: () => void = () => undefined;

    act(() => {
      first = pauseAutoRefresh();
      second = pauseAutoRefresh();
    });
    expect(result.current).toBe(true);

    act(() => {
      first();
      first();
    });
    expect(result.current).toBe(true);

    act(() => {
      second();
    });
    expect(result.current).toBe(false);
  });

  it('con la pestaña oculta, en pausa', () => {
    const { result } = renderHook(() => useAutoRefreshPaused());

    act(() => {
      setHidden(true);
    });
    expect(result.current).toBe(true);

    act(() => {
      setHidden(false);
    });
    expect(result.current).toBe(false);
  });

  it('ConfirmDialog abierto pausa; cerrado, la suelta', () => {
    const { result } = renderHook(() => useAutoRefreshPaused());
    const dialog = (open: boolean) => (
      <ConfirmDialog
        open={open}
        title="Eliminar"
        confirmLabel="Eliminar"
        onConfirm={() => undefined}
        onClose={() => undefined}
      >
        ¿Seguro?
      </ConfirmDialog>
    );
    const { rerender, unmount } = render(dialog(true));

    expect(result.current).toBe(true);
    rerender(dialog(false));
    expect(result.current).toBe(false);
    rerender(dialog(true));
    unmount();
    expect(result.current).toBe(false);
  });
});

/**
 * Lo que la agenda (el día, la semana, la lista y la cola) arma con `autoRefreshQueryOptions`: una consulta con el `staleTime`
 * de la agenda (30 s), con el cliente real (`refetchOnWindowFocus: false` por defecto). Cubre lo que en Edge sin ventana solo
 * se pudo simular (la 3.7, desvío 4): la pestaña oculta y la vuelta.
 */
describe('la actualización de la agenda: el intervalo y la vuelta a la pestaña', () => {
  const STALE_MS = 30_000;

  function setup() {
    vi.useFakeTimers({
      shouldAdvanceTime: true,
      toFake: ['setInterval', 'clearInterval', 'Date'],
      now: new Date('2026-11-17T13:00:00Z'),
    });
    const queryClient = createQueryClient();
    let calls = 0;
    const wrapper = ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    );
    const hook = renderHook(
      () => {
        const paused = useAutoRefreshPaused();

        return useQuery({
          queryKey: ['branch', 10, 'appointments', 'range', { from: '2026-11-17' }],
          queryFn: () => {
            calls += 1;

            return Promise.resolve(calls);
          },
          staleTime: STALE_MS,
          ...autoRefreshQueryOptions(paused),
        });
      },
      { wrapper },
    );

    return { hook, calls: () => calls, queryClient };
  }

  async function advance(ms: number) {
    await act(async () => {
      await vi.advanceTimersByTimeAsync(ms);
    });
  }

  it('cada 60 s con la pestaña visible', async () => {
    const { hook, calls } = setup();
    await vi.waitFor(() => {
      expect(hook.result.current.data).toBe(1);
    });

    await advance(AUTO_REFRESH_MS);
    await vi.waitFor(() => {
      expect(calls()).toBe(2);
    });
  });

  it('oculta, no pide; al volver con lo traído viejo, pide enseguida (sin esperar al minuto)', async () => {
    const { hook, calls } = setup();
    await vi.waitFor(() => {
      expect(hook.result.current.data).toBe(1);
    });

    act(() => {
      setHidden(true);
    });
    await advance(5 * AUTO_REFRESH_MS);
    expect(calls()).toBe(1);

    act(() => {
      setHidden(false);
    });
    await vi.waitFor(() => {
      expect(calls()).toBe(2);
    });
  });

  it('al volver antes de los 30 s, no pide (lo traído todavía vale)', async () => {
    const { hook, calls } = setup();
    await vi.waitFor(() => {
      expect(hook.result.current.data).toBe(1);
    });

    act(() => {
      setHidden(true);
    });
    await advance(STALE_MS / 3);
    act(() => {
      setHidden(false);
    });
    await advance(100);

    expect(calls()).toBe(1);
  });

  it('al volver con un diálogo abierto, no pide; al cerrarlo, sigue el intervalo', async () => {
    const { hook, calls } = setup();
    await vi.waitFor(() => {
      expect(hook.result.current.data).toBe(1);
    });
    let release: () => void = () => undefined;

    act(() => {
      release = pauseAutoRefresh();
      setHidden(true);
    });
    await advance(5 * AUTO_REFRESH_MS);
    act(() => {
      setHidden(false);
    });
    await advance(100);
    expect(calls()).toBe(1);

    act(() => {
      release();
    });
    await advance(AUTO_REFRESH_MS);
    await vi.waitFor(() => {
      expect(calls()).toBe(2);
    });
  });

  it('las demás consultas del cliente no piden al volver (refetchOnWindowFocus: false por defecto)', async () => {
    const { queryClient } = setup();
    let others = 0;
    renderHook(
      () =>
        useQuery({
          queryKey: ['clients', 'list'],
          queryFn: () => {
            others += 1;

            return Promise.resolve(others);
          },
          staleTime: 0,
        }),
      {
        wrapper: ({ children }: { children: ReactNode }) => (
          <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
        ),
      },
    );
    await vi.waitFor(() => {
      expect(others).toBe(1);
    });

    act(() => {
      setHidden(true);
    });
    await advance(AUTO_REFRESH_MS);
    act(() => {
      setHidden(false);
    });
    await advance(100);

    expect(others).toBe(1);
  });
});
