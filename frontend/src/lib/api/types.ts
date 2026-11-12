/**
 * El contrato común de la API (backend/CLAUDE.md §4). Los nombres de los campos son los de la API, en snake_case: no se
 * traducen a camelCase en el cliente.
 */

/** `meta.pagination` de los listados (entrega front-00 del backend, B3). */
export interface Pagination {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface ApiMeta {
  pagination?: Pagination;
  [key: string]: unknown;
}

/**
 * `errors` de un error: en un 422, cada campo con sus mensajes; algunos códigos de negocio traen otra forma (por ejemplo,
 * `errors.conflicts` de APPOINTMENT_SERIES_CONFLICT), por eso el valor es `unknown`.
 */
export type ApiErrors = Record<string, unknown>;

export interface ApiSuccess<T> {
  success: true;
  status: number;
  message: string;
  data: T;
  errors: null;
  meta: ApiMeta | null;
  timestamp: string;
}

export interface ApiFailure {
  success: false;
  status: number;
  message: string;
  data: null;
  errors: ApiErrors | null;
  error_code: string;
  timestamp?: string;
}

export type ApiResponse<T> = ApiSuccess<T> | ApiFailure;

/** Un listado paginado ya separado de la respuesta. */
export interface Page<T> {
  items: T[];
  pagination: Pagination;
}
