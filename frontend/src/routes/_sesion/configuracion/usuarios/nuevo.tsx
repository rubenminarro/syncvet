import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { loadUserCreate } from '../../../../features/users/loader';
import { UserCreatePage } from '../../../../features/users/UserFormPages';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/usuarios/nuevo: el alta (gana sobre $id). El loader trae los roles y las sucursales del formulario.
export const Route = createFileRoute('/_sesion/configuracion/usuarios/nuevo')({
  beforeLoad: requirePermission(PERMISSIONS.usersStore),
  loader: loadUserCreate,
  component: UserCreatePage,
});
