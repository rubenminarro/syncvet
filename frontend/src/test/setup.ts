import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/react';
import { afterAll, afterEach, beforeAll } from 'vitest';
import { server } from './server';

// findBy* y waitFor esperan hasta 3 s (1 s por defecto): el primer render de una página con el layout (la sesión, `me` y
// los datos) puede pasar el segundo con la PC cargada (las 3 fallas por tiempo de la primera corrida de la 1.1). Un test
// que falla de verdad tarda un poco más en avisar; uno que pasa, lo mismo que antes.
configure({ asyncUtilTimeout: 3000 });

// jsdom no implementa scrollTo (lo usa la restauración del scroll del router).
if (typeof window !== 'undefined') {
  window.scrollTo = () => undefined;
}

// Un pedido sin handler hace fallar el test: ningún test llama a la API real.
beforeAll(() => {
  server.listen({ onUnhandledFrame: 'error' });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
});

afterAll(() => {
  server.close();
});
