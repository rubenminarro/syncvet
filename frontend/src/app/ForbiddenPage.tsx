import { Link } from '@tanstack/react-router';
import { ErrorPage } from '../components/ui';

export function ForbiddenPage() {
  return (
    <ErrorPage
      code="403"
      title="No tenés permiso para ver esta página"
      message="Si lo necesitás, pedíselo al administrador de la clínica."
      action={
        <Link to="/" className="btn btn-primary">
          Volver al inicio
        </Link>
      }
    />
  );
}
