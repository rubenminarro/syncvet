import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterAll, afterEach, beforeAll } from 'vitest';
import { server } from './server';

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
