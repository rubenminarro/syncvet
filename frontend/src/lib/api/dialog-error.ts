import { errorTreatment, isApiError, MESSAGES, userMessage } from './errors';

/** Un error en un diálogo: el texto y si otro intento daría lo mismo (entonces queda solo "Cerrar"). */
export interface DialogFailure {
  message: string;
  final: boolean;
}

/**
 * Qué hace un diálogo con el error de su acción (desde la 2.3, común a sucursales, usuarios y veterinarios): un 404 (lo
 * eliminaron en otra pestaña), `onGone` (aviso y lo que corresponda) y null; un 422 o un error de negocio
 * (`*_DEFAULT_NOT_DEACTIVATABLE`, `USER_LAST_ADMIN`…), el mensaje de la API y solo "Cerrar"; lo demás (el servidor, la red,
 * `FORBIDDEN`), el mensaje y el botón para probar de nuevo.
 */
export function dialogError(
  error: unknown,
  onGone: (message: string) => void,
): DialogFailure | null {
  if (!isApiError(error)) {
    return { message: MESSAGES.programming, final: false };
  }

  const treatment = errorTreatment(error.errorCode);

  if (treatment === 'not-found') {
    onGone(error.message);

    return null;
  }

  if (treatment === 'validation') {
    const messages = Object.values(error.fieldErrors()).flat();

    return { message: messages.length > 0 ? messages.join(' ') : error.message, final: true };
  }

  return { message: userMessage(error), final: treatment === 'business' };
}
