import { tanstackRouter } from '@tanstack/router-plugin/vite';
import react from '@vitejs/plugin-react';
import { loadEnv } from 'vite';
import { defineConfig } from 'vitest/config';

// API_PROXY_TARGET no lleva el prefijo VITE_: solo la lee este archivo y no llega al código del navegador.
const DEFAULT_API_PROXY_TARGET = 'http://syncvet.test';

// Los tests corren en UTC: así, mostrar una fecha con la zona de la PC (en lugar de la de Asunción) los hace fallar.
if (process.env.VITEST !== undefined) {
  process.env.TZ = 'UTC';
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const target = env.API_PROXY_TARGET || DEFAULT_API_PROXY_TARGET;

  return {
    plugins: [
      // El plugin del router va antes que el de React (lo pide TanStack Router).
      // En los tests, sin dividir el código: las rutas cargan de una vez y el primer render no espera los pedazos.
      tanstackRouter({
        target: 'react',
        autoCodeSplitting: process.env.VITEST === undefined,
        quoteStyle: 'single',
      }),
      react(),
    ],
    server: {
      proxy: {
        // Mismo origen en desarrollo: el navegador habla con Vite y Vite, con el backend (sin CORS).
        '/api': { target, changeOrigin: true },
      },
    },
    preview: {
      proxy: {
        '/api': { target, changeOrigin: true },
      },
    },
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
      include: ['src/**/*.test.{ts,tsx}'],
      restoreMocks: true,
    },
  };
});
