import { describe, expect, it } from 'vitest';
import { parseClientId, toListFilters, validateClientListSearch } from './search';

describe('validateClientListSearch (la búsqueda de /clientes en la URL)', () => {
  it('toma los valores válidos', () => {
    expect(validateClientListSearch({ buscar: 'perez', tipo: 'empresa', pagina: '2' })).toEqual({
      buscar: 'perez',
      tipo: 'empresa',
      pagina: 2,
    });
  });

  it('sin nada, vacía (los valores por defecto no van en la URL)', () => {
    expect(validateClientListSearch({})).toEqual({});
  });

  it.each([
    ['0', 0],
    ['-3', -3],
    ['abc', 'abc'],
    ['2.5', 2.5],
    ['1', 1],
    ['vacía', ''],
    ['un objeto', { a: 1 }],
  ])('una página inválida o la 1 (%s) vuelve al valor por defecto', (_, pagina) => {
    expect(validateClientListSearch({ pagina })).toEqual({});
  });

  it.each(['person', 'Empresa', 'otro', 1])('un tipo desconocido (%s) se descarta', (tipo) => {
    expect(validateClientListSearch({ tipo })).toEqual({});
  });

  it('la búsqueda se recorta a 100 caracteres y sin espacios en los extremos', () => {
    const long = `  ${'a'.repeat(120)}`;

    expect(validateClientListSearch({ buscar: long }).buscar).toBe('a'.repeat(100));
    expect(validateClientListSearch({ buscar: '  Firulais  ' }).buscar).toBe('Firulais');
  });

  it('una búsqueda vacía o que no es texto se descarta; un número se toma como texto', () => {
    expect(validateClientListSearch({ buscar: '   ' })).toEqual({});
    expect(validateClientListSearch({ buscar: ['a'] })).toEqual({});
    expect(validateClientListSearch({ buscar: 99100001 })).toEqual({ buscar: '99100001' });
  });

  it('los demás parámetros de la URL no pasan', () => {
    expect(validateClientListSearch({ buscar: 'x', otro: '1' })).toEqual({ buscar: 'x' });
  });
});

describe('toListFilters', () => {
  it('pasa la URL en castellano a los filtros de la API', () => {
    expect(toListFilters({ buscar: 'perez', tipo: 'empresa', pagina: 3 })).toEqual({
      search: 'perez',
      kind: 'company',
      page: 3,
    });
    expect(toListFilters({ tipo: 'persona' })).toEqual({ search: '', kind: 'person', page: 1 });
    expect(toListFilters({})).toEqual({ search: '', kind: null, page: 1 });
  });
});

describe('parseClientId', () => {
  it.each([
    ['1', 1],
    ['42', 42],
  ])('%s es un id', (param, id) => {
    expect(parseClientId(param)).toBe(id);
  });

  it.each(['0', '-1', 'abc', '01', '1.5', '1e3', '', ' 1', '99999999999999999'])(
    '"%s" no es un id',
    (param) => {
      expect(parseClientId(param)).toBeNull();
    },
  );
});
