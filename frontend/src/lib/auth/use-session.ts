import { createContext, use, useSyncExternalStore } from 'react';
import type { SessionManager, SessionSnapshot } from './session';

export const SessionContext = createContext<SessionManager | null>(null);

export function useSessionManager(): SessionManager {
  const manager = use(SessionContext);

  if (manager === null) {
    throw new Error('useSessionManager necesita el SessionContext (App).');
  }

  return manager;
}

/** El estado de la sesión (la sesión, el aviso previo y el aviso para /ingresar), reactivo. */
export function useSessionState(): SessionSnapshot {
  const manager = useSessionManager();

  return useSyncExternalStore(manager.subscribe, manager.getSnapshot);
}
