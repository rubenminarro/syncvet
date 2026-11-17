import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { BranchListPage } from '../../../../features/branches/BranchListPage';
import { validateBranchListSearch } from '../../../../features/branches/search';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/sucursales?buscar=&inactivas=&pagina= (2.1): solo el administrador (branches.show; branches.index lo
// tienen los tres roles, pero la pantalla es de la administración, pregunta 4 del diseño de la F2).
export const Route = createFileRoute('/_sesion/configuracion/sucursales/')({
  validateSearch: validateBranchListSearch,
  beforeLoad: requirePermission(PERMISSIONS.branchesShow),
  component: BranchListPage,
});
