import { describe, expect, it } from 'vitest';
import { parseScheduleId, toScheduleListFilters, validateScheduleListSearch } from './search';

describe('validateScheduleListSearch', () => {
  it('toma los valores válidos', () => {
    expect(
      validateScheduleListSearch({
        buscar: ' consultorio ',
        sucursal: '10',
        inactivas: '1',
        pagina: '2',
      }),
    ).toEqual({ buscar: 'consultorio', sucursal: 10, inactivas: 1, pagina: 2 });
  });

  it('descarta cada valor inválido sin tocar los demás, y devuelve siempre las cuatro claves', () => {
    expect(
      validateScheduleListSearch({ buscar: '   ', sucursal: 'norte', inactivas: '0', pagina: '1' }),
    ).toEqual({ buscar: undefined, sucursal: undefined, inactivas: undefined, pagina: undefined });
    expect(validateScheduleListSearch({ sucursal: 0, pagina: 'x', inactivas: 2 })).toEqual({
      buscar: undefined,
      sucursal: undefined,
      inactivas: undefined,
      pagina: undefined,
    });
  });

  it('lo escrito es texto: ?buscar=1e3 es "1e3"', () => {
    expect(validateScheduleListSearch({ buscar: '1e3' }).buscar).toBe('1e3');
  });
});

describe('toScheduleListFilters', () => {
  it('sin búsqueda: todas las sucursales, solo las activas, la página 1', () => {
    expect(toScheduleListFilters({})).toEqual({
      search: '',
      branch_id: null,
      include_inactive: false,
      page: 1,
    });
  });

  it('con "Incluir las inactivas" y una sucursal', () => {
    expect(toScheduleListFilters({ sucursal: 11, inactivas: 1, pagina: 3 })).toEqual({
      search: '',
      branch_id: 11,
      include_inactive: true,
      page: 3,
    });
  });
});

describe('parseScheduleId', () => {
  it.each([
    ['7', 7],
    ['0', null],
    ['-1', null],
    ['7a', null],
    ['1.5', null],
  ])('%s → %s', (param, expected) => {
    expect(parseScheduleId(param)).toBe(expected);
  });
});
