import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { server } from '../../test/server';
import { api, getPage, parseRetryAfter, setTokenProvider } from './client';
import { ApiError } from './errors';

async function caught(promise: Promise<unknown>): Promise<ApiError> {
  try {
    await promise;
  } catch (error) {
    expect(error).toBeInstanceOf(ApiError);
    return error as ApiError;
  }

  throw new Error('El pedido no falló.');
}

describe('client', () => {
  afterEach(() => {
    setTokenProvider(() => null);
  });

  it('manda Accept: application/json y el token, y devuelve la respuesta del contrato', async () => {
    let headers: Headers | undefined;
    server.use(
      http.get('/api/me', ({ request }) => {
        headers = request.headers;
        return success({ id: 1 });
      }),
    );
    setTokenProvider(() => '7|secreto');

    const response = await api.get<{ id: number }>('me');

    expect(response.data).toEqual({ id: 1 });
    expect(headers?.get('Accept')).toBe('application/json');
    expect(headers?.get('Authorization')).toBe('Bearer 7|secreto');
  });

  it('sin sesión no manda Authorization; el cuerpo va en JSON', async () => {
    let request: Request | undefined;
    server.use(
      http.post('/api/login', ({ request: received }) => {
        request = received.clone();
        return success({ token: 'x' });
      }),
    );

    await api.post('login', { email: 'a@b.com' });

    expect(request?.headers.get('Authorization')).toBeNull();
    expect(request?.headers.get('Content-Type')).toBe('application/json');
    expect(await request?.json()).toEqual({ email: 'a@b.com' });
  });

  async function expectContractError(status: number, errorCode: string, message: string) {
    server.use(http.get('/api/clients', () => failure(status, errorCode, message)));

    const error = await caught(api.get('clients'));

    expect(error).toMatchObject({ status, errorCode, message, errors: null, retryAfter: null });
  }

  it('convierte un 401 del contrato en ApiError', () =>
    expectContractError(401, 'UNAUTHENTICATED', 'No autenticado.'));
  it('convierte un 403 del contrato en ApiError', () =>
    expectContractError(403, 'FORBIDDEN', 'No tenés permiso para realizar esta acción.'));
  it('convierte un 404 del contrato en ApiError', () =>
    expectContractError(404, 'RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.'));
  it('convierte un 409 del contrato en ApiError', () =>
    expectContractError(409, 'CONFLICT', 'El turno cambió mientras lo guardabas.'));
  it('convierte un 413 del contrato en ApiError', () =>
    expectContractError(413, 'PAYLOAD_TOO_LARGE', 'El archivo es demasiado grande.'));
  it('convierte un 500 del contrato en ApiError', () =>
    expectContractError(500, 'INTERNAL_SERVER_ERROR', 'Error interno del servidor.'));

  it('un 422 conserva errors por campo', async () => {
    const errors = { email: ['El email ya está registrado.'], name: ['Ingresá el nombre.'] };
    server.use(
      http.post('/api/clients', () =>
        failure(422, 'VALIDATION_ERROR', 'Revisá los datos.', { errors }),
      ),
    );

    const error = await caught(api.post('clients', {}));

    expect(error).toMatchObject({ status: 422, errorCode: 'VALIDATION_ERROR', errors });
    expect(error.fieldErrors()).toEqual(errors);
  });

  it('un 413 con errors.photo lo conserva para el campo del archivo', async () => {
    server.use(
      http.post('/api/patients/1/photo', () =>
        failure(413, 'PAYLOAD_TOO_LARGE', 'La foto supera los 5 MB.', {
          errors: { photo: ['La foto supera los 5 MB.'] },
        }),
      ),
    );

    const error = await caught(api.post('patients/1/photo', new FormData()));

    expect(error.fieldErrors()).toEqual({ photo: ['La foto supera los 5 MB.'] });
  });

  it('un 429 lee Retry-After', async () => {
    server.use(
      http.post('/api/login', () =>
        failure(429, 'AUTH_TOO_MANY_ATTEMPTS', 'Demasiados intentos.', {
          headers: { 'Retry-After': '42' },
        }),
      ),
    );

    const error = await caught(api.post('login', {}));

    expect(error).toMatchObject({
      status: 429,
      errorCode: 'AUTH_TOO_MANY_ATTEMPTS',
      retryAfter: 42,
    });
  });

  it('sin respuesta del servidor es NETWORK_ERROR', async () => {
    server.use(http.get('/api/me', () => HttpResponse.error()));

    const error = await caught(api.get('me'));

    expect(error).toMatchObject({
      status: 0,
      errorCode: 'NETWORK_ERROR',
      message: 'No hay conexión con el servidor.',
    });
  });

  it('una respuesta fuera del contrato (el proxy sin backend) también es ApiError', async () => {
    server.use(http.get('/api/me', () => new HttpResponse('Bad Gateway', { status: 502 })));

    const error = await caught(api.get('me'));

    expect(error).toMatchObject({ status: 502, errorCode: 'INTERNAL_SERVER_ERROR' });
  });

  it('getPage lee meta.pagination en snake_case', async () => {
    server.use(
      http.get('/api/clients', ({ request }) => {
        expect(new URL(request.url).searchParams.get('page')).toBe('2');
        return success([{ id: 1 }], {
          meta: {
            pagination: { total: 31, per_page: 15, current_page: 2, last_page: 3 },
          },
        });
      }),
    );

    const page = await getPage<{ id: number }>('clients', { query: { page: 2, search: null } });

    expect(page).toEqual({
      items: [{ id: 1 }],
      pagination: { total: 31, per_page: 15, current_page: 2, last_page: 3 },
    });
  });

  it('parseRetryAfter acepta segundos o una fecha HTTP', () => {
    const now = Date.parse('2026-11-12T12:00:00Z');

    expect(parseRetryAfter('60', now)).toBe(60);
    expect(parseRetryAfter('Thu, 12 Nov 2026 12:00:30 GMT', now)).toBe(30);
    expect(parseRetryAfter(null, now)).toBeNull();
    expect(parseRetryAfter('cualquier cosa', now)).toBeNull();
  });
});
