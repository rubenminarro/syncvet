import { useEffect, useState } from 'react';

/**
 * El valor, `delayMs` después de su último cambio (la espera al escribir de la búsqueda de los listados, 300 ms en la F1).
 * Cada cambio antes de que se cumpla la espera la vuelve a empezar: escribir rápido da un solo valor nuevo.
 */
export function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const id = setTimeout(() => {
      setDebounced(value);
    }, delayMs);

    return () => {
      clearTimeout(id);
    };
  }, [value, delayMs]);

  return debounced;
}
