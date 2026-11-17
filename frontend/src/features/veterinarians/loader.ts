import type { QueryClient } from '@tanstack/react-query';
import { notFound } from '@tanstack/react-router';
import { errorTreatment, isApiError } from '../../lib/api/errors';
import { userOptionsQueryOptions } from '../users/api';
import { veterinarianDetailQueryOptions } from './api';
import { parseVeterinarianId } from './search';

interface LoaderArgs {
  context: { queryClient: QueryClient };
}

/** Los usuarios que se pueden elegir en el alta: los activos con el rol veterinario (se descartan los que tienen ficha). */
export const VETERINARIAN_USER_OPTIONS = { role: 'veterinario', include_inactive: false } as const;

/**
 * El `loader` de /veterinarios/$id: un id que no es un entero positivo y el 404 de la API son `notFound()`; un 403, la de
 * sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con "Reintentar".
 */
export async function loadVeterinarian({
  context,
  params,
}: LoaderArgs & { params: { id: string } }) {
  const id = parseVeterinarianId(params.id);

  if (id === null) {
    throw notFound();
  }

  try {
    await context.queryClient.query({ ...veterinarianDetailQueryOptions(id), staleTime: 'static' });
  } catch (error) {
    if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
      throw notFound();
    }

    throw error;
  }

  return { id };
}

/** El `loader` de /veterinarios/nuevo: los usuarios con el rol veterinario, siempre al día (quién tiene ficha cambia). */
export async function loadVeterinarianCreate({ context }: LoaderArgs) {
  await context.queryClient.query({
    ...userOptionsQueryOptions(VETERINARIAN_USER_OPTIONS),
    staleTime: 0,
  });
}
