/**
 * El control de la foto en el navegador (1.4), con los mensajes de la API (UploadPatientPhotoRequest): un archivo grande no
 * se manda (con PHP en 8 y 10 MB, uno de más de 10 MB respondería un 413 sin `errors`). El tipo real lo mira la API por el
 * contenido: acá solo el declarado, y un tipo vacío (sin extensión conocida) pasa, para no rechazar lo que la API acepta.
 */

/** 5 MB, como `MAX_KILOBYTES = 5120` de la API (la regla `max` de Laravel compara en kilobytes: 5 MB justos pasan). */
export const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;

/** El `accept` del campo. */
export const PHOTO_ACCEPT = PHOTO_TYPES.join(',');

export const PHOTO_MESSAGES = {
  tooLarge: 'La foto no debe pesar más de 5 MB.',
  notAnImage: 'La foto debe ser una imagen JPG, PNG o WEBP.',
} as const;

/** El error del archivo elegido, o null si se puede mandar. */
export function photoFileError(file: Pick<File, 'size' | 'type'>): string | null {
  if (file.type !== '' && !(PHOTO_TYPES as readonly string[]).includes(file.type)) {
    return PHOTO_MESSAGES.notAnImage;
  }

  if (file.size > PHOTO_MAX_BYTES) {
    return PHOTO_MESSAGES.tooLarge;
  }

  return null;
}
