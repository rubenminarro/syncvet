import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { ClientDetailPage, ClientNotFound } from '../../../features/clients/ClientDetailPage';
import { loadClient } from '../../../features/clients/loader';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /clientes/$id: un id que no es un entero positivo y el 404 de la API (inexistente o de otra clínica) son la página de no
// encontrado; un 403 FORBIDDEN, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con "Reintentar".
export const Route = createFileRoute('/_sesion/clientes/$id')({
  beforeLoad: requirePermission(PERMISSIONS.clientsShow),
  loader: loadClient,
  notFoundComponent: ClientNotFound,
  component: ClientDetailRoute,
});

function ClientDetailRoute() {
  const { id } = Route.useLoaderData();

  return <ClientDetailPage id={id} />;
}
