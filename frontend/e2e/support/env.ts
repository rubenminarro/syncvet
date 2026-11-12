/** Puertos propios de las pruebas de punta a punta: no chocan con `npm run dev` (5173) ni con el vhost de Laragon. */
export const BACKEND_PORT = 8010;
export const BACKEND_URL = `http://127.0.0.1:${BACKEND_PORT}`;
export const FRONTEND_PORT = 5174;
export const FRONTEND_URL = `http://localhost:${FRONTEND_PORT}`;

/** La contraseña de los usuarios del DemoSeeder en esta corrida: la genera la preparación (global-setup.ts). */
export const PASSWORD_ENV = 'E2E_DEMO_PASSWORD';

/** La sucursal que la preparación crea y le asigna a admin-a (el DemoSeeder da una sola). */
export const SECOND_BRANCH = { code: 'NOR', name: 'Sucursal Norte' } as const;
