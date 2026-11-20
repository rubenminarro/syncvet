import { z } from 'zod';
import { trimLikeApi } from '../../lib/format/text';

/**
 * El nombre de una especie o una raza (1.5), con las reglas y los mensajes de la API (`StoreSpeciesRequest`,
 * `StoreBreedRequest` y `CatalogMessages`): obligatorio, de 2 a 60 caracteres (especie) u 80 (raza), con al menos una letra y
 * solo letras, números, espacios, puntos, guiones, apóstrofos y paréntesis. El largo en caracteres (`Array.from`, como
 * `mb_strlen`) y sin los espacios de los extremos (`TrimStrings` de Laravel). El nombre repetido lo dice la API (422 en `name`).
 */

export type CatalogKind = 'species' | 'breed';

export const NAME_MAX: Record<CatalogKind, number> = { species: 60, breed: 80 };

const SUBJECT: Record<CatalogKind, string> = {
  species: 'El nombre de la especie',
  breed: 'El nombre de la raza',
};

/** `CatalogMessages::NAME_REGEX`: `\pL` y `\pN` de PHP, con al menos una letra; sin "/" (es del sufijo de baja). */
const NAME_PATTERN = /^(?=[^\p{L}]*\p{L})[\p{L}\p{N}\s.\-'()]+$/u;

export function nameMessages(kind: CatalogKind) {
  const subject = SUBJECT[kind];

  return {
    required: `${subject} es obligatorio.`,
    min: `${subject} debe tener al menos 2 caracteres.`,
    max: `${subject} no debe tener más de ${String(NAME_MAX[kind])} caracteres.`,
    pattern: `${subject} debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.`,
  };
}

export function catalogNameSchema(kind: CatalogKind) {
  const messages = nameMessages(kind);

  return z
    .string()
    .overwrite(trimLikeApi)
    .superRefine((value, context) => {
      const length = Array.from(value).length;
      let message: string | null = null;

      if (length === 0) {
        message = messages.required;
      } else if (length < 2) {
        message = messages.min;
      } else if (length > NAME_MAX[kind]) {
        message = messages.max;
      } else if (!NAME_PATTERN.test(value)) {
        message = messages.pattern;
      }

      if (message !== null) {
        context.addIssue({ code: 'custom', message });
      }
    });
}

/** El nombre listo para mandar (sin los espacios de los extremos) o el error, con el mensaje de la API. */
export function parseCatalogName(
  kind: CatalogKind,
  value: string,
): { ok: true; name: string } | { ok: false; error: string } {
  const result = catalogNameSchema(kind).safeParse(value);

  return result.success
    ? { ok: true, name: result.data }
    : { ok: false, error: result.error.issues[0]?.message ?? nameMessages(kind).required };
}
