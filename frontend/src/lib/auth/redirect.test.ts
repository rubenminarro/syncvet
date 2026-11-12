import { describe, expect, it } from 'vitest';
import { safeRedirect } from './redirect';

const ORIGIN = 'http://localhost:5173';

describe('safeRedirect', () => {
  it.each([
    ['/clientes', '/clientes'],
    ['/clientes/5?pagina=2#notas', '/clientes/5?pagina=2#notas'],
    ['/', '/'],
  ])('acepta la ruta interna %s', (volver, expected) => {
    expect(safeRedirect(volver, ORIGIN)).toBe(expected);
  });

  it.each([
    [undefined],
    [''],
    ['clientes'],
    ['https://otro.sitio/robar'],
    ['//otro.sitio/robar'],
    ['/\\otro.sitio'],
    ['javascript:alert(1)'],
    ['/ingresar'],
    ['/ingresar?volver=/clientes'],
    [42],
  ])('lleva al inicio con %s', (volver) => {
    expect(safeRedirect(volver, ORIGIN)).toBe('/');
  });
});
