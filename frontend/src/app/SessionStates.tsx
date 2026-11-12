import type { ErrorComponentProps } from '@tanstack/react-router';
import { Button, ErrorPage, Loading } from '../components/ui';
import { isApiError, userMessage } from '../lib/api/errors';

/** Mientras se valida la sesión (al abrir o recargar): ni el formulario de ingreso ni el layout a medias. */
export function SessionLoading() {
  return (
    <div className="page page-center">
      <Loading label="Cargando tu sesión…" />
    </div>
  );
}

/** Una falla al cargar la sesión que no la termina (sin conexión, el servidor caído): se puede reintentar. */
export function SessionErrorPage({ error, reset }: ErrorComponentProps) {
  return (
    <div className="page page-center">
      <div className="container container-tight py-4">
        <ErrorPage
          title="No se pudo cargar tu sesión"
          message={isApiError(error) ? userMessage(error) : 'Ocurrió un error inesperado.'}
          action={
            <Button
              onClick={() => {
                reset();
              }}
            >
              Reintentar
            </Button>
          }
        />
      </div>
    </div>
  );
}
