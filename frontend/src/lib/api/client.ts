import { ApiError, MESSAGES, NETWORK_ERROR } from './errors';
import type { ApiFailure, ApiSuccess, Page } from './types';

/**
 * El cliente HTTP de la aplicación, sobre fetch. Toda llamada a la API pasa por acá: pone `Accept: application/json` y el
 * token (si hay sesión) y convierte cualquier falla en un ApiError. Ningún componente llama a fetch directo.
 */

export const API_BASE = '/api';

type QueryValue = string | number | boolean | null | undefined;

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  /** Un objeto se manda como JSON; un FormData, como multipart (sin Content-Type: lo pone el navegador). */
  body?: unknown;
  query?: Record<string, QueryValue>;
  signal?: AbortSignal;
}

type ReadOptions = Omit<RequestOptions, 'method' | 'body'>;

type TokenProvider = () => string | null;

let tokenProvider: TokenProvider = () => null;

/** La sesión (F0.2) registra de dónde sale el token. */
export function setTokenProvider(provider: TokenProvider): void {
  tokenProvider = provider;
}

function buildUrl(path: string, query?: Record<string, QueryValue>): string {
  const url = new URL(`${API_BASE}/${path.replace(/^\/+/, '')}`, window.location.origin);

  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== null && value !== undefined && value !== '') {
      url.searchParams.set(key, String(value));
    }
  }

  return url.toString();
}

/** `Retry-After` en segundos (también acepta una fecha HTTP); null si no viene o no se entiende. */
export function parseRetryAfter(header: string | null, now: number = Date.now()): number | null {
  const value = header?.trim() ?? '';

  if (value === '') {
    return null;
  }

  if (/^\d+$/.test(value)) {
    return Number(value);
  }

  const date = Date.parse(value);

  return Number.isNaN(date) ? null : Math.max(0, Math.ceil((date - now) / 1000));
}

function isFailure(body: unknown): body is ApiFailure {
  if (typeof body !== 'object' || body === null) {
    return false;
  }

  const candidate = body as Partial<Record<keyof ApiFailure, unknown>>;

  return (
    candidate.success === false &&
    typeof candidate.error_code === 'string' &&
    typeof candidate.message === 'string'
  );
}

/** El cuerpo de la respuesta: vacío (un 204), JSON o algo fuera del contrato (HTML de un proxy, JSON roto). */
type Body = { kind: 'empty' } | { kind: 'json'; value: unknown } | { kind: 'other' };

async function readBody(response: Response): Promise<Body> {
  let text: string;

  try {
    text = await response.text();
  } catch {
    return { kind: 'other' };
  }

  if (text.trim() === '') {
    return { kind: 'empty' };
  }

  if (!(response.headers.get('Content-Type') ?? '').includes('json')) {
    return { kind: 'other' };
  }

  try {
    return { kind: 'json', value: JSON.parse(text) as unknown };
  } catch {
    return { kind: 'other' };
  }
}

function unexpected(status: number): string {
  return `Respuesta inesperada del servidor (HTTP ${status}).`;
}

function toApiError(response: Response, body: unknown): ApiError {
  const retryAfter = parseRetryAfter(response.headers.get('Retry-After'));

  if (isFailure(body)) {
    return new ApiError({
      status: response.status,
      errorCode: body.error_code,
      message: body.message,
      errors: body.errors,
      retryAfter,
    });
  }

  // Una respuesta fuera del contrato: el proxy sin backend, una página de error del servidor web.
  return new ApiError({
    status: response.status,
    errorCode: response.status >= 500 ? 'INTERNAL_SERVER_ERROR' : 'HTTP_ERROR',
    message: unexpected(response.status),
    retryAfter,
  });
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<ApiSuccess<T>> {
  const headers = new Headers({ Accept: 'application/json' });
  const token = tokenProvider();
  let body: BodyInit | undefined;

  if (token !== null) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  if (options.body instanceof FormData) {
    body = options.body;
  } else if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json');
    body = JSON.stringify(options.body);
  }

  let response: Response;

  try {
    response = await fetch(buildUrl(path, options.query), {
      method: options.method ?? 'GET',
      headers,
      ...(body === undefined ? {} : { body }),
      ...(options.signal === undefined ? {} : { signal: options.signal }),
    });
  } catch (error) {
    // Una cancelación (TanStack Query, al desmontar) no es una falla de la red: se propaga tal cual. Se mira la señal y
    // no el tipo del error, porque fetch rechaza con el motivo de abort(), que puede no ser un DOMException.
    if (options.signal?.aborted === true) {
      throw error;
    }

    throw new ApiError({ status: 0, errorCode: NETWORK_ERROR, message: MESSAGES.network });
  }

  const received = await readBody(response);
  const json = received.kind === 'json' ? received.value : null;

  if (!response.ok || isFailure(json)) {
    throw toApiError(response, json);
  }

  // Un 204 (o un 2xx sin cuerpo) es un éxito sin datos: data = null. Quien llama a un endpoint así lo tipa con T = null.
  if (received.kind === 'empty') {
    return {
      success: true,
      status: response.status,
      message: '',
      data: null as T,
      errors: null,
      meta: null,
      timestamp: '',
    };
  }

  if (received.kind === 'other') {
    throw new ApiError({
      status: response.status,
      errorCode: 'HTTP_ERROR',
      message: unexpected(response.status),
    });
  }

  return json as ApiSuccess<T>;
}

export const api = {
  get: <T>(path: string, options: ReadOptions = {}) =>
    apiRequest<T>(path, { ...options, method: 'GET' }),
  post: <T>(path: string, body?: unknown, options: ReadOptions = {}) =>
    apiRequest<T>(path, { ...options, method: 'POST', body }),
  put: <T>(path: string, body?: unknown, options: ReadOptions = {}) =>
    apiRequest<T>(path, { ...options, method: 'PUT', body }),
  patch: <T>(path: string, body?: unknown, options: ReadOptions = {}) =>
    apiRequest<T>(path, { ...options, method: 'PATCH', body }),
  delete: <T>(path: string, options: ReadOptions = {}) =>
    apiRequest<T>(path, { ...options, method: 'DELETE' }),
};

/** Un listado paginado: los ítems y `meta.pagination` (per_page, current_page y last_page, en snake_case). */
export async function getPage<T>(path: string, options: ReadOptions = {}): Promise<Page<T>> {
  const response = await api.get<T[]>(path, options);
  const pagination = response.meta?.pagination;

  if (pagination === undefined) {
    throw new ApiError({
      status: response.status,
      errorCode: 'HTTP_ERROR',
      message: 'El listado no trae meta.pagination.',
    });
  }

  return { items: response.data, pagination };
}
