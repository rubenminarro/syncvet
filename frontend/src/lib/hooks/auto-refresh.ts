import { useEffect, useSyncExternalStore } from 'react';

/**
 * La pausa de la actualización periódica (diseño de la F3, pregunta 9, con la precisión del usuario): la agenda se vuelve a
 * pedir cada 60 s solo con la pestaña visible y sin un formulario ni un diálogo abierto. Cada diálogo o formulario abierto
 * suma una pausa (`usePauseAutoRefresh`, que ya usa `ConfirmDialog`) y la suelta al cerrarse; la pestaña oculta pausa sola.
 * Lo que se actualiza solo lee `useAutoRefreshPaused()` y arma su consulta con `autoRefreshQueryOptions(paused)`.
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

/** Cada cuánto se vuelve a pedir lo que se actualiza solo, con la pestaña visible y sin pausas (pregunta 9). */
export const AUTO_REFRESH_MS = 60_000;

/**
 * Las opciones de TanStack Query de lo que se actualiza solo (el día, la semana, la lista y la cola de la agenda, con sus
 * bloqueos): cada 60 s sin pausa y, **al volver a la pestaña**, enseguida si lo traído ya está viejo (su `staleTime`), salvo con
 * un diálogo o un formulario abierto (la misma pausa: se pide al cerrarlo, con el intervalo). El cliente apaga la vuelta a la
 * pestaña para todo lo demás (`refetchOnWindowFocus: false` en `createQueryClient`); acá se enciende solo para esto. Revisión
 * de la 3.7: antes solo la cola lo hacía, y el día, la semana y la lista esperaban al minuto siguiente.
 */
export function autoRefreshQueryOptions(paused: boolean) {
  return {
    refetchInterval: paused ? false : AUTO_REFRESH_MS,
    refetchIntervalInBackground: false,
    // Se evalúa al volver (no con el `paused` del último render, que todavía dice "oculta").
    refetchOnWindowFocus: () => pauses === 0,
  } as const;
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
