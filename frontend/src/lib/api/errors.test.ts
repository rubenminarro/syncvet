import { describe, expect, it } from 'vitest';
import { ApiError, errorTreatment, isRetryable, MESSAGES, userMessage } from './errors';

function error(errorCode: string, status: number, message = 'Mensaje de la API.') {
  return new ApiError({ status, errorCode, message });
}

describe('la tabla de error_code', () => {
  // Un caso por fila de la tabla de f0-base.md.
  it('UNAUTHENTICATED cierra la sesión', () => {
    expect(errorTreatment('UNAUTHENTICATED')).toBe('session-expired');
  });

  it('USER_INACTIVE', () => {
    expect(errorTreatment('USER_INACTIVE')).toBe('user-inactive');
  });

  it('CLINIC_NOT_AVAILABLE', () => {
    expect(errorTreatment('CLINIC_NOT_AVAILABLE')).toBe('clinic-unavailable');
  });

  it('FORBIDDEN', () => {
    expect(errorTreatment('FORBIDDEN')).toBe('forbidden');
  });

  it('RESOURCE_NOT_FOUND', () => {
    expect(errorTreatment('RESOURCE_NOT_FOUND')).toBe('not-found');
  });

  it('VALIDATION_ERROR va a los campos', () => {
    expect(errorTreatment('VALIDATION_ERROR')).toBe('validation');
  });

  it('INVALID_CREDENTIALS', () => {
    expect(errorTreatment('INVALID_CREDENTIALS')).toBe('invalid-credentials');
  });

  it('AUTH_TOO_MANY_ATTEMPTS y TOO_MANY_REQUESTS esperan Retry-After', () => {
    expect(errorTreatment('AUTH_TOO_MANY_ATTEMPTS')).toBe('throttled');
    expect(errorTreatment('TOO_MANY_REQUESTS')).toBe('throttled');
  });

  it('CONFLICT', () => {
    expect(errorTreatment('CONFLICT')).toBe('conflict');
  });

  it('PAYLOAD_TOO_LARGE', () => {
    expect(errorTreatment('PAYLOAD_TOO_LARGE')).toBe('payload-too-large');
  });

  it('los códigos de negocio muestran el message', () => {
    for (const code of ['CLIENT_IN_USE', 'APPOINTMENT_OVERLAP', 'USER_LAST_ADMIN']) {
      expect(errorTreatment(code), code).toBe('business');
    }
  });

  it('INTERNAL_SERVER_ERROR y SERVICE_UNAVAILABLE', () => {
    expect(errorTreatment('INTERNAL_SERVER_ERROR')).toBe('server');
    expect(errorTreatment('SERVICE_UNAVAILABLE')).toBe('server');
  });

  it('NETWORK_ERROR (propio del frontend)', () => {
    expect(errorTreatment('NETWORK_ERROR')).toBe('network');
  });

  it('los errores de programación', () => {
    for (const code of [
      'BAD_REQUEST',
      'METHOD_NOT_ALLOWED',
      'NOT_ACCEPTABLE',
      'UNSUPPORTED_MEDIA_TYPE',
      'HTTP_ERROR',
    ]) {
      expect(errorTreatment(code), code).toBe('programming');
    }
  });

  it('un código que la tabla no conoce se trata como uno de negocio', () => {
    expect(errorTreatment('CODIGO_QUE_NO_EXISTE')).toBe('business');
    // Ni una propiedad heredada de Object cuenta como fila de la tabla.
    expect(errorTreatment('toString')).toBe('business');
  });

  it('muestra el message de la API tal cual, salvo sesión, servidor, red y programación', () => {
    expect(userMessage(error('CLIENT_IN_USE', 409, 'El cliente tiene pacientes.'))).toBe(
      'El cliente tiene pacientes.',
    );
    expect(userMessage(error('INVALID_CREDENTIALS', 422, 'Credenciales incorrectas.'))).toBe(
      'Credenciales incorrectas.',
    );
    expect(userMessage(error('UNAUTHENTICATED', 401))).toBe(MESSAGES.sessionExpired);
    expect(userMessage(error('INTERNAL_SERVER_ERROR', 500))).toBe(MESSAGES.server);
    expect(userMessage(error('NETWORK_ERROR', 0))).toBe(MESSAGES.network);
    expect(userMessage(error('METHOD_NOT_ALLOWED', 405))).toBe(MESSAGES.programming);
    expect(userMessage(error('CONFLICT', 409, 'El turno cambió.'))).toBe(
      `${MESSAGES.conflict} El turno cambió.`,
    );
  });

  it('solo la red y los 5xx se pueden reintentar', () => {
    expect(isRetryable(error('NETWORK_ERROR', 0))).toBe(true);
    expect(isRetryable(error('INTERNAL_SERVER_ERROR', 500))).toBe(true);
    expect(isRetryable(error('SERVICE_UNAVAILABLE', 503))).toBe(true);
    expect(isRetryable(error('FORBIDDEN', 403))).toBe(false);
    expect(isRetryable(error('VALIDATION_ERROR', 422))).toBe(false);
    expect(isRetryable(error('TOO_MANY_REQUESTS', 429))).toBe(false);
    expect(isRetryable(new Error('otro'))).toBe(false);
  });
});
