/**
 * `App\Support\PersonName::REGEX`: el nombre y el apellido de una persona, la misma regla para clientes (1.2) y usuarios
 * (2.2a). Letras separadas por espacios, o por un apóstrofo o un guion entre letras (D'Alessandro, Ana-María).
 */
export const PERSON_NAME_PATTERN =
  /^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ]+(?:(?:\s+|['-])[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ]+)*$/u;
