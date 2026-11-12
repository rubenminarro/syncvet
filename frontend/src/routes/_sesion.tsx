import { createFileRoute, redirect } from '@tanstack/react-router';
import { SessionErrorPage, SessionLoading } from '../app/SessionStates';
import { SessionLayout } from '../app/SessionLayout';
import { meQueryOptions } from '../features/auth/api';

// Layout con sesión (el "_" no forma parte de la URL): exige una sesión válida, la propia o la de otra pestaña, y `me`.
export const Route = createFileRoute('/_sesion')({
  beforeLoad: async ({ context, location }) => {
    const toLogin = () =>
      redirect({
        to: '/ingresar',
        search: location.href === '/' ? {} : { volver: location.href },
      });

    if (!(await context.session.ensureSession())) {
      throw toLogin();
    }

    try {
      await context.queryClient.query(meQueryOptions());
    } catch (error) {
      // Un error de sesión (401, USER_INACTIVE, CLINIC_NOT_AVAILABLE) ya la cerró: el cliente le avisó al SessionManager,
      // que además limpió la caché (por eso el error que llega acá puede ser la cancelación de la consulta).
      if (context.session.getSnapshot().session === null) {
        throw toLogin();
      }

      throw error;
    }
  },
  // Mientras se pide la sesión a otra pestaña o se carga `me`: el estado de carga, nunca el formulario de ingreso.
  pendingComponent: SessionLoading,
  pendingMs: 0,
  errorComponent: SessionErrorPage,
  component: SessionLayout,
});
