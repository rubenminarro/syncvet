import { useRouter, type ErrorComponentProps } from '@tanstack/react-router';
import { Button, ErrorPage } from '../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../lib/api/errors';
import { ForbiddenPage } from './ForbiddenPage';

/**
 * El error al cargar una página (defaultErrorComponent del router). La API manda: un 403 FORBIDDEN muestra la página de sin
 * permiso con el `message` de la API (decide el error_code, no el HTTP: un 403 de negocio, como APPOINTMENT_NOT_YOURS, es
 * otro error); otro error, la página de error con su mensaje y "Reintentar", que vuelve a cargar la ruta (invalida el
 * router: sin eso, el error de un loader volvería a mostrarse).
 */
export function RouteErrorPage({ error, reset }: ErrorComponentProps) {
  const router = useRouter();

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
            void router.invalidate();
          }}
        >
          Reintentar
        </Button>
      }
    />
  );
}
