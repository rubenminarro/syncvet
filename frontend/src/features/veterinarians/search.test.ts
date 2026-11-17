import { describe, expect, it } from 'vitest';
import {
  parseVeterinarianId,
  toVeterinarianListFilters,
  validateVeterinarianCreateSearch,
  validateVeterinarianListSearch,
} from './search';

describe('validateVeterinarianListSearch', () => {
  it('sin nada: todas las claves en undefined', () => {
    expect(validateVeterinarianListSearch({})).toEqual({
      buscar: undefined,
      estado: undefined,
      sucursal: undefined,
      pagina: undefined,
    });
  });

  it('valores válidos', () => {
    expect(
      validateVeterinarianListSearch({
        buscar: ' acosta ',
        estado: 'todos',
        sucursal: '10',
        pagina: '2',
      }),
    ).toEqual({ buscar: 'acosta', estado: 'todos', sucursal: 10, pagina: 2 });
  });

  it.each([['activos'], ['ACTIVOS'], ['inactive'], [1]])(
    'el estado %s se descarta ("activos" es el por defecto: no va en la URL)',
    (estado) => {
      expect(validateVeterinarianListSearch({ estado }).estado).toBeUndefined();
    },
  );

  it.each([['0'], ['-1'], ['1.5'], ['abc']])('la sucursal %s se descarta', (sucursal) => {
    expect(validateVeterinarianListSearch({ sucursal }).sucursal).toBeUndefined();
  });

  it('la búsqueda 1e3 (que el router lee como número) queda como se escribió', () => {
    expect(validateVeterinarianListSearch({ buscar: '1e3' }).buscar).toBe('1e3');
  });

  it('la página 1 no va en la URL', () => {
    expect(validateVeterinarianListSearch({ pagina: '1' }).pagina).toBeUndefined();
  });
});

describe('toVeterinarianListFilters: el estado traducido para la API', () => {
  it('"Activos" (por defecto): ni status ni include_inactive', () => {
    expect(toVeterinarianListFilters({})).toEqual({
      search: '',
      status: null,
      include_inactive: false,
      branch_id: null,
      page: 1,
    });
  });

  it('"Inactivos": status=inactive', () => {
    expect(toVeterinarianListFilters({ estado: 'inactivos' })).toMatchObject({
      status: 'inactive',
      include_inactive: false,
    });
  });

  it('"Todos": include_inactive=1, sin status', () => {
    expect(toVeterinarianListFilters({ estado: 'todos', sucursal: 11 })).toMatchObject({
      status: null,
      include_inactive: true,
      branch_id: 11,
    });
  });
});

describe('los ids de la URL', () => {
  it('el detalle: solo un entero positivo', () => {
    expect(parseVeterinarianId('4')).toBe(4);
    expect(parseVeterinarianId('04')).toBeNull();
    expect(parseVeterinarianId('x')).toBeNull();
  });

  it('?usuario= del alta', () => {
    expect(validateVeterinarianCreateSearch({ usuario: '8' })).toEqual({ usuario: 8 });
    expect(validateVeterinarianCreateSearch({ usuario: 'x' })).toEqual({ usuario: undefined });
    expect(validateVeterinarianCreateSearch({})).toEqual({ usuario: undefined });
  });
});
