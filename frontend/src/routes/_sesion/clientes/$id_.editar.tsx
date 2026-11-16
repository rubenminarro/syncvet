import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { ClientNotFound } from '../../../features/clients/ClientDetailPage';
import { ClientEditPage } from '../../../features/clients/ClientFormPages';
import { loadClient } from '../../../features/clients/loader';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /clientes/$id/editar (con `$id_`, fuera del detalle: no se dibuja adentro de él). El mismo loader del detalle.
export const Route = createFileRoute('/_sesion/clientes/$id_/editar')({
  beforeLoad: requirePermission(PERMISSIONS.clientsUpdate),
  loader: loadClient,
  notFoundComponent: ClientNotFound,
  component: ClientEditRoute,
});

function ClientEditRoute() {
  const { id } = Route.useLoaderData();

  return <ClientEditPage id={id} />;
}
