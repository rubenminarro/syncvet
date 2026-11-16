/**
 * El control de la foto en el navegador (1.4), con los mensajes de la API (UploadPatientPhotoRequest): un archivo grande no
 * se manda (con PHP en 8 y 10 MB, uno de más de 10 MB respondería un 413 sin `errors`). El tipo se mira por el contenido,
 * como la API (finfo): los primeros bytes de un JPG, un PNG o un WEBP, no la extensión ni el tipo declarado (revisión de la
 * 1.4: un JPG llamado `.gif` lo acepta la API, y un texto llamado `.png` no). Lo que GD no decodifica o pasa de 25
 * megapíxeles lo dice la API (422).
 */

/** 5 MB, como `MAX_KILOBYTES = 5120` de la API (la regla `max` de Laravel compara en kilobytes: 5 MB justos pasan). */
export const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;

/** El `accept` del campo: solo ordena el diálogo de archivos, no controla nada. */
export const PHOTO_ACCEPT = PHOTO_TYPES.join(',');

export const PHOTO_MESSAGES = {
  tooLarge: 'La foto no debe pesar más de 5 MB.',
  notAnImage: 'La foto debe ser una imagen JPG, PNG o WEBP.',
} as const;

const PNG_SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];

function startsWith(bytes: Uint8Array, signature: number[], offset = 0): boolean {
  return signature.every((byte, index) => bytes[offset + index] === byte);
}

function ascii(text: string): number[] {
  return Array.from(text, (character) => character.charCodeAt(0));
}

/** ¿Los primeros bytes son de un JPG (FF D8 FF), un PNG (su firma de 8 bytes) o un WEBP (RIFF….WEBP)? */
export function isPhotoContent(bytes: Uint8Array): boolean {
  return (
    startsWith(bytes, [0xff, 0xd8, 0xff]) ||
    startsWith(bytes, PNG_SIGNATURE) ||
    (startsWith(bytes, ascii('RIFF')) && startsWith(bytes, ascii('WEBP'), 8))
  );
}

/** El error del archivo elegido, o null si se puede mandar. El tamaño primero: la API también responde el 413 antes. */
export async function photoFileError(file: Pick<Blob, 'size' | 'slice'>): Promise<string | null> {
  if (file.size > PHOTO_MAX_BYTES) {
    return PHOTO_MESSAGES.tooLarge;
  }

  const head = new Uint8Array(await file.slice(0, 12).arrayBuffer());

  return isPhotoContent(head) ? null : PHOTO_MESSAGES.notAnImage;
}
