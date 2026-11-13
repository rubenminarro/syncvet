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
      setupFiles: ['./src/test/setup.ts'],
      restoreMocks: true,
      // Velocidad: preparar jsdom es la mayor parte del tiempo de la suite. Los tests de .ts (lógica, sin DOM) corren en
      // node; los de .tsx (componentes), en jsdom. Un .ts que necesita el DOM lo pide con // @vitest-environment jsdom.
      projects: [
        {
          extends: true,
          test: { name: 'node', environment: 'node', include: ['src/**/*.test.ts'] },
        },
        {
          extends: true,
          // 15 s por test (5 s por defecto): /ingresar y /perfil pasaron los 5 s con la PC cargada (1.1, hallazgo 4).
          test: {
            name: 'dom',
            environment: 'jsdom',
            include: ['src/**/*.test.tsx'],
            testTimeout: 15_000,
          },
        },
      ],
    },
  };
});
