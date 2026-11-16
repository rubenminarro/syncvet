import { describe, expect, it, vi } from 'vitest';
import { dialogError } from './dialog-error';
import { ApiError, MESSAGES } from './errors';
import type { ApiErrors } from './types';

function error(
  errorCode: string,
  status: number,
  message = 'Mensaje de la API.',
  errors?: ApiErrors,
) {
  return new ApiError({ status, errorCode, message, errors: errors ?? null });
}

describe('dialogError', () => {
  it('un 404 llama a onGone con el mensaje y no deja error en el diálogo', () => {
    const onGone = vi.fn();

    expect(dialogError(error('RESOURCE_NOT_FOUND', 404, 'No existe.'), onGone)).toBeNull();
    expect(onGone).toHaveBeenCalledWith('No existe.');
  });

  it('un 422 junta los mensajes de sus campos y queda solo "Cerrar"', () => {
    const failure = dialogError(
      error('VALIDATION_ERROR', 422, 'Los datos no son válidos.', {
        is_default: ['Primero.'],
        branch: ['Segundo.'],
      }),
      vi.fn(),
    );

    expect(failure).toEqual({ message: 'Primero. Segundo.', final: true });
  });

  it('un 422 sin errores de campo muestra el mensaje general', () => {
    expect(
      dialogError(error('VALIDATION_ERROR', 422, 'Los datos no son válidos.'), vi.fn()),
    ).toEqual({ message: 'Los datos no son válidos.', final: true });
  });

  it('un error de negocio (también uno que la tabla no conoce) muestra el mensaje y queda solo "Cerrar"', () => {
    expect(dialogError(error('USER_LAST_ADMIN', 409, 'Último.'), vi.fn())).toEqual({
      message: 'Último.',
      final: true,
    });
    expect(dialogError(error('ALGO_NUEVO', 409, 'Nuevo.'), vi.fn())).toEqual({
      message: 'Nuevo.',
      final: true,
    });
  });

  it.each([
    ['INTERNAL_SERVER_ERROR', 500, MESSAGES.server],
    ['NETWORK_ERROR', 0, MESSAGES.network],
    ['FORBIDDEN', 403, 'Mensaje de la API.'],
  ])('%s deja probar de nuevo', (code, status, message) => {
    expect(dialogError(error(code, status), vi.fn())).toEqual({ message, final: false });
  });

  it('algo que no es un ApiError es un error de programación y deja probar de nuevo', () => {
    expect(dialogError(new Error('x'), vi.fn())).toEqual({
      message: MESSAGES.programming,
      final: false,
    });
  });
});
