import { defineConfig } from '@playwright/test';
import { E2E_DATABASE } from './e2e/support/database';
import { BACKEND_PORT, BACKEND_URL, FRONTEND_PORT, FRONTEND_URL } from './e2e/support/env';

/**
 * Pruebas de punta a punta contra el backend real y la base syncvet_e2e (npm run test:e2e). Levanta el backend
 * (php artisan serve, con DB_DATABASE=syncvet_e2e; si ya hay uno en :8010, lo usa) y Vite con el proxy hacia él; la
 * preparación (e2e/global-setup.ts) recrea la base cada vez. En serie: la base es compartida.
 */
export default defineConfig({
  testDir: 'e2e',
  testMatch: '*.spec.ts',
  globalSetup: './e2e/global-setup.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: process.env.CI !== undefined,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 60_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL: FRONTEND_URL,
    // El Edge instalado (sin descargar navegadores); en otra PC, E2E_BROWSER_CHANNEL=chrome, por ejemplo.
    channel: process.env.E2E_BROWSER_CHANNEL ?? 'msedge',
    locale: 'es-PY',
    timezoneId: 'America/Asuncion',
    trace: 'retain-on-failure',
  },
  webServer: [
    {
      command: `php artisan serve --no-reload --host=127.0.0.1 --port=${String(BACKEND_PORT)}`,
      cwd: '../backend',
      url: `${BACKEND_URL}/up`,
      // Sin la consulta de DNS del email (como la suite del backend): los casos no dependen de la red y los emails de
      // prueba (@example.com, sin MX) se aceptan (1.2).
      env: { DB_DATABASE: E2E_DATABASE, EMAIL_DNS_CHECK: 'false' },
      reuseExistingServer: true,
      timeout: 60_000,
    },
    {
      command: `npx vite --port ${String(FRONTEND_PORT)} --strictPort`,
      url: FRONTEND_URL,
      env: { API_PROXY_TARGET: BACKEND_URL },
      reuseExistingServer: false,
      timeout: 60_000,
    },
  ],
});
