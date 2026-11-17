import { useRef } from 'react';

/**
 * Un solo pedido a la vez: un doble clic o dos Enter llegan antes de que el botón quede esperando (la regla de la 1.2).
 * `tryStart` deja pasar el primero y rechaza los demás hasta `release` (en el `onSettled` de la mutación).
 */
export function useSingleAction() {
  const inFlight = useRef(false);

  return {
    tryStart: () => {
      if (inFlight.current) {
        return false;
      }

      inFlight.current = true;

      return true;
    },
    release: () => {
      inFlight.current = false;
    },
  };
}
