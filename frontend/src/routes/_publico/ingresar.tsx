import { createFileRoute, redirect } from '@tanstack/react-router';
import { LoginPage } from '../../features/auth/LoginPage';

interface LoginSearch {
  /** La ruta pedida antes de ingresar; se revisa con safeRedirect antes de usarla. */
  volver?: string;
}

export const Route = createFileRoute('/_publico/ingresar')({
  validateSearch: (search: Record<string, unknown>): LoginSearch =>
    typeof search.volver === 'string' && search.volver !== '' ? { volver: search.volver } : {},
  // Con una sesión ya abierta en esta pestaña, al inicio.
  beforeLoad: ({ context }) => {
    if (context.session.getSnapshot().session !== null) {
      throw redirect({ to: '/' });
    }
  },
  component: LoginPage,
});
