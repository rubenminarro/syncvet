import '@tabler/core/dist/css/tabler.min.css';
import './styles.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './app/App';
import { createQueryClient } from './lib/api/query';
import { SessionManager } from './lib/auth/session';

const root = document.getElementById('root');

if (root === null) {
  throw new Error('Falta el elemento #root en index.html.');
}

// Una sola vez por pestaña, fuera de React: el token del cliente, el canal entre pestañas y los temporizadores.
const queryClient = createQueryClient();
const session = new SessionManager({ queryClient });

createRoot(root).render(
  <StrictMode>
    <App queryClient={queryClient} session={session} />
  </StrictMode>,
);
