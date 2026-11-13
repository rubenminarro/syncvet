import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { ClientListPage } from '../../../features/clients/ClientListPage';
import { validateClientListSearch } from '../../../features/clients/search';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /clientes?buscar=&tipo=&pagina=: la búsqueda va en la URL; un valor inválido vuelve al valor por defecto.
export const Route = createFileRoute('/_sesion/clientes/')({
  validateSearch: validateClientListSearch,
  beforeLoad: requirePermission(PERMISSIONS.clientsIndex),
  component: ClientListPage,
});
