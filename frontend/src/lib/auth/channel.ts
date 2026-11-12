import type { StoredSession } from './storage';

/** El canal entre las pestañas de la aplicación (mismo origen). */
export const CHANNEL_NAME = 'syncvet-sesion';

export type SessionMessage =
  /** Una pestaña sin sesión le pide la sesión a las abiertas. */
  | { type: 'session-request' }
  /** La respuesta de una pestaña con sesión: el token compartido. */
  | { type: 'session-share'; session: StoredSession }
  /**
   * Una pestaña cerró la sesión: las demás borran la suya. `token_id` dice qué token se cerró (una pestaña con otro token
   * hace su propio logout); `notice`, qué mostrar en /ingresar (null si el usuario salió).
   */
  | { type: 'logout'; token_id: string; notice: string | null };

export interface SessionChannel {
  post(message: SessionMessage): void;
  /** Devuelve la función para dejar de escuchar. */
  subscribe(listener: (message: SessionMessage) => void): () => void;
  close(): void;
}

function isMessage(value: unknown): value is SessionMessage {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const type = (value as { type?: unknown }).type;

  return type === 'session-request' || type === 'session-share' || type === 'logout';
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
        if (isMessage(event.data)) {
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
 * demás pestañas (no a la que lo manda) y más tarde (una microtarea), nunca en el mismo llamado.
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
