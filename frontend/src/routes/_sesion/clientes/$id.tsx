import { createFileRoute, notFound } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { clientDetailQueryOptions } from '../../../features/clients/api';
import { ClientDetailPage, ClientNotFound } from '../../../features/clients/ClientDetailPage';
import { parseClientId } from '../../../features/clients/search';
import { errorTreatment, isApiError } from '../../../lib/api/errors';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /clientes/$id: un id que no es un entero positivo y el 404 de la API (inexistente o de otra clínica) son la página de no
// encontrado; un 403 FORBIDDEN, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con "Reintentar".
export const Route = createFileRoute('/_sesion/clientes/$id')({
  beforeLoad: requirePermission(PERMISSIONS.clientsShow),
  loader: async ({ context, params }) => {
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
  },
  notFoundComponent: ClientNotFound,
  component: ClientDetailRoute,
});

function ClientDetailRoute() {
  const { id } = Route.useLoaderData();

  return <ClientDetailPage id={id} />;
}
