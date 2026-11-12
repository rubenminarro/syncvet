// La guarda de la preparación de Playwright (e2e/support/database.ts). Vive en src/test para que /validar-docs cuente el archivo.
import { describe, expect, it } from 'vitest';
import { assertE2eDatabase, E2E_DATABASE } from '../../e2e/support/database';

describe('assertE2eDatabase', () => {
  it('acepta solo syncvet_e2e', () => {
    expect(E2E_DATABASE).toBe('syncvet_e2e');
    expect(() => {
      assertE2eDatabase('syncvet_e2e');
    }).not.toThrow();
  });

  it.each([
    ['la de desarrollo', 'syncvet'],
    ['la de los tests de MySQL', 'syncvet_test_mysql'],
    ['otra que termina en _e2e', 'otra_e2e'],
    ['con un sufijo', 'syncvet_e2e_copia'],
    ['en mayúsculas', 'SYNCVET_E2E'],
    ['con espacios', ' syncvet_e2e '],
    ['vacía', ''],
    ['sin nombre', undefined],
  ])('rechaza %s (%j)', (_, name) => {
    expect(() => {
      assertE2eDatabase(name);
    }).toThrow(/solo corre contra la base syncvet_e2e/);
  });
});
