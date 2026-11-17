import { describe, expect, it } from 'vitest';
import { parseServiceId, toServiceListFilters, validateServiceListSearch } from './search';

describe('validateServiceListSearch', () => {
  it('sin nada: las cuatro claves sin valor', () => {
    expect(validateServiceListSearch({})).toEqual({
      buscar: undefined,
      categoria: undefined,
      estado: undefined,
      pagina: undefined,
    });
  });

  it('los valores válidos pasan', () => {
    expect(
      validateServiceListSearch({
        buscar: '  vacuna ',
        categoria: 'vaccination',
        estado: 'todos',
        pagina: '3',
      }),
    ).toEqual({ buscar: 'vacuna', categoria: 'vaccination', estado: 'todos', pagina: 3 });
  });

  it.each([
    [{ categoria: 'dentistry' }],
    [{ categoria: 'Vaccination' }],
    [{ estado: 'activos' }],
    [{ estado: 'borrados' }],
    [{ pagina: '1' }],
    [{ pagina: '0' }],
    [{ pagina: 'abc' }],
    [{ buscar: '   ' }],
  ])('un valor inválido se descarta: %j', (raw) => {
    expect(
      Object.values(validateServiceListSearch(raw)).every((value) => value === undefined),
    ).toBe(true);
  });

  it('un valor inválido no descarta a los demás', () => {
    expect(validateServiceListSearch({ categoria: 'x', estado: 'inactivos' })).toEqual({
      buscar: undefined,
      categoria: undefined,
      estado: 'inactivos',
      pagina: undefined,
    });
  });
});

describe('toServiceListFilters', () => {
  it.each([
    [undefined, null, false],
    ['inactivos', 'inactive', false],
    ['todos', null, true],
  ] as const)('estado %s → status %s, include_inactive %s', (estado, status, includeInactive) => {
    const filters = toServiceListFilters({ estado });

    expect(filters.status).toBe(status);
    expect(filters.include_inactive).toBe(includeInactive);
  });

  it('la búsqueda, la categoría y la página', () => {
    expect(toServiceListFilters({ buscar: 'vacuna', categoria: 'vaccination', pagina: 2 })).toEqual(
      {
        search: 'vacuna',
        category: 'vaccination',
        status: null,
        include_inactive: false,
        page: 2,
      },
    );
  });
});

describe('parseServiceId', () => {
  it.each([
    ['7', 7],
    ['0', null],
    ['-1', null],
    ['1.5', null],
    ['abc', null],
  ])('%s → %s', (param, id) => {
    expect(parseServiceId(param)).toBe(id);
  });
});
