import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { loadUserEdit } from '../../../../features/users/loader';
import { UserNotFound } from '../../../../features/users/UserDetailPage';
import { UserEditPage } from '../../../../features/users/UserFormPages';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/usuarios/$id/editar (con `$id_`, fuera del detalle): el usuario, los roles y las sucursales.
export const Route = createFileRoute('/_sesion/configuracion/usuarios/$id_/editar')({
  beforeLoad: requirePermission(PERMISSIONS.usersUpdate),
  loader: loadUserEdit,
  notFoundComponent: UserNotFound,
  component: UserEditRoute,
});

function UserEditRoute() {
  const { id } = Route.useLoaderData();

  return <UserEditPage key={id} id={id} />;
}
