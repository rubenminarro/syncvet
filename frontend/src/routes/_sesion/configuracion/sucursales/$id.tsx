import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { BranchDetailPage, BranchNotFound } from '../../../../features/branches/BranchDetailPage';
import { loadBranch } from '../../../../features/branches/loader';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/sucursales/$id: un id inválido y el 404 de la API son la página de no encontrado, dentro del layout.
export const Route = createFileRoute('/_sesion/configuracion/sucursales/$id')({
  beforeLoad: requirePermission(PERMISSIONS.branchesShow),
  loader: loadBranch,
  notFoundComponent: BranchNotFound,
  component: BranchDetailRoute,
});

function BranchDetailRoute() {
  const { id } = Route.useLoaderData();

  // key: otro id es otra pantalla (un diálogo abierto no pasa de una sucursal a otra).
  return <BranchDetailPage key={id} id={id} />;
}
