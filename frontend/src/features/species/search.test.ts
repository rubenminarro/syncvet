import { describe, expect, it } from 'vitest';
import { validateSpeciesListSearch } from './search';

describe('validateSpeciesListSearch (1.5)', () => {
  it.each([1, '1', true])('?inactivas=%s incluye las inactivas', (value) => {
    expect(validateSpeciesListSearch({ inactivas: value })).toEqual({ inactivas: 1 });
  });

  it.each([undefined, 0, '0', 2, 'si', false, null])(
    '?inactivas=%s vuelve al valor por defecto (la clave va con undefined)',
    (value) => {
      const search = validateSpeciesListSearch({ inactivas: value });

      expect(Object.keys(search)).toEqual(['inactivas']);
      expect(search.inactivas).toBeUndefined();
    },
  );
});
