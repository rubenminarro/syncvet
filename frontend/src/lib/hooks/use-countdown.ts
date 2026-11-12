import { useCallback, useEffect, useState } from 'react';

/**
 * Una cuenta regresiva en segundos (el Retry-After de un 429): `remaining` baja de a uno hasta 0. Se calcula con la hora,
 * no contando los ticks, así un intervalo atrasado no la estira.
 */
export function useCountdown(): { remaining: number; start: (seconds: number) => void } {
  const [until, setUntil] = useState<number | null>(null);
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (until === null) {
      return;
    }

    const id = setInterval(() => {
      const current = Date.now();
      setNow(current);

      if (current >= until) {
        setUntil(null);
      }
    }, 250);

    return () => {
      clearInterval(id);
    };
  }, [until]);

  const start = useCallback((seconds: number) => {
    const current = Date.now();
    setNow(current);
    setUntil(current + seconds * 1000);
  }, []);

  return {
    remaining: until === null ? 0 : Math.max(0, Math.ceil((until - now) / 1000)),
    start,
  };
}
