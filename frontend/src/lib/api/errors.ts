import type { ApiErrors } from './types';

/** El único código propio del frontend: el pedido no tuvo respuesta del servidor. */
export const NETWORK_ERROR = 'NETWORK_ERROR';

/** Un error de la API, con los datos del contrato. Toda falla de `client.ts` termina en uno. */
export class ApiError extends Error {
  override readonly name = 'ApiError';
  readonly status: number;
  readonly errorCode: string;
  readonly errors: ApiErrors | null;
  /** Segundos de `Retry-After` (429), o null. */
  readonly retryAfter: number | null;

  constructor(init: {
    status: number;
    errorCode: string;
    message: string;
    errors?: ApiErrors | null;
    retryAfter?: number | null;
  }) {
    super(init.message);
    this.status = init.status;
    this.errorCode = init.errorCode;
    this.errors = init.errors ?? null;
    this.retryAfter = init.retryAfter ?? null;
  }

  /** Los mensajes de cada campo de `errors` (los valores que no son una lista de textos quedan afuera). */
  fieldErrors(): Record<string, string[]> {
    const fields: Record<string, string[]> = {};

    for (const [field, value] of Object.entries(this.errors ?? {})) {
      if (Array.isArray(value) && value.every((item) => typeof item === 'string')) {
        fields[field] = value;
      }
    }

    return fields;
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

/**
 * Qué hace la interfaz con cada error_code (docs/03-plan-desarrollo/fases/frontend/f0-base.md). Se decide por el código,
 * nunca por el texto ni solo por el HTTP.
 */
export type ErrorTreatment =
  /** Cierra la sesión en todas las pestañas y lleva a /ingresar?volver=… con el aviso de sesión vencida. */
  | 'session-expired'
  /** En el login, el mensaje en el formulario; con sesión, como session-expired pero con el mensaje de la API. */
  | 'user-inactive'
  /** En el login, el mensaje en el formulario; con sesión, borra la sesión y muestra el mensaje en /ingresar. */
  | 'clinic-unavailable'
  /** Al cargar una página, /sin-permiso; en una acción, un aviso con el mensaje. */
  | 'forbidden'
  /** Al cargar un detalle, la página de no encontrado; en una acción, un aviso e invalidar la lista. */
  | 'not-found'
  /** Cada clave de `errors` a su campo; las demás, a una alerta arriba del formulario. */
  | 'validation'
  /** Mensaje en el formulario de ingreso; se borra la contraseña. */
  | 'invalid-credentials'
  /** Mensaje y el botón deshabilitado con la cuenta regresiva de Retry-After. */
  | 'throttled'
  /** Aviso de datos cambiados con el mensaje; se vuelven a pedir los datos. */
  | 'conflict'
  /** El error en el campo del archivo; sin `errors`, un aviso. */
  | 'payload-too-large'
  /** Aviso genérico de falla del servidor; una lectura se reintenta una vez. */
  | 'server'
  /** Aviso de falta de conexión; una lectura se reintenta una vez. */
  | 'network'
  /** Error de programación: aviso genérico y el detalle en la consola en desarrollo. */
  | 'programming'
  /** Código de negocio (o desconocido): alerta con el mensaje de la API. */
  | 'business';

export const ERROR_TREATMENTS: Readonly<Record<string, ErrorTreatment>> = {
  UNAUTHENTICATED: 'session-expired',
  USER_INACTIVE: 'user-inactive',
  CLINIC_NOT_AVAILABLE: 'clinic-unavailable',
  FORBIDDEN: 'forbidden',
  RESOURCE_NOT_FOUND: 'not-found',
  VALIDATION_ERROR: 'validation',
  INVALID_CREDENTIALS: 'invalid-credentials',
  AUTH_TOO_MANY_ATTEMPTS: 'throttled',
  TOO_MANY_REQUESTS: 'throttled',
  CONFLICT: 'conflict',
  PAYLOAD_TOO_LARGE: 'payload-too-large',
  INTERNAL_SERVER_ERROR: 'server',
  SERVICE_UNAVAILABLE: 'server',
  [NETWORK_ERROR]: 'network',
  BAD_REQUEST: 'programming',
  METHOD_NOT_ALLOWED: 'programming',
  NOT_ACCEPTABLE: 'programming',
  UNSUPPORTED_MEDIA_TYPE: 'programming',
  HTTP_ERROR: 'programming',
};

/** El trato de un error_code; un código que la tabla no conoce se trata como uno de negocio. */
export function errorTreatment(errorCode: string): ErrorTreatment {
  return Object.hasOwn(ERROR_TREATMENTS, errorCode)
    ? (ERROR_TREATMENTS[errorCode] ?? 'business')
    : 'business';
}

export const MESSAGES = {
  sessionExpired: 'Tu sesión venció. Ingresá de nuevo.',
  server: 'No se pudo completar. Probá de nuevo en unos minutos.',
  network: 'No hay conexión con el servidor.',
  conflict: 'Alguien cambió estos datos mientras guardabas. Revisalos y probá de nuevo.',
  programming: 'No se pudo completar el pedido. Si se repite, avisá al administrador.',
} as const;

/**
 * El texto que ve el usuario. Por regla se muestra el `message` de la API tal cual (ya viene en español y con voseo); solo
 * la sesión vencida y los errores del servidor, de la red y de programación usan un texto propio.
 */
export function userMessage(error: ApiError): string {
  switch (errorTreatment(error.errorCode)) {
    case 'session-expired':
      return MESSAGES.sessionExpired;
    case 'server':
      return MESSAGES.server;
    case 'network':
      return MESSAGES.network;
    case 'programming':
      return MESSAGES.programming;
    case 'conflict':
      return `${MESSAGES.conflict} ${error.message}`;
    default:
      return error.message;
  }
}

/** Solo una lectura que falló por la red o con un 5xx se reintenta (una vez); nunca con un 4xx. */
export function isRetryable(error: unknown): boolean {
  if (!isApiError(error)) {
    return false;
  }

  return error.errorCode === NETWORK_ERROR || error.status >= 500;
}
