import { describe, expect, it } from 'vitest';
import { isPhotoContent, PHOTO_MAX_BYTES, PHOTO_MESSAGES, photoFileError } from './photo';

function ascii(text: string): number[] {
  return Array.from(text, (character) => character.charCodeAt(0));
}

/** Los primeros bytes de cada formato, como los mira finfo en la API. */
const JPEG = [0xff, 0xd8, 0xff, 0xe0];
const PNG = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
const WEBP = ascii('RIFF').concat([0, 0, 0, 0], ascii('WEBP'));

/** Un archivo con esos primeros bytes, de `size` bytes en total, con el tipo que declara el navegador por la extensión. */
function file(
  head: number[],
  { size = 100, type = '' }: { size?: number; type?: string } = {},
): Blob {
  const bytes = new Uint8Array(Math.max(size, head.length));
  bytes.set(head);

  return new Blob([bytes], { type });
}

describe('photoFileError: el tipo por el contenido y hasta 5 MB, como la API', () => {
  it.each([
    ['JPG', JPEG],
    ['PNG', PNG],
    ['WEBP', WEBP],
  ])('un %s de 5 MB justos pasa', async (_, head) => {
    expect(await photoFileError(file(head, { size: PHOTO_MAX_BYTES }))).toBeNull();
  });

  it('un byte más que 5 MB: el mensaje de la API, antes de mirar el contenido', async () => {
    expect(await photoFileError(file(PNG, { size: PHOTO_MAX_BYTES + 1 }))).toBe(
      PHOTO_MESSAGES.tooLarge,
    );
    expect(await photoFileError(file([0x41], { size: PHOTO_MAX_BYTES + 1 }))).toBe(
      PHOTO_MESSAGES.tooLarge,
    );
  });

  it('un JPG con otra extensión (el navegador declara image/gif) pasa: la API lo acepta', async () => {
    expect(await photoFileError(file(JPEG, { type: 'image/gif' }))).toBeNull();
  });

  it('sin tipo declarado, pasa si el contenido es una imagen', async () => {
    expect(await photoFileError(file(WEBP))).toBeNull();
  });

  it.each([
    ['un texto renombrado a .png', ascii('Hola'), 'image/png'],
    ['un GIF', ascii('GIF89a'), 'image/gif'],
    ['un SVG', ascii('<svg'), 'image/svg+xml'],
    ['un RIFF que no es WEBP (un WAV)', ascii('RIFF....WAVE'), 'audio/wav'],
    ['un PNG cortado (firma incompleta)', PNG.slice(0, 7), 'image/png'],
  ])('%s: el mensaje de la API', async (_, head, type) => {
    expect(await photoFileError(file(head, { size: head.length, type }))).toBe(
      PHOTO_MESSAGES.notAnImage,
    );
  });

  it('un archivo vacío no es una imagen', async () => {
    expect(isPhotoContent(new Uint8Array())).toBe(false);
    expect(await photoFileError(new Blob([]))).toBe(PHOTO_MESSAGES.notAnImage);
  });
});
