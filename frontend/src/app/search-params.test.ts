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
