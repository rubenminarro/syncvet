import { describe, expect, it } from 'vitest';
import { LOGO_MAX_BYTES, LOGO_MESSAGES, logoFileError } from '../../features/settings/logo';
import { IMAGE_ACCEPT, imageFileError, isImageContent } from './image';

function ascii(text: string): number[] {
  return Array.from(text, (character) => character.charCodeAt(0));
}

const JPEG = [0xff, 0xd8, 0xff, 0xe0];
const PNG = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
const WEBP = ascii('RIFF').concat([0, 0, 0, 0], ascii('WEBP'));

function file(
  head: number[],
  { size = 100, type = '' }: { size?: number; type?: string } = {},
): Blob {
  const bytes = new Uint8Array(Math.max(size, head.length));
  bytes.set(head);

  return new Blob([bytes], { type });
}

const MESSAGES = { tooLarge: 'Grande.', notAnImage: 'No es una imagen.' };

describe('imageFileError: el límite y los mensajes como parámetros (2.5, H8)', () => {
  it('el límite justo pasa y un byte más no, con el mensaje que se le pasa', async () => {
    const rules = { maxBytes: 1000, messages: MESSAGES };

    expect(await imageFileError(file(PNG, { size: 1000 }), rules)).toBeNull();
    expect(await imageFileError(file(PNG, { size: 1001 }), rules)).toBe('Grande.');
  });

  it('el tamaño se mira antes que el contenido, como la API', async () => {
    expect(
      await imageFileError(file([0x41], { size: 2000 }), { maxBytes: 1000, messages: MESSAGES }),
    ).toBe('Grande.');
  });

  it('el tipo por el contenido, no por el tipo declarado', async () => {
    const rules = { maxBytes: 1000, messages: MESSAGES };

    expect(await imageFileError(file(JPEG, { type: 'image/gif' }), rules)).toBeNull();
    expect(await imageFileError(file(ascii('hola'), { type: 'image/png' }), rules)).toBe(
      'No es una imagen.',
    );
  });

  it('reconoce JPG, PNG y WEBP; el accept los lista', () => {
    expect([JPEG, PNG, WEBP].map((head) => isImageContent(new Uint8Array(head)))).toEqual([
      true,
      true,
      true,
    ]);
    expect(isImageContent(new Uint8Array(ascii('RIFF0000WAVE')))).toBe(false);
    expect(IMAGE_ACCEPT).toBe('image/jpeg,image/png,image/webp');
  });
});

describe('logoFileError: hasta 2 MB, con los mensajes de UploadClinicLogoRequest', () => {
  it('el límite es de 2 MB (MAX_KILOBYTES = 2048)', () => {
    expect(LOGO_MAX_BYTES).toBe(2048 * 1024);
  });

  it.each([
    ['JPG', JPEG],
    ['PNG', PNG],
    ['WEBP', WEBP],
  ])('un %s de 2 MB justos pasa', async (_, head) => {
    expect(await logoFileError(file(head, { size: LOGO_MAX_BYTES }))).toBeNull();
  });

  it('un byte más de 2 MB se rechaza con el mensaje del logo', async () => {
    expect(await logoFileError(file(PNG, { size: LOGO_MAX_BYTES + 1 }))).toBe(
      'El logo no debe pesar más de 2 MB.',
    );
  });

  it('un texto con la extensión de una imagen se rechaza por el contenido', async () => {
    expect(await logoFileError(file(ascii('no es una imagen'), { type: 'image/png' }))).toBe(
      LOGO_MESSAGES.notAnImage,
    );
  });
});
