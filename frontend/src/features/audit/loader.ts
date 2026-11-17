import type { QueryClient } from '@tanstack/react-query';
import { notFound } from '@tanstack/react-router';
import { errorTreatment, isApiError } from '../../lib/api/errors';
import { auditLogDetailQueryOptions } from './api';
import { parseAuditLogId } from './search';

/**
 * El `loader` de /configuracion/auditoria/$id: un id que no es un entero positivo y el 404 de la API (otra clínica, uno que no
 * existe) son `notFound()`; un 403, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con
 * "Reintentar". Se pide siempre (`staleTime` de 0: es de solo lectura, pero nada la invalida).
 */
export async function loadAuditLog({
  context,
  params,
}: {
  context: { queryClient: QueryClient };
  params: { id: string };
}) {
  const id = parseAuditLogId(params.id);

  if (id === null) {
    throw notFound();
  }

  try {
    await context.queryClient.query({ ...auditLogDetailQueryOptions(id), staleTime: 0 });
  } catch (error) {
    if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
      throw notFound();
    }

    throw error;
  }

  return { id };
}
