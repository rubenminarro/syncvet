import type { QueryClient } from '@tanstack/react-query';
import { notFound } from '@tanstack/react-router';
import { errorTreatment, isApiError } from '../../lib/api/errors';
import { branchOptionsQueryOptions } from '../branches/api';
import { rolesQueryOptions, userDetailQueryOptions } from './api';
import { parseUserId } from './search';

interface LoaderArgs {
  context: { queryClient: QueryClient };
}

/** Los roles y las sucursales del formulario (el alta y la edición): sin ellos, el formulario no se dibuja. */
async function loadFormOptions(queryClient: QueryClient) {
  await Promise.all([
    queryClient.query({ ...rolesQueryOptions(), staleTime: 'static' }),
    queryClient.query({ ...branchOptionsQueryOptions(), staleTime: 0 }),
  ]);
}

/**
 * El `loader` de /configuracion/usuarios/$id: un id que no es un entero positivo y el 404 de la API son `notFound()`; un
 * 403, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con "Reintentar".
 */
export async function loadUser({ context, params }: LoaderArgs & { params: { id: string } }) {
  const id = parseUserId(params.id);

  if (id === null) {
    throw notFound();
  }

  try {
    await context.queryClient.query({ ...userDetailQueryOptions(id), staleTime: 'static' });
  } catch (error) {
    if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
      throw notFound();
    }

    throw error;
  }

  return { id };
}

/** El `loader` de /configuracion/usuarios/$id/editar: el usuario, los roles y las sucursales (las activas, para elegir). */
export async function loadUserEdit(args: LoaderArgs & { params: { id: string } }) {
  const [result] = await Promise.all([loadUser(args), loadFormOptions(args.context.queryClient)]);

  return result;
}

/** El `loader` de /configuracion/usuarios/nuevo: los roles y las sucursales. */
export async function loadUserCreate({ context }: LoaderArgs) {
  await loadFormOptions(context.queryClient);
}
