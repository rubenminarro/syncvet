import { useEffect, useSyncExternalStore } from 'react';

/**
 * La pausa de la actualización periódica (diseño de la F3, pregunta 9, con la precisión del usuario): la agenda se vuelve a
 * pedir cada 60 s solo con la pestaña visible y sin un formulario ni un diálogo abierto. Cada diálogo o formulario abierto
 * suma una pausa (`usePauseAutoRefresh`, que ya usa `ConfirmDialog`) y la suelta al cerrarse; la pestaña oculta pausa sola.
 * Lo que se actualiza solo lee `useAutoRefreshPaused()`.
 */

let pauses = 0;
const listeners = new Set<() => void>();

function emit() {
  for (const listener of listeners) {
    listener();
  }
}

/** Suma una pausa; devuelve cómo soltarla (una sola vez). */
export function pauseAutoRefresh(): () => void {
  let released = false;
  pauses += 1;
  emit();

  return () => {
    if (!released) {
      released = true;
      pauses -= 1;
      emit();
    }
  };
}

function subscribe(listener: () => void) {
  listeners.add(listener);
  document.addEventListener('visibilitychange', listener);

  return () => {
    listeners.delete(listener);
    document.removeEventListener('visibilitychange', listener);
  };
}

function isPaused(): boolean {
  return pauses > 0 || document.visibilityState === 'hidden';
}

/** ¿Está en pausa la actualización? Con un diálogo o un formulario abierto, o con la pestaña oculta. */
export function useAutoRefreshPaused(): boolean {
  return useSyncExternalStore(subscribe, isPaused, () => false);
}

/** Mientras `active` (un diálogo abierto, un formulario con cambios), la actualización periódica queda en pausa. */
export function usePauseAutoRefresh(active: boolean): void {
  useEffect(() => {
    if (!active) {
      return;
    }

    return pauseAutoRefresh();
  }, [active]);
}
