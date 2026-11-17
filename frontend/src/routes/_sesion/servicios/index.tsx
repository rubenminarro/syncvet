import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { validateServiceListSearch } from '../../../features/services/search';
import { ServiceListPage } from '../../../features/services/ServiceListPage';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /servicios?buscar=&categoria=&estado=&pagina= (2.4): los tres roles.
export const Route = createFileRoute('/_sesion/servicios/')({
  validateSearch: validateServiceListSearch,
  beforeLoad: requirePermission(PERMISSIONS.servicesIndex),
  component: ServiceListPage,
});
