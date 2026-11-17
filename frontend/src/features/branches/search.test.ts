import { describe, expect, it } from 'vitest';
import { parseBranchId, toBranchListFilters, validateBranchListSearch } from './search';

describe('validateBranchListSearch (la búsqueda de /configuracion/sucursales en la URL)', () => {
  it('toma los valores válidos', () => {
    expect(validateBranchListSearch({ buscar: 'cen', inactivas: 1, pagina: '2' })).toEqual({
      buscar: 'cen',
      inactivas: 1,
      pagina: 2,
    });
  });

  it('sin nada, vacía; las tres claves van siempre (undefined si se descartan)', () => {
    const search = validateBranchListSearch({});

    expect(Object.keys(search).sort()).toEqual(['buscar', 'inactivas', 'pagina']);
    expect(search).toEqual({});
  });

  it.each([0, '0', 2, 'si', false, null])(
    'inactivas distinto de 1 (%s) vuelve a solo las activas',
    (inactivas) => {
      expect(validateBranchListSearch({ inactivas }).inactivas).toBeUndefined();
    },
  );

  it.each([1, '1', true])('inactivas %s es 1', (inactivas) => {
    expect(validateBranchListSearch({ inactivas }).inactivas).toBe(1);
  });

  it.each([0, -3, 'abc', 2.5, 1, ''])('una página inválida o la 1 (%s) se descarta', (pagina) => {
    expect(validateBranchListSearch({ pagina }).pagina).toBeUndefined();
  });

  it('la búsqueda se recorta y un número se toma como texto', () => {
    expect(validateBranchListSearch({ buscar: `  ${'a'.repeat(120)}` }).buscar).toBe(
      'a'.repeat(100),
    );
    expect(validateBranchListSearch({ buscar: 1000 }).buscar).toBe('1000');
    expect(validateBranchListSearch({ buscar: '   ' }).buscar).toBeUndefined();
  });
});

describe('toBranchListFilters', () => {
  it('los filtros de GET branches', () => {
    expect(toBranchListFilters({})).toEqual({ search: '', include_inactive: false, page: 1 });
    expect(toBranchListFilters({ buscar: 'cen', inactivas: 1, pagina: 3 })).toEqual({
      search: 'cen',
      include_inactive: true,
      page: 3,
    });
  });
});

describe('parseBranchId', () => {
  it.each([
    ['1', 1],
    ['42', 42],
    ['0', null],
    ['01', null],
    ['-1', null],
    ['abc', null],
    ['nueva', null],
  ])('%s → %s', (param, expected) => {
    expect(parseBranchId(param)).toBe(expected);
  });
});
