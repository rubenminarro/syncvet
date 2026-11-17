import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { BranchNotFound } from '../../../../features/branches/BranchDetailPage';
import { BranchEditPage } from '../../../../features/branches/BranchFormPages';
import { loadBranch } from '../../../../features/branches/loader';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/sucursales/$id/editar (con `$id_`, fuera del detalle). El mismo loader del detalle.
export const Route = createFileRoute('/_sesion/configuracion/sucursales/$id_/editar')({
  beforeLoad: requirePermission(PERMISSIONS.branchesUpdate),
  loader: loadBranch,
  notFoundComponent: BranchNotFound,
  component: BranchEditRoute,
});

function BranchEditRoute() {
  const { id } = Route.useLoaderData();

  return <BranchEditPage key={id} id={id} />;
}
