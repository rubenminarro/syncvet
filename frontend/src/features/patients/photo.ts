import { IMAGE_ACCEPT, IMAGE_TYPES, imageFileError, isImageContent } from '../../lib/files/image';

/**
 * El control de la foto en el navegador (1.4), con los mensajes de la API (UploadPatientPhotoRequest). Desde la 2.5, sobre el
 * control común de `src/lib/files/image.ts` (el tipo por el contenido y el tamaño primero), con el límite de la foto: 5 MB.
 */

/** 5 MB, como `MAX_KILOBYTES = 5120` de la API (la regla `max` de Laravel compara en kilobytes: 5 MB justos pasan). */
export const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

export const PHOTO_TYPES = IMAGE_TYPES;

/** El `accept` del campo: solo ordena el diálogo de archivos, no controla nada. */
export const PHOTO_ACCEPT = IMAGE_ACCEPT;

export const PHOTO_MESSAGES = {
  tooLarge: 'La foto no debe pesar más de 5 MB.',
  notAnImage: 'La foto debe ser una imagen JPG, PNG o WEBP.',
} as const;

/** ¿Los primeros bytes son de un JPG, un PNG o un WEBP? (el control común). */
export const isPhotoContent = isImageContent;

/** El error de la foto elegida, o null si se puede mandar. */
export function photoFileError(file: Pick<Blob, 'size' | 'slice'>): Promise<string | null> {
  return imageFileError(file, { maxBytes: PHOTO_MAX_BYTES, messages: PHOTO_MESSAGES });
}
