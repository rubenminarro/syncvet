/**
 * El control de una imagen elegida en el navegador, antes de mandarla (común desde la 2.5, H8 del diseño de la F2; antes, la
 * foto del paciente de la 1.4): las reglas de `PrivateImageRequest` de la API, con el límite y los mensajes de cada uso (la
 * foto, 5 MB; el logo de la clínica, 2 MB). Un archivo grande no se manda (PHP corta el pedido con un 413 sin `errors`). El
 * tipo se mira por el contenido, como la API (finfo): los primeros bytes de un JPG, un PNG o un WEBP, no la extensión ni el
 * tipo declarado (un JPG llamado `.gif` lo acepta la API, y un texto llamado `.png` no). Lo que GD no decodifica o pasa de
 * 25 megapíxeles lo dice la API (422).
 */

/** Los tipos que acepta la API (JPG, PNG o WEBP). */
export const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;

/** El `accept` del campo: solo ordena el diálogo de archivos, no controla nada. */
export const IMAGE_ACCEPT = IMAGE_TYPES.join(',');

/** Los mensajes de la API para un archivo grande y para uno que no es una imagen. */
export interface ImageMessages {
  tooLarge: string;
  notAnImage: string;
}

/** El límite (en bytes; la regla `max` de Laravel compara en kilobytes: el límite justo pasa) y sus mensajes. */
export interface ImageRules {
  maxBytes: number;
  messages: ImageMessages;
}

const PNG_SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];

function startsWith(bytes: Uint8Array, signature: number[], offset = 0): boolean {
  return signature.every((byte, index) => bytes[offset + index] === byte);
}

function ascii(text: string): number[] {
  return Array.from(text, (character) => character.charCodeAt(0));
}

/** ¿Los primeros bytes son de un JPG (FF D8 FF), un PNG (su firma de 8 bytes) o un WEBP (RIFF….WEBP)? */
export function isImageContent(bytes: Uint8Array): boolean {
  return (
    startsWith(bytes, [0xff, 0xd8, 0xff]) ||
    startsWith(bytes, PNG_SIGNATURE) ||
    (startsWith(bytes, ascii('RIFF')) && startsWith(bytes, ascii('WEBP'), 8))
  );
}

/** El error del archivo elegido, o null si se puede mandar. El tamaño primero: la API también responde el 413 antes. */
export async function imageFileError(
  file: Pick<Blob, 'size' | 'slice'>,
  { maxBytes, messages }: ImageRules,
): Promise<string | null> {
  if (file.size > maxBytes) {
    return messages.tooLarge;
  }

  const head = new Uint8Array(await file.slice(0, 12).arrayBuffer());

  return isImageContent(head) ? null : messages.notAnImage;
}
