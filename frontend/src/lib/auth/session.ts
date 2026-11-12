import type { QueryClient } from '@tanstack/react-query';
import { api, setSessionErrorHandler, setTokenProvider } from '../api/client';
import { MESSAGES, userMessage } from '../api/errors';
import { createBroadcastSessionChannel, type SessionChannel, type SessionMessage } from './channel';
import {
  clearSession,
  expiresAtMs,
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

/** Cuánto se espera el POST logout de "Salir" (la sesión local ya se cerró antes de mandarlo). */
export const LOGOUT_TIMEOUT_MS = 5000;

/**
 * Cada cuánto se compara la hora con expires_at, además de los temporizadores: con la PC suspendida y la pestaña a la vista
 * no llega visibilitychange y los temporizadores pueden no contar el tiempo suspendido. También actualiza los minutos del
 * aviso previo.
 */
export const CHECK_INTERVAL_MS = 15_000;

/** setTimeout no acepta más de 2^31 - 1 ms (unos 24 días); con más, el temporizador se vuelve a programar al llegar. */
const MAX_TIMEOUT_MS = 2 ** 31 - 1;

export interface SessionSnapshot {
  /** La sesión de esta pestaña, o null. */
  session: StoredSession | null;
  /** Los minutos que quedan con el aviso previo encendido (se actualizan), o null sin aviso. */
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
  logoutTimeoutMs?: number;
}

/**
 * A quién avisa un cierre: `all`, a todas las pestañas (el usuario salió: las que tienen otro token también lo revocan);
 * `token`, solo a las que tienen el mismo token (venció o la API lo rechazó); `none`, a ninguna (el cierre vino del canal).
 */
type Publish = 'all' | 'token' | 'none';

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
  private readonly logoutTimeoutMs: number;
  private readonly unsubscribeChannel: () => void;
  private timers: ReturnType<typeof setTimeout>[] = [];
  private interval: ReturnType<typeof setInterval> | null = null;
  private pendingRequest: Promise<StoredSession | null> | null = null;
  private receiveShare: ((session: StoredSession | null) => void) | null = null;
  /** Los id de los tokens cerrados en otra pestaña: una sesión compartida con uno de ellos se descarta. */
  private readonly closedTokenIds = new Set<string>();

  constructor(options: SessionManagerOptions) {
    this.queryClient = options.queryClient;
    this.storage = options.storage ?? window.sessionStorage;
    this.channel = options.channel ?? createBroadcastSessionChannel();
    this.requestTimeoutMs = options.requestTimeoutMs ?? SESSION_REQUEST_TIMEOUT_MS;
    this.logoutTimeoutMs = options.logoutTimeoutMs ?? LOGOUT_TIMEOUT_MS;

    // Una sesión guardada y vencida se borra sin llamar a la API.
    const stored = readSession(this.storage);

    if (stored !== null && isExpired(stored)) {
      clearSession(this.storage);
      this.snapshot = { ...this.snapshot, notice: MESSAGES.sessionExpired };
    } else if (stored !== null) {
      this.snapshot = { ...this.snapshot, session: stored };
    }

    setTokenProvider(() => this.snapshot.session?.token ?? null);
    setSessionErrorHandler((error, token) => {
      // Una respuesta tardía de un token que ya no es el de la sesión (se volvió a ingresar) no la cierra.
      if (token === this.snapshot.session?.token) {
        this.close(userMessage(error), 'token');
      }
    });
    this.unsubscribeChannel = this.channel.subscribe((message) => {
      this.onMessage(message);
    });

    if (typeof window !== 'undefined') {
      document.addEventListener('visibilitychange', this.onVisibilityChange);
      window.addEventListener('focus', this.resync);
      window.addEventListener('pageshow', this.resync);
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

  /**
   * Salir: primero cierra la sesión en todas las pestañas (aunque la API no responda) y después manda POST logout con el
   * token, sin esperarlo más de logoutTimeoutMs. La promesa termina cuando el POST termina (o se abandona); nunca falla.
   */
  logout(): Promise<void> {
    const session = this.snapshot.session;

    if (session === null) {
      return Promise.resolve();
    }

    this.close(null, 'all');

    return this.revoke(session.token);
  }

  dispose(): void {
    this.clearTimers();
    this.unsubscribeChannel();
    this.channel.close();
    this.receiveShare?.(null);

    if (typeof window !== 'undefined') {
      document.removeEventListener('visibilitychange', this.onVisibilityChange);
      window.removeEventListener('focus', this.resync);
      window.removeEventListener('pageshow', this.resync);
    }

    setTokenProvider(() => null);
    setSessionErrorHandler(() => undefined);
  }

  /**
   * Cierra la sesión de esta pestaña: borra el almacenamiento, los temporizadores y la caché de Query y avisa a las demás
   * pestañas según `publish`. `notice` es lo que muestra /ingresar.
   */
  private close(notice: string | null, publish: Publish): void {
    const session = this.snapshot.session;

    clearSession(this.storage);
    this.clearTimers();
    this.set({ session: null, warningMinutes: null, notice });

    if (publish !== 'none' && session !== null) {
      this.channel.post({
        type: 'logout',
        token_id: tokenId(session.token),
        notice,
        all: publish === 'all',
      });
    }

    this.queryClient.clear();
  }

  /** POST logout con `token`, con tiempo límite; sus errores no importan (la sesión local ya se cerró). */
  private revoke(token: string): Promise<void> {
    return api
      .post<null>('logout', undefined, { token, signal: AbortSignal.timeout(this.logoutTimeoutMs) })
      .then(
        () => undefined,
        () => undefined,
      );
  }

  private set(snapshot: SessionSnapshot): void {
    this.snapshot = snapshot;

    for (const listener of this.listeners) {
      listener();
    }
  }

  private expire(): void {
    this.close(MESSAGES.sessionExpired, 'token');
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

  /** Los mensajes llegan ya validados por el canal (isSessionMessage). */
  private onMessage(message: SessionMessage): void {
    const own = this.snapshot.session;

    switch (message.type) {
      case 'session-request':
        if (own !== null && !isExpired(own)) {
          this.channel.post({ type: 'session-share', session: own });
        }
        break;
      case 'session-share':
        // Una sesión de un token que otra pestaña ya cerró (el logout llegó antes que esta respuesta) no sirve.
        if (!this.closedTokenIds.has(tokenId(message.session.token))) {
          this.receiveShare?.(message.session);
        }
        break;
      case 'logout': {
        if (message.token_id !== '') {
          this.closedTokenIds.add(message.token_id);
        }

        if (own === null) {
          clearSession(this.storage);
          break;
        }

        const sameToken = tokenId(own.token) === message.token_id;

        // El vencimiento o el rechazo de otro token no cierran esta sesión; "Salir", sí.
        if (!sameToken && !message.all) {
          break;
        }

        this.close(message.notice, 'none');

        // Dos ingresos simultáneos y el usuario salió: esta pestaña también revoca su token en la API.
        if (!sameToken) {
          void this.revoke(own.token);
        }
        break;
      }
    }
  }

  private readonly onVisibilityChange = (): void => {
    if (document.visibilityState === 'visible') {
      this.resync();
    }
  };

  /**
   * Al volver a la pestaña (visibilitychange, focus, pageshow): los temporizadores se frenan con la PC suspendida. Se relee
   * el almacenamiento: si expires_at cambió (a mano, desde DevTools) se toma el nuevo; si no está o venció, se cierra.
   */
  private readonly resync = (): void => {
    const own = this.snapshot.session;

    if (own === null) {
      return;
    }

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

  /** El intervalo: compara la hora con expires_at; cierra si venció y, con el aviso previo, actualiza los minutos. */
  private readonly check = (): void => {
    const own = this.snapshot.session;
    const expiresAt = own === null ? null : expiresAtMs(own);

    if (expiresAt === null) {
      return;
    }

    const now = Date.now();

    if (expiresAt <= now) {
      this.expire();
    } else if (now >= expiresAt - WARNING_BEFORE_MS) {
      this.warn(expiresAt);
    }
  };

  /** Programa el aviso previo, el vencimiento y el intervalo de la sesión actual (o los aplica ya, si su hora pasó). */
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
    this.interval = setInterval(this.check, CHECK_INTERVAL_MS);
  }

  /** Enciende el aviso previo o actualiza sus minutos (los que quedan, redondeados para arriba; nunca menos de 1). */
  private warn(expiresAt: number): void {
    const minutes = Math.max(1, Math.ceil((expiresAt - Date.now()) / 60_000));

    if (this.snapshot.warningMinutes !== minutes) {
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

    if (this.interval !== null) {
      clearInterval(this.interval);
      this.interval = null;
    }
  }
}

function isExpired(session: StoredSession): boolean {
  const expiresAt = expiresAtMs(session);

  return expiresAt === null || expiresAt <= Date.now();
}
