/**
 * Lo que la pantalla de la auditoría nunca muestra (diseño de la F2, 2.6, pregunta 12), aunque la API ya lo oculte
 * (backend/CLAUDE.md §6.9: `AuditLogger` guarda `password`, `remember_token` y `token` como `********`, y las rutas de los
 * archivos, la foto del paciente y el logo, salen como `"(archivo)"`). Si la API un día dejara pasar uno, acá se tapa:
 *
 * - el valor de una clave con `password` o `token` en el nombre (`password`, `remember_token`, `api_token`…), en cualquier
 *   nivel, sea un texto, un número, una lista o un objeto, se cambia por `********` (null queda null: no hay nada que ocultar);
 * - el valor de una clave que termina en `_path` (`photo_path`, `logo_path`), por `"(archivo)"`;
 * - un texto con la forma de una ruta de archivo de la clínica (`clinics/12/…`), en cualquier clave o lista, por `"(archivo)"`.
 *
 * Es una función pura: devuelve una copia y no toca los datos de la caché.
 */

export const MASKED_VALUE = '********';

export const FILE_VALUE = '(archivo)';

const SECRET_KEY = /password|token/i;

const PATH_KEY = /_path$/i;

const CLINIC_FILE = /^clinics\/\d+\//;

function maskValue(value: unknown): unknown {
  if (typeof value === 'string') {
    return CLINIC_FILE.test(value) ? FILE_VALUE : value;
  }

  if (Array.isArray(value)) {
    return value.map(maskValue);
  }

  if (value !== null && typeof value === 'object') {
    return maskObject(value as Record<string, unknown>);
  }

  return value;
}

function maskObject(values: Record<string, unknown>): Record<string, unknown> {
  const masked: Record<string, unknown> = {};

  for (const [key, value] of Object.entries(values)) {
    if (value === null) {
      masked[key] = null;
    } else if (SECRET_KEY.test(key)) {
      masked[key] = MASKED_VALUE;
    } else if (PATH_KEY.test(key)) {
      masked[key] = FILE_VALUE;
    } else {
      masked[key] = maskValue(value);
    }
  }

  return masked;
}

/** Los valores de un cambio, sin contraseñas, tokens ni rutas de archivos; null sigue null. */
export function maskAuditValues(
  values: Record<string, unknown> | null,
): Record<string, unknown> | null {
  return values === null ? null : maskObject(values);
}
