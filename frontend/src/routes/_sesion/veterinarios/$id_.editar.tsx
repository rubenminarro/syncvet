import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { loadVeterinarian } from '../../../features/veterinarians/loader';
import { VeterinarianNotFound } from '../../../features/veterinarians/VeterinarianDetailPage';
import { VeterinarianEditPage } from '../../../features/veterinarians/VeterinarianFormPages';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /veterinarios/$id/editar (con `$id_`, fuera del detalle): solo el administrador.
export const Route = createFileRoute('/_sesion/veterinarios/$id_/editar')({
  beforeLoad: requirePermission(PERMISSIONS.veterinariansUpdate),
  loader: loadVeterinarian,
  notFoundComponent: VeterinarianNotFound,
  component: VeterinarianEditRoute,
});

function VeterinarianEditRoute() {
  const { id } = Route.useLoaderData();

  return <VeterinarianEditPage key={id} id={id} />;
}
