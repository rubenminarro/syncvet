import { useEffect, useState } from 'react';

/**
 * Lo que admite `useDebouncedValue`: solo primitivos. Un objeto o un arreglo armado en cada render es otro valor cada vez:
 * la espera volvería a empezar en cada render y, al cumplirse, el nuevo estado causaría otro render sin fin.
 */
export type DebounceablePrimitive = string | number | boolean | bigint | null | undefined;

/**
 * El valor, `delayMs` después de su último cambio (la espera al escribir de la búsqueda de los listados, 300 ms en la F1).
 * Cada cambio antes de que se cumpla la espera la vuelve a empezar: escribir rápido da un solo valor nuevo.
 */
export function useDebouncedValue<T extends DebounceablePrimitive>(value: T, delayMs: number): T {
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
