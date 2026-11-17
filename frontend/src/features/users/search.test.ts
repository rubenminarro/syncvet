import { describe, expect, it } from 'vitest';
import { toUserListFilters, validateUserListSearch } from './search';

describe('validateUserListSearch', () => {
  it('toma los valores válidos y devuelve siempre las cinco claves', () => {
    expect(
      validateUserListSearch({
        buscar: ' gomez ',
        rol: 'veterinario',
        sucursal: '10',
        inactivos: '1',
        pagina: 2,
      }),
    ).toEqual({ buscar: 'gomez', rol: 'veterinario', sucursal: 10, inactivos: 1, pagina: 2 });
    expect(validateUserListSearch({})).toEqual({
      buscar: undefined,
      rol: undefined,
      sucursal: undefined,
      inactivos: undefined,
      pagina: undefined,
    });
  });

  it.each([
    ['un rol desconocido', { rol: 'gerente' }, 'rol'],
    ['el rol con mayúscula', { rol: 'Veterinario' }, 'rol'],
    ['una sucursal que no es un entero', { sucursal: 'diez' }, 'sucursal'],
    ['una sucursal con decimales', { sucursal: 10.5 }, 'sucursal'],
    ['la sucursal 0', { sucursal: 0 }, 'sucursal'],
    ['una sucursal negativa', { sucursal: '-3' }, 'sucursal'],
    ['inactivos con otro valor', { inactivos: '2' }, 'inactivos'],
    ['la página 1 (el valor por defecto)', { pagina: 1 }, 'pagina'],
  ] as const)('descarta %s', (_case, raw, key) => {
    expect(validateUserListSearch(raw)[key]).toBeUndefined();
  });

  it('un valor inválido no toca los demás', () => {
    expect(validateUserListSearch({ rol: 'gerente', sucursal: 11 })).toMatchObject({
      rol: undefined,
      sucursal: 11,
    });
  });

  it('?buscar=1e3 queda como texto', () => {
    expect(validateUserListSearch({ buscar: '1e3' }).buscar).toBe('1e3');
  });
});

describe('toUserListFilters', () => {
  it('los nombres de la API, con los valores por defecto', () => {
    expect(toUserListFilters({})).toEqual({
      search: '',
      role: null,
      branch_id: null,
      include_inactive: false,
      page: 1,
    });
    expect(
      toUserListFilters({
        buscar: 'ana',
        rol: 'administrador',
        sucursal: 10,
        inactivos: 1,
        pagina: 3,
      }),
    ).toEqual({
      search: 'ana',
      role: 'administrador',
      branch_id: 10,
      include_inactive: true,
      page: 3,
    });
  });
});
