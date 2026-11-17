import { IMAGE_ACCEPT, imageFileError } from '../../lib/files/image';

/**
 * El control del logo en el navegador (2.5), con el control común de `src/lib/files/image.ts` y los mensajes de la API
 * (UploadClinicLogoRequest).
 */

/** 2 MB, como `MAX_KILOBYTES = 2048` de la API (la regla `max` de Laravel compara en kilobytes: 2 MB justos pasan). */
export const LOGO_MAX_BYTES = 2 * 1024 * 1024;

export const LOGO_ACCEPT = IMAGE_ACCEPT;

export const LOGO_MESSAGES = {
  tooLarge: 'El logo no debe pesar más de 2 MB.',
  notAnImage: 'El logo debe ser una imagen JPG, PNG o WEBP.',
} as const;

/** Sin logo (nunca se subió, otra persona lo quitó o el archivo falta): 404 de la API. */
export const CLINIC_LOGO_NOT_FOUND = 'CLINIC_LOGO_NOT_FOUND';

/** El error del logo elegido, o null si se puede mandar. */
export function logoFileError(file: Pick<Blob, 'size' | 'slice'>): Promise<string | null> {
  return imageFileError(file, { maxBytes: LOGO_MAX_BYTES, messages: LOGO_MESSAGES });
}
