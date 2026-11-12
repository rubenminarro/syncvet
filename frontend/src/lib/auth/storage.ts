/**
 * La sesión guardada en sessionStorage (`syncvet.session`): el token y su vencimiento. Nada en localStorage ni en cookies
 * (R-058): cada pestaña tiene su copia y una pestaña nueva la pide por el canal (channel.ts).
 */

export const SESSION_KEY = 'syncvet.session';

export interface StoredSession {
  token: string;
  /** El vencimiento del token, como lo manda la API (ISO 8601 en UTC). */
  expires_at: string;
}

/** El almacenamiento que usa la sesión: sessionStorage en el navegador; uno propio por "pestaña" en los tests. */
export type SessionStorageLike = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

/** Los milisegundos de `expires_at`, o null si no es una fecha. */
export function expiresAtMs(session: StoredSession): number | null {
  const ms = Date.parse(session.expires_at);

  return Number.isNaN(ms) ? null : ms;
}

/** Una sesión con un token y un vencimiento válidos (los datos de session-share se revisan igual que los guardados). */
export function isStoredSession(value: unknown): value is StoredSession {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const candidate = value as Partial<Record<keyof StoredSession, unknown>>;

  return (
    typeof candidate.token === 'string' &&
    candidate.token !== '' &&
    typeof candidate.expires_at === 'string' &&
    !Number.isNaN(Date.parse(candidate.expires_at))
  );
}

/** La sesión guardada, o null. Lo que no se entiende (JSON roto, sin token, sin vencimiento) se borra. */
export function readSession(storage: SessionStorageLike): StoredSession | null {
  let raw: string | null;

  try {
    raw = storage.getItem(SESSION_KEY);
  } catch {
    return null;
  }

  if (raw === null) {
    return null;
  }

  let value: unknown;

  try {
    value = JSON.parse(raw);
  } catch {
    value = null;
  }

  if (!isStoredSession(value)) {
    clearSession(storage);

    return null;
  }

  return { token: value.token, expires_at: value.expires_at };
}

export function saveSession(storage: SessionStorageLike, session: StoredSession): void {
  storage.setItem(
    SESSION_KEY,
    JSON.stringify({ token: session.token, expires_at: session.expires_at }),
  );
}

export function clearSession(storage: SessionStorageLike): void {
  try {
    storage.removeItem(SESSION_KEY);
  } catch {
    // Sin acceso al almacenamiento no hay nada que borrar.
  }
}

/** El id del token de Sanctum (`id|secreto`): la parte anterior a `|`, que no es secreta. Se publica en el canal. */
export function tokenId(token: string): string {
  const separator = token.indexOf('|');

  return separator === -1 ? '' : token.slice(0, separator);
}
