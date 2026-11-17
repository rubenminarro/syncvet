import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { validateUserListSearch } from '../../../../features/users/search';
import { UserListPage } from '../../../../features/users/UserListPage';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/usuarios?buscar=&rol=&sucursal=&inactivos=&pagina= (2.2a): solo el administrador.
export const Route = createFileRoute('/_sesion/configuracion/usuarios/')({
  validateSearch: validateUserListSearch,
  beforeLoad: requirePermission(PERMISSIONS.usersIndex),
  component: UserListPage,
});
