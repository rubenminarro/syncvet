import type { QueryClient } from '@tanstack/react-query';
import { api, setSessionErrorHandler, setTokenProvider } from '../api/client';
import { MESSAGES, userMessage } from '../api/errors';
import { createBroadcastSessionChannel, type SessionChannel, type SessionMessage } from './channel';
import {
  clearSession,
  expiresAtMs,
  isStoredSession,
  readSession,
  saveSession,
  tokenId,
  type SessionStorageLike,
  type StoredSession,
} from './storage';

/** El aviso previo: 10 minutos antes de expires_at (P2). */
export const WARNING_BEFORE_MS = 10 * 60 * 1000;

/** Cuánto espera una pestaña nueva la sesión de una abierta (P1). */
export const SESSION_REQUEST_TIMEOUT_MS = 300;

/** setTimeout no acepta más de 2^31 - 1 ms (unos 24 días); con más, el temporizador se vuelve a programar al llegar. */
const MAX_TIMEOUT_MS = 2 ** 31 - 1;

export interface SessionSnapshot {
  /** La sesión de esta pestaña, o null. */
  session: StoredSession | null;
  /** Los minutos que faltaban al encenderse el aviso previo (10, o menos si ya se estaba cerca), o null sin aviso. */
  warningMinutes: number | null;
  /** Qué mostrar en /ingresar después de un cierre que no pidió el usuario (sesión vencida, usuario inactivo…). */
  notice: string | null;
}

export interface SessionManagerOptions {
  queryClient: QueryClient;
  /** sessionStorage en el navegador; uno propio por "pestaña" en los tests. */
  storage?: SessionStorageLike;
  /** BroadcastChannel('syncvet-sesion') en el navegador; uno en memoria en los tests. */
  channel?: SessionChannel;
  requestTimeoutMs?: number;
}

/**
 * La sesión de una pestaña (docs/03-plan-desarrollo/fases/frontend/f0-base.md, "Sesión (0.2)"): el token en
 * sessionStorage, el aviso previo y el vencimiento, el canal entre pestañas, el token del cliente de la API y el cierre ante
 * un error de sesión. Los componentes leen su estado con useSyncExternalStore (use-session.ts).
 */
export class SessionManager {
  private snapshot: SessionSnapshot = { session: null, warningMinutes: null, notice: null };
  private readonly listeners = new Set<() => void>();
  private readonly queryClient: QueryClient;
  private readonly storage: SessionStorageLike;
  private readonly channel: SessionChannel;
  private readonly requestTimeoutMs: number;
  private readonly unsubscribeChannel: () => void;
  private timers: ReturnType<typeof setTimeout>[] = [];
  private pendingRequest: Promise<StoredSession | null> | null = null;
  private receiveShare: ((session: StoredSession | null) => void) | null = null;

  constructor(options: SessionManagerOptions) {
    this.queryClient = options.queryClient;
    this.storage = options.storage ?? window.sessionStorage;
    this.channel = options.channel ?? createBroadcastSessionChannel();
    this.requestTimeoutMs = options.requestTimeoutMs ?? SESSION_REQUEST_TIMEOUT_MS;

    // Una sesión guardada y vencida se borra sin llamar a la API.
    const stored = readSession(this.storage);

    if (stored !== null && isExpired(stored)) {
      clearSession(this.storage);
      this.snapshot = { ...this.snapshot, notice: MESSAGES.sessionExpired };
    } else if (stored !== null) {
      this.snapshot = { ...this.snapshot, session: stored };
    }

    setTokenProvider(() => this.snapshot.session?.token ?? null);
    setSessionErrorHandler((error) => {
      this.end(userMessage(error));
    });
    this.unsubscribeChannel = this.channel.subscribe((message) => {
      this.onMessage(message);
    });

    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', this.onVisibilityChange);
    }

