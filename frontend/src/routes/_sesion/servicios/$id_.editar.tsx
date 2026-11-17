import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { loadService } from '../../../features/services/loader';
import { ServiceNotFound } from '../../../features/services/ServiceDetailPage';
import { ServiceEditPage } from '../../../features/services/ServiceFormPages';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /servicios/$id/editar (con `$id_`, fuera del detalle): solo el administrador.
export const Route = createFileRoute('/_sesion/servicios/$id_/editar')({
  beforeLoad: requirePermission(PERMISSIONS.servicesUpdate),
  loader: loadService,
  notFoundComponent: ServiceNotFound,
  component: ServiceEditRoute,
});

function ServiceEditRoute() {
  const { id } = Route.useLoaderData();

  return <ServiceEditPage key={id} id={id} />;
}
