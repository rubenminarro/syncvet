import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { loadUser } from '../../../../features/users/loader';
import { UserDetailPage, UserNotFound } from '../../../../features/users/UserDetailPage';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/usuarios/$id: un id inválido y el 404 de la API son la página de no encontrado, dentro del layout.
export const Route = createFileRoute('/_sesion/configuracion/usuarios/$id')({
  beforeLoad: requirePermission(PERMISSIONS.usersShow),
  loader: loadUser,
  notFoundComponent: UserNotFound,
  component: UserDetailRoute,
});

function UserDetailRoute() {
  const { id } = Route.useLoaderData();

  // key: otro id es otra pantalla (el estado local no pasa de un usuario a otro).
  return <UserDetailPage key={id} id={id} />;
}
