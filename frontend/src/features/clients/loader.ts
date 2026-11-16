import type { QueryClient } from '@tanstack/react-query';
import { notFound } from '@tanstack/react-router';
import { errorTreatment, isApiError } from '../../lib/api/errors';
import { clientDetailQueryOptions } from './api';
import { parseClientId } from './search';

/**
 * El `loader` de /clientes/$id y /clientes/$id/editar: un id que no es un entero positivo y el 404 de la API (inexistente o
 * de otra clínica) son `notFound()`; un 403 FORBIDDEN, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de
 * error con "Reintentar".
 */
export async function loadClient({
  context,
  params,
}: {
  context: { queryClient: QueryClient };
  params: { id: string };
}) {
  const id = parseClientId(params.id);

  if (id === null) {
    throw notFound();
  }

  try {
    // Lo que ya está en la caché se usa tal cual (la página lo vuelve a pedir si está viejo); si no, se espera el pedido.
    await context.queryClient.query({ ...clientDetailQueryOptions(id), staleTime: 'static' });
  } catch (error) {
    if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
      throw notFound();
    }

    throw error;
  }

  return { id };
}