    this.schedule();
  }

  readonly subscribe = (listener: () => void): (() => void) => {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  };

  readonly getSnapshot = (): SessionSnapshot => this.snapshot;

  /** Después del login (o con la sesión de otra pestaña): la guarda y programa el aviso y el vencimiento. */
  start(session: StoredSession): void {
    saveSession(this.storage, session);
    this.set({ session, warningMinutes: null, notice: null });
    this.schedule();
  }

  /**
   * ¿Hay sesión? La propia o, si no hay, la de otra pestaña abierta (session-request, con la espera de requestTimeoutMs).
   * La usa el beforeLoad del layout con sesión.
   */
  async ensureSession(): Promise<boolean> {
    const own = this.snapshot.session;

    if (own !== null) {
      if (isExpired(own)) {
        this.expire();

        return false;
      }

      return true;
    }

    const shared = await this.requestFromOtherTabs();

    if (shared === null || isExpired(shared)) {
      return false;
    }

    this.start(shared);

    return true;
  }

  /** Salir: POST logout con el token (si falla, igual se cierra) y el cierre en todas las pestañas. */
  async logout(): Promise<void> {
    const session = this.snapshot.session;

    if (session === null) {
      return;
    }

    try {
      await api.post<null>('logout', undefined, { token: session.token });
    } catch {
      // El token puede estar vencido o el servidor caído: la sesión local se cierra igual.
    }

    this.end(null);
  }

  /**
   * Cierra la sesión de esta pestaña: borra el almacenamiento, los temporizadores y la caché de Query y, si `broadcast`,
   * publica `logout` para que las demás pestañas también la cierren. `notice` es lo que muestra /ingresar.
   */
  end(notice: string | null, broadcast = true): void {
    const session = this.snapshot.session;

    clearSession(this.storage);
    this.clearTimers();
    this.set({ session: null, warningMinutes: null, notice });

    if (broadcast && session !== null) {
      this.channel.post({ type: 'logout', token_id: tokenId(session.token), notice });
    }

    this.queryClient.clear();
  }

  dispose(): void {
    this.clearTimers();
    this.unsubscribeChannel();
    this.channel.close();
    this.receiveShare?.(null);

    if (typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', this.onVisibilityChange);
    }

    setTokenProvider(() => null);
    setSessionErrorHandler(() => undefined);
  }

  private set(snapshot: SessionSnapshot): void {
    this.snapshot = snapshot;

    for (const listener of this.listeners) {
      listener();
    }
  }

  private expire(): void {
    this.end(MESSAGES.sessionExpired);
  }

  private requestFromOtherTabs(): Promise<StoredSession | null> {
    if (this.pendingRequest !== null) {
      return this.pendingRequest;
    }

    this.pendingRequest = new Promise((resolve) => {
      const finish = (session: StoredSession | null) => {
        clearTimeout(timer);
        this.receiveShare = null;
        this.pendingRequest = null;
        resolve(session);
      };
      const timer = setTimeout(() => {
        finish(null);
      }, this.requestTimeoutMs);

      this.receiveShare = finish;
      this.channel.post({ type: 'session-request' });
    });

    return this.pendingRequest;
  }

  private onMessage(message: SessionMessage): void {
    const own = this.snapshot.session;

    switch (message.type) {
      case 'session-request':
        if (own !== null && !isExpired(own)) {
          this.channel.post({ type: 'session-share', session: own });
        }
        break;
      case 'session-share':
        this.receiveShare?.(isStoredSession(message.session) ? message.session : null);
        break;
      case 'logout':
        if (own === null) {
          clearSession(this.storage);
          break;
        }

        // Dos ingresos simultáneos: esta pestaña tiene otro token y también lo cierra en la API.
        if (tokenId(own.token) !== message.token_id) {
          void api.post<null>('logout', undefined, { token: own.token }).catch(() => undefined);
        }

        this.end(message.notice, false);
        break;
    }
  }

  /** Los temporizadores se frenan con la PC suspendida: al volver a la pestaña se revisa el vencimiento. */
  private readonly onVisibilityChange = (): void => {
    const own = this.snapshot.session;

    if (own === null || document.visibilityState !== 'visible') {
      return;
    }

    // Se relee el almacenamiento: si expires_at cambió (a mano, desde DevTools) se toma el nuevo; si no está, se cierra.
    const stored = readSession(this.storage);

    if (stored?.token !== own.token) {
      this.expire();

      return;
    }

    if (stored.expires_at !== own.expires_at) {
      this.set({ ...this.snapshot, session: stored, warningMinutes: null });
    }

    this.schedule();
  };

  /** Programa el aviso previo y el vencimiento de la sesión actual (o los aplica ya, si su hora pasó). */
  private schedule(): void {
    this.clearTimers();

    const session = this.snapshot.session;
    const expiresAt = session === null ? null : expiresAtMs(session);

    if (session === null || expiresAt === null) {
      return;
    }

    const now = Date.now();

    if (expiresAt <= now) {
      this.expire();

      return;
    }

    if (now >= expiresAt - WARNING_BEFORE_MS) {
      this.warn(expiresAt);
    } else {
      this.later(expiresAt - WARNING_BEFORE_MS - now, () => {
        this.schedule();
      });
    }

    this.later(expiresAt - now, () => {
      this.schedule();
    });
  }

  private warn(expiresAt: number): void {
    const minutes = Math.max(1, Math.ceil((expiresAt - Date.now()) / 60_000));

    if (this.snapshot.warningMinutes === null) {
      this.set({ ...this.snapshot, warningMinutes: minutes });
    }
  }

  private later(delay: number, callback: () => void): void {
    this.timers.push(setTimeout(callback, Math.min(delay, MAX_TIMEOUT_MS)));
  }

  private clearTimers(): void {
    for (const timer of this.timers) {
      clearTimeout(timer);
    }

    this.timers = [];
  }
}

function isExpired(session: StoredSession): boolean {
  const expiresAt = expiresAtMs(session);

  return expiresAt === null || expiresAt <= Date.now();
}
