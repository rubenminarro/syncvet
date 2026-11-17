import type { QueryClient } from '@tanstack/react-query';
import { notFound } from '@tanstack/react-router';
import { errorTreatment, isApiError } from '../../lib/api/errors';
import { branchDetailQueryOptions } from './api';
import { parseBranchId } from './search';

/**
 * El `loader` de /configuracion/sucursales/$id y de su edición: un id que no es un entero positivo y el 404 de la API son
 * `notFound()`; un 403, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con "Reintentar".
 */
export async function loadBranch({
  context,
  params,
}: {
  context: { queryClient: QueryClient };
  params: { id: string };
}) {
  const id = parseBranchId(params.id);

  if (id === null) {
    throw notFound();
  }

  try {
    await context.queryClient.query({ ...branchDetailQueryOptions(id), staleTime: 'static' });
  } catch (error) {
    if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
      throw notFound();
    }

    throw error;
  }

  return { id };
}
