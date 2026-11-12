import { Link } from '@tanstack/react-router';
import { ErrorPage } from '../components/ui';

export function NotFoundPage() {
  return (
    <div className="page page-center">
      <div className="container container-tight py-4">
        <ErrorPage
          code="404"
          title="No encontramos esta página"
          message="La dirección no existe o cambió. Revisala o volvé al inicio."
          action={
            <Link to="/" className="btn btn-primary">
              Volver al inicio
            </Link>
          }
        />
      </div>
    </div>
  );
}
