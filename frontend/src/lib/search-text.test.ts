import { describe, expect, it } from 'vitest';
import { normalizeSearchText, SEARCH_MAX_LENGTH } from './search-text';
describe('deuda 28: los invisibles de los bordes se recortan como en la API (trimLikeApi)', () => {
  it('la búsqueda sin los invisibles de los extremos', () => {
    expect(normalizeSearchText('\u200B Luna \u3164')).toBe('Luna');
  });

  it('hasta 100 caracteres de la API (puntos de código), sin cortar uno en dos', () => {
    expect(Array.from(normalizeSearchText('𝒜'.repeat(SEARCH_MAX_LENGTH + 5)))).toHaveLength(
      SEARCH_MAX_LENGTH,
    );
  });
});
