import { useCallback, useSyncExternalStore } from 'react';

/**
 * ¿La pantalla cumple la consulta de medios? (2.5: el menú plegable). Sigue los cambios (girar el celular, achicar la
 * ventana) con `matchMedia`. Sin `matchMedia` (jsdom, sin configurarlo en el test), `false`.
 */
export function useMediaQuery(query: string): boolean {
  const subscribe = useCallback(
    (onChange: () => void) => {
      if (typeof window.matchMedia !== 'function') {
        return () => undefined;
      }

      const list = window.matchMedia(query);
      list.addEventListener('change', onChange);

      return () => {
        list.removeEventListener('change', onChange);
      };
    },
    [query],
  );

  return useSyncExternalStore(
    subscribe,
    () => typeof window.matchMedia === 'function' && window.matchMedia(query).matches,
    () => false,
  );
}
