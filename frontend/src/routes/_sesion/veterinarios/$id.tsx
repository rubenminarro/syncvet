import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { loadVeterinarian } from '../../../features/veterinarians/loader';
import {
  VeterinarianDetailPage,
  VeterinarianNotFound,
} from '../../../features/veterinarians/VeterinarianDetailPage';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /veterinarios/$id (solo el administrador): un id inválido y el 404 de la API son la página de no encontrado.
export const Route = createFileRoute('/_sesion/veterinarios/$id')({
  beforeLoad: requirePermission(PERMISSIONS.veterinariansShow),
  loader: loadVeterinarian,
  notFoundComponent: VeterinarianNotFound,
  component: VeterinarianDetailRoute,
});

function VeterinarianDetailRoute() {
  const { id } = Route.useLoaderData();

  // key: otro id es otra pantalla (el estado local no pasa de una ficha a otra).
  return <VeterinarianDetailPage key={id} id={id} />;
}
