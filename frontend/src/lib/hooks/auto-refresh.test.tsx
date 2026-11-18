import { act, render, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { ConfirmDialog } from '../../components/ui';
import { pauseAutoRefresh, useAutoRefreshPaused } from './auto-refresh';

function setHidden(hidden: boolean) {
  Object.defineProperty(document, 'visibilityState', {
    configurable: true,
    get: () => (hidden ? 'hidden' : 'visible'),
  });
  document.dispatchEvent(new Event('visibilitychange'));
}

afterEach(() => {
  setHidden(false);
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
