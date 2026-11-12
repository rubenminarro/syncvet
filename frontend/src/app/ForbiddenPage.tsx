import { Link } from '@tanstack/react-router';
import { ErrorPage } from '../components/ui';

const DEFAULT_MESSAGE = 'Si lo necesitás, pedíselo al administrador de la clínica.';

/** Sin permiso: la ruta lo declara (requirePermission) o la API respondió 403 FORBIDDEN (`message`, el de la API). */
export function ForbiddenPage({ message }: { message?: string }) {
  return (
    <ErrorPage
      code="403"
      title="No tenés permiso para ver esta página"
      message={message ?? DEFAULT_MESSAGE}
      action={
        <Link to="/" className="btn btn-primary">
          Volver al inicio
        </Link>
      }
    />
  );
}
