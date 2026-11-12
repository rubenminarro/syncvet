import type { ErrorComponentProps } from '@tanstack/react-router';
import { Button, ErrorPage } from '../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../lib/api/errors';
import { ForbiddenPage } from './ForbiddenPage';

/**
 * El error al cargar una página (defaultErrorComponent del router). La API manda: un 403 FORBIDDEN muestra la página de sin
 * permiso con el `message` de la API; otro error, la página de error con su mensaje y "Reintentar".
 */
export function RouteErrorPage({ error, reset }: ErrorComponentProps) {
  if (isApiError(error) && errorTreatment(error.errorCode) === 'forbidden') {
    return <ForbiddenPage message={error.message} />;
  }

  return (
    <ErrorPage
      title="No se pudo cargar esta página"
      message={isApiError(error) ? userMessage(error) : MESSAGES.programming}
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
  );
}
