import { createFileRoute } from '@tanstack/react-router';
import { ProfilePage } from '../../features/auth/ProfilePage';

// Sin permiso: todo usuario con sesión ve sus datos y cambia su contraseña.
export const Route = createFileRoute('/_sesion/perfil')({
  component: ProfilePage,
});
