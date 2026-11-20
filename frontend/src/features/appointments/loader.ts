import type { QueryClient } from '@tanstack/react-query';
import { notFound } from '@tanstack/react-router';
import { errorTreatment, isApiError } from '../../lib/api/errors';
import { parseRouteId } from '../../lib/search-text';
import { appointmentDetailQueryOptions } from './api';

interface LoaderArgs {
  context: { queryClient: QueryClient };
  params: { id: string };
}

/**
 * El `loader` de /turnos/$id y de su edición (3.4): un id que no es un entero positivo y el 404 de la API (inexistente o de
 * otra clínica) son `notFound()`; un 403, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con
 * "Reintentar". El detalle se vuelve a pedir al entrar (otra PC pudo moverlo o fichar el turno).
 */
export async function loadAppointment({ context, params }: LoaderArgs) {
  const id = parseRouteId(params.id);

  if (id === null) {
    throw notFound();
  }

  try {
    await context.queryClient.query({ ...appointmentDetailQueryOptions(id), staleTime: 0 });
  } catch (error) {
    if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
      throw notFound();
    }

    throw error;
  }

  return { id };
}
