import { describe, expect, it } from 'vitest';
import { parseSearch, stringifySearch } from './search-params';

describe('parseSearch: la búsqueda escrita llega siempre como texto', () => {
  it.each([
    ['?buscar=1e3', '1e3'],
    ['?buscar=12345678901234567890', '12345678901234567890'],
    ['?buscar=null', 'null'],
    ['?buscar=true', 'true'],
    ['?buscar=0012', '0012'],
    ['?buscar=%25', '%'],
    ['?buscar=Laura+Mart%C3%ADnez', 'Laura Martínez'],
    ['?buscar=a&buscar=b', 'a'],
  ])('%s → %j', (url, expected) => {
    expect(parseSearch(url).buscar).toBe(expected);
  });

  it('los demás parámetros siguen con el JSON del router', () => {
    expect(parseSearch('?buscar=7&pagina=2&tipo=empresa')).toEqual({
      buscar: '7',
      pagina: 2,
      tipo: 'empresa',
    });
    expect(parseSearch('')).toEqual({});
  });
});

describe('stringifySearch', () => {
  it('escribe la búsqueda sin comillas, aunque parezca un número, y respeta el orden', () => {
    expect(stringifySearch({ buscar: '1e3', pagina: 2 })).toBe('?buscar=1e3&pagina=2');
    expect(stringifySearch({ buscar: 'Laura Martínez' })).toBe('?buscar=Laura+Mart%C3%ADnez');
    expect(stringifySearch({ tipo: 'empresa', buscar: '123' })).toBe('?tipo=empresa&buscar=123');
  });

  it('omite lo que no tiene valor', () => {
    expect(stringifySearch({ buscar: undefined, pagina: undefined })).toBe('');
    expect(stringifySearch({})).toBe('');
  });

  it.each(['1e3', 'null', 'true', '12345678901234567890', '%', '"entre comillas"', 'a&b=c'])(
    'ida y vuelta: %j',
    (text) => {
      expect(parseSearch(stringifySearch({ buscar: text })).buscar).toBe(text);
    },
  );
});

describe('las listas en la URL (3.3: estados de /turnos)', () => {
  it('se leen como una lista de textos, sin los vacíos y con la primera aparición', () => {
    expect(parseSearch('?estados=confirmado,fichado').estados).toEqual(['confirmado', 'fichado']);
    expect(parseSearch('?estados=ausente').estados).toEqual(['ausente']);
    expect(parseSearch('?estados=1').estados).toEqual(['1']);
    expect(parseSearch('?estados=,confirmado,,').estados).toEqual(['confirmado']);
    expect(parseSearch('?estados=').estados).toEqual([]);
    expect(parseSearch('?estados=a&estados=b').estados).toEqual(['a']);
    expect(parseSearch('?estados=a%2Cb').estados).toEqual(['a', 'b']);
  });

  it('sin la clave, no aparece', () => {
    expect(parseSearch('?pagina=2')).toEqual({ pagina: 2 });
  });

  it('se escriben separadas por comas, sin comillas ni JSON, y una lista vacía no va', () => {
    expect(stringifySearch({ estados: ['confirmado', 'fichado'], pagina: 2 })).toBe(
      '?estados=confirmado,fichado&pagina=2',
    );
    expect(stringifySearch({ estados: ['a confirmar'] })).toBe('?estados=a%20confirmar');
    expect(stringifySearch({ estados: [] })).toBe('');
    expect(stringifySearch({ buscar: 'x', estados: [] })).toBe('?buscar=x');
  });

  it('ida y vuelta', () => {
    const estados = ['a-confirmar', 'confirmado', 'cancelado'];

    expect(parseSearch(stringifySearch({ estados })).estados).toEqual(estados);
  });

  it('los otros listados no cambian', () => {
    expect(parseSearch('?buscar=7&pagina=2&tipo=empresa')).toEqual({
      buscar: '7',
      pagina: 2,
      tipo: 'empresa',
    });
    expect(stringifySearch({ tipo: 'empresa', pagina: 3 })).toBe('?tipo=empresa&pagina=3');
  });
});
