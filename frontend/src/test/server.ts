import { setupServer } from 'msw/node';

/** El servidor de MSW de los tests. Cada test suma sus handlers con server.use(). */
export const server = setupServer();
