import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { validateVeterinarianListSearch } from '../../../features/veterinarians/search';
import { VeterinarianListPage } from '../../../features/veterinarians/VeterinarianListPage';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /veterinarios?buscar=&estado=&sucursal=&pagina= (2.3): los tres roles.
export const Route = createFileRoute('/_sesion/veterinarios/')({
  validateSearch: validateVeterinarianListSearch,
  beforeLoad: requirePermission(PERMISSIONS.veterinariansIndex),
  component: VeterinarianListPage,
});
