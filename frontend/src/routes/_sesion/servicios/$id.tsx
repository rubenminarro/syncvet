import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { loadService } from '../../../features/services/loader';
import { ServiceDetailPage, ServiceNotFound } from '../../../features/services/ServiceDetailPage';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /servicios/$id (los tres roles): un id inválido y el 404 de la API son la página de no encontrado.
export const Route = createFileRoute('/_sesion/servicios/$id')({
  beforeLoad: requirePermission(PERMISSIONS.servicesShow),
  loader: loadService,
  notFoundComponent: ServiceNotFound,
  component: ServiceDetailRoute,
});

function ServiceDetailRoute() {
  const { id } = Route.useLoaderData();

  // key: otro id es otra pantalla (el estado local no pasa de un servicio a otro).
  return <ServiceDetailPage key={id} id={id} />;
}
