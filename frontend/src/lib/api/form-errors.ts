import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { errorTreatment, type ApiError } from './errors';

/**
 * Vuelca un 422 (VALIDATION_ERROR) a un formulario de React Hook Form: cada clave de `errors` que corresponde a un campo
 * del formulario va a ese campo con setError (foco en el primero); las demás vuelven como lista, para la alerta de arriba.
 *
 * Laravel nombra los errores de un arreglo con su índice (`branch_ids.0`, `hours.1.starts_at`). Una clave va al campo:
 *
 * 1. con el mismo nombre (`hours.1.starts_at`, si el formulario lo declara así);
 * 2. de un patrón con `*` en el lugar del índice (`hours.*.starts_at` recibe `hours.1.starts_at`, en ese mismo campo);
 * 3. si no, al campo padre más cercano (`branch_ids.0` va a `branch_ids`).
 *
 * Los mensajes de varias claves que caen en un mismo campo se juntan. Devuelve null si el error no es de validación: lo
 * trata quien llama, según la tabla de errors.ts.
 */
/** Un patrón de campos de un arreglo, con `*` en el lugar del índice: `hours.*.starts_at`. */
export type FieldPattern = `${string}*${string}`;

export function applyValidationErrors<T extends FieldValues>(
  error: ApiError,
  setError: UseFormSetError<T>,
  fields: readonly (Path<T> | FieldPattern)[],
): string[] | null {
  if (errorTreatment(error.errorCode) !== 'validation') {
    return null;
  }

  const byField = new Map<string, string[]>();
  const others: string[] = [];

  for (const [key, messages] of Object.entries(error.fieldErrors())) {
    const field = fieldFor(key, fields);

    if (field === null) {
      others.push(messages.join(' '));
      continue;
    }

    byField.set(field, [...(byField.get(field) ?? []), ...messages]);
  }

  let focused = false;

  for (const [field, messages] of byField) {
    setError(
      field as Path<T>,
      { type: 'server', message: messages.join(' ') },
      { shouldFocus: !focused },
    );
    focused = true;
  }

  return others;
}

/** El campo del formulario que recibe la clave de `errors`, o null si no es de ningún campo. */
export function fieldFor(key: string, fields: readonly string[]): string | null {
  if (fields.includes(key)) {
    return key;
  }

  const segments = key.split('.');
  const pattern = fields.find((field) => matchesPattern(field.split('.'), segments));

  if (pattern !== undefined) {
    return key;
  }

  for (let length = segments.length - 1; length > 0; length--) {
    const parent = segments.slice(0, length).join('.');

    if (fields.includes(parent)) {
      return parent;
    }
  }

  return null;
}

function matchesPattern(pattern: string[], segments: string[]): boolean {
  return (
    pattern.includes('*') &&
    pattern.length === segments.length &&
    pattern.every(
      (part, index) =>
        part === segments[index] || (part === '*' && /^\d+$/.test(segments[index] ?? '')),
    )
  );
}
