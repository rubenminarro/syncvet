import { isStoredSession, type StoredSession } from './storage';

/** El canal entre las pestañas de la aplicación (mismo origen). */
export const CHANNEL_NAME = 'syncvet-sesion';

export type SessionMessage =
  /** Una pestaña sin sesión le pide la sesión a las abiertas. */
  | { type: 'session-request' }
  /** La respuesta de una pestaña con sesión: el token compartido. */
  | { type: 'session-share'; session: StoredSession }
  /**
   * Una pestaña cerró la sesión. `token_id` dice qué token se cerró; `notice`, qué mostrar en /ingresar (null si el usuario
   * salió). `all`: true si el usuario salió a propósito (todas las pestañas cierran y una con otro token también lo revoca);
   * false si el token venció o la API lo rechazó (solo cierran las pestañas con ese mismo token).
   */
  | { type: 'logout'; token_id: string; notice: string | null; all: boolean };

export interface SessionChannel {
  post(message: SessionMessage): void;
  /** Devuelve la función para dejar de escuchar. */
  subscribe(listener: (message: SessionMessage) => void): () => void;
  close(): void;
}

/**
 * Un mensaje del canal con su formato completo (y sin campos de más): lo que no cumple se descarta. Una sesión compartida
 * se revisa como la guardada (token no vacío y expires_at con fecha válida).
 */
export function isSessionMessage(value: unknown): value is SessionMessage {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return false;
  }

  const message = value as Record<string, unknown>;
  const keys = Object.keys(message).sort().join(',');

  switch (message.type) {
    case 'session-request':
      return keys === 'type';
    case 'session-share':
      return keys === 'session,type' && isStoredSession(message.session);
    case 'logout':
      return (
        keys === 'all,notice,token_id,type' &&
        typeof message.token_id === 'string' &&
        (message.notice === null || typeof message.notice === 'string') &&
        typeof message.all === 'boolean'
      );
    default:
      return false;
  }
}

/** El canal sobre BroadcastChannel; sin BroadcastChannel (un navegador viejo), uno que no hace nada. */
export function createBroadcastSessionChannel(name: string = CHANNEL_NAME): SessionChannel {
  if (typeof BroadcastChannel === 'undefined') {
    return { post: () => undefined, subscribe: () => () => undefined, close: () => undefined };
  }

  const channel = new BroadcastChannel(name);

  return {
    post: (message) => {
      channel.postMessage(message);
    },
    subscribe: (listener) => {
      const handler = (event: MessageEvent) => {
        if (isSessionMessage(event.data)) {
          listener(event.data);
        }
      };
      channel.addEventListener('message', handler);

      return () => {
        channel.removeEventListener('message', handler);
      };
    },
    close: () => {
      channel.close();
    },
  };
}

/**
 * Un "BroadcastChannel" en memoria para los tests: cada `connect()` es una pestaña. Como el real, un mensaje llega a las
 * demás pestañas (no a la que lo manda) y más tarde (una microtarea), nunca en el mismo llamado, y solo si tiene el
 * formato del canal (isSessionMessage).
 */
export function createMemoryChannelHub() {
  const tabs = new Set<{ listeners: Set<(message: SessionMessage) => void> }>();

  return {
    connect(): SessionChannel {
      const tab = { listeners: new Set<(message: SessionMessage) => void>() };
      tabs.add(tab);

      return {
        post: (message) => {
          const copy = structuredClone(message);

          if (!isSessionMessage(copy)) {
            return;
          }

          for (const other of tabs) {
            if (other !== tab) {
              queueMicrotask(() => {
                for (const listener of other.listeners) {
                  listener(copy);
                }
              });
            }
          }
        },
        subscribe: (listener) => {
          tab.listeners.add(listener);

          return () => {
            tab.listeners.delete(listener);
          };
        },
        close: () => {
          tabs.delete(tab);
        },
      };
    },
  };
}
