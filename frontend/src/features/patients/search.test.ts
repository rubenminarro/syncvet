import { describe, expect, it } from 'vitest';
import { parsePatientId, toPatientListFilters, validatePatientListSearch } from './search';

const EMPTY = { buscar: undefined, especie: undefined, fallecidos: undefined, pagina: undefined };

describe('validatePatientListSearch (la búsqueda de /pacientes en la URL)', () => {
  it('toma los valores válidos', () => {
    expect(
      validatePatientListSearch({ buscar: 'toby', especie: 3, fallecidos: 1, pagina: 2 }),
    ).toEqual({
      buscar: 'toby',
      especie: 3,
      fallecidos: 1,
      pagina: 2,
    });
    expect(validatePatientListSearch({ especie: '3', fallecidos: '1', pagina: '2' })).toEqual({
      ...EMPTY,
      especie: 3,
      fallecidos: 1,
      pagina: 2,
    });
  });

  it('sin nada, vacía; las cuatro claves van siempre (con undefined)', () => {
    const result = validatePatientListSearch({});

    expect(result).toEqual(EMPTY);
    expect(Object.keys(result).sort()).toEqual(['buscar', 'especie', 'fallecidos', 'pagina']);
  });

  it.each([
    ['especie', 0],
    ['especie', -3],
    ['especie', 1.5],
    ['especie', 'perro'],
    ['fallecidos', 0],
    ['fallecidos', 2],
    ['fallecidos', 'si'],
    ['pagina', 1],
    ['pagina', 0],
    ['pagina', 'x'],
  ])('%s=%j se descarta', (key, value) => {
    expect(validatePatientListSearch({ [key]: value })).toEqual(EMPTY);
  });

  it('la búsqueda se recorta a 100 caracteres y sin espacios en los extremos; un número se toma como texto', () => {
    expect(validatePatientListSearch({ buscar: `  ${'a'.repeat(120)}` }).buscar).toBe(
      'a'.repeat(100),
    );
    expect(validatePatientListSearch({ buscar: 1000 }).buscar).toBe('1000');
    expect(validatePatientListSearch({ buscar: '   ' }).buscar).toBeUndefined();
  });

  it('los demás parámetros de la URL no pasan', () => {
    expect(validatePatientListSearch({ tipo: 'empresa' })).toEqual(EMPTY);
  });
});

describe('toPatientListFilters', () => {
  it('pasa la URL en castellano a los filtros de la API', () => {
    expect(toPatientListFilters({ buscar: 'toby', especie: 3, fallecidos: 1, pagina: 2 })).toEqual({
      search: 'toby',
      species_id: 3,
      include_deceased: true,
      page: 2,
    });
    expect(toPatientListFilters({})).toEqual({
      search: '',
      species_id: null,
      include_deceased: false,
      page: 1,
    });
  });
});

describe('parsePatientId', () => {
  it.each([
    ['1', 1],
    ['42', 42],
    ['0', null],
    ['01', null],
    ['-1', null],
    ['1.5', null],
    ['abc', null],
    ['99999999999999999', null],
  ])('%j → %j', (param, id) => {
    expect(parsePatientId(param)).toBe(id);
  });
});
