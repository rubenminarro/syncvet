import type { QueryClient } from '@tanstack/react-query';
import { notFound } from '@tanstack/react-router';
import { errorTreatment, isApiError } from '../../lib/api/errors';
import { patientDetailQueryOptions } from './api';
import { parsePatientId } from './search';

/**
 * El `loader` de /pacientes/$id y /pacientes/$id/editar (como el de los clientes): un id que no es un entero positivo y el
 * 404 de la API (inexistente o de otra clínica) son `notFound()`; un 403 FORBIDDEN, la de sin permiso (RouteErrorPage); una
 * falla de red o un 5xx, la de error con "Reintentar".
 */
export async function loadPatient({
  context,
  params,
}: {
  context: { queryClient: QueryClient };
  params: { id: string };
}) {
  const id = parsePatientId(params.id);

  if (id === null) {
    throw notFound();
  }

  try {
    await context.queryClient.query({ ...patientDetailQueryOptions(id), staleTime: 'static' });
  } catch (error) {
    if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
      throw notFound();
    }

    throw error;
  }

  return { id };
}
