import { describe, expect, it } from 'vitest';
import { PHOTO_MAX_BYTES, PHOTO_MESSAGES, photoFileError } from './photo';

describe('photoFileError', () => {
  it.each(['image/jpeg', 'image/png', 'image/webp'])('%s de 5 MB justos pasa', (type) => {
    expect(photoFileError({ type, size: PHOTO_MAX_BYTES })).toBeNull();
  });

  it('un byte más que 5 MB: el mensaje de la API', () => {
    expect(photoFileError({ type: 'image/png', size: PHOTO_MAX_BYTES + 1 })).toBe(
      PHOTO_MESSAGES.tooLarge,
    );
  });

  it.each(['text/plain', 'image/gif', 'image/svg+xml', 'image/heic', 'application/pdf'])(
    '%s: el mensaje de la API',
    (type) => {
      expect(photoFileError({ type, size: 10 })).toBe(PHOTO_MESSAGES.notAnImage);
    },
  );

  it('sin tipo declarado pasa (lo decide la API por el contenido), pero no si es grande', () => {
    expect(photoFileError({ type: '', size: 10 })).toBeNull();
    expect(photoFileError({ type: '', size: PHOTO_MAX_BYTES + 1 })).toBe(PHOTO_MESSAGES.tooLarge);
  });
});
