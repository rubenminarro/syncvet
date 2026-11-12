import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { errorTreatment, type ApiError } from './errors';

/**
 * Vuelca un 422 (VALIDATION_ERROR) a un formulario de React Hook Form: cada clave de `errors` que es un campo del formulario
 * va a ese campo con setError (foco en el primero); las demás vuelven como lista, para la alerta de arriba.
 *
 * Devuelve null si el error no es de validación: lo trata quien llama, según la tabla de errors.ts.
 */
export function applyValidationErrors<T extends FieldValues>(
  error: ApiError,
  setError: UseFormSetError<T>,
  fields: readonly Path<T>[],
): string[] | null {
  if (errorTreatment(error.errorCode) !== 'validation') {
    return null;
  }

  const others: string[] = [];
  let focused = false;

  for (const [key, messages] of Object.entries(error.fieldErrors())) {
    const field = fields.find((name) => name === key);
    const message = messages.join(' ');

    if (field === undefined) {
      others.push(message);
      continue;
    }

    setError(field, { type: 'server', message }, { shouldFocus: !focused });
    focused = true;
  }

  return others;
}
