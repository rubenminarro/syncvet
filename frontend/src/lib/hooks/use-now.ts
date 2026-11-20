import { useEffect, useState } from 'react';

/** Cada cuánto se vuelve a mirar la hora: 30 s (el ausente se habilita al llegar el inicio; la espera de la cola avanza). */
export const NOW_TICK_MS = 30_000;

/** "Ahora", renovado cada 30 s. */
export function useNow(): Date {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const timer = window.setInterval(() => {
      setNow(new Date());
    }, NOW_TICK_MS);

    return () => {
      window.clearInterval(timer);
    };
  }, []);

  return now;
}
