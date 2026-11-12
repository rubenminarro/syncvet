import { HttpResponse } from 'msw';
import type { ApiErrors, ApiMeta } from '../lib/api/types';

/** Las respuestas del contrato de la API (backend/CLAUDE.md §4), para los handlers de MSW. */

export function success(data: unknown, init: { status?: number; meta?: ApiMeta | null } = {}) {
  const status = init.status ?? 200;

  return HttpResponse.json(
    {
      success: true,
      status,
      message: 'Operación realizada correctamente.',
      data,
      errors: null,
      meta: init.meta ?? null,
      timestamp: '2026-11-12T12:00:00Z',
    },
    { status },
  );
}

export function failure(
  status: number,
  errorCode: string,
  message: string,
  init: { errors?: ApiErrors; headers?: Record<string, string> } = {},
) {
  return HttpResponse.json(
    {
      success: false,
      status,
      message,
      data: null,
      errors: init.errors ?? null,
      error_code: errorCode,
      timestamp: '2026-11-12T12:00:00Z',
    },
    { status, headers: init.headers ?? {} },
  );
}
