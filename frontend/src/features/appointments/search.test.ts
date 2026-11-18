import { describe, expect, it } from 'vitest';
import { parseSearch, stringifySearch } from '../../app/search-params';
import { validateAgendaSearch } from './search';

const EMPTY = {
  vista: undefined,
  fecha: undefined,
  agenda: undefined,
  veterinario: undefined,
  cancelados: undefined,
  todos: undefined,
};

describe('validateAgendaSearch', () => {
  it('sin parámetros: todas las claves, sin valor (hoy, todas las agendas, sin cancelados)', () => {
    expect(validateAgendaSearch({})).toEqual(EMPTY);
  });

  it('toma los valores válidos', () => {
    expect(
      validateAgendaSearch({
        vista: 'dia',
        fecha: '2026-11-17',
        agenda: '7',
        veterinario: 4,
        cancelados: '1',
        todos: 1,
      }),
    ).toEqual({
      vista: 'dia',
      fecha: '2026-11-17',
      agenda: 7,
      veterinario: 4,
      cancelados: 1,
      todos: 1,
    });
  });

  it.each([
    ['una fecha mal formada', { fecha: '17/11/2026' }],
    ['una fecha que no existe', { fecha: '2026-02-30' }],
    ['una fecha con hora', { fecha: '2026-11-17T09:00' }],
    ['una vista desconocida', { vista: 'mes' }],
    ['la semana, que llega en la 3.3', { vista: 'semana' }],
    ['una agenda que no es un entero positivo', { agenda: '0' }],
    ['un veterinario con letras', { veterinario: 'abc' }],
    ['cancelados distinto de 1', { cancelados: '2' }],
    ['todos distinto de 1', { todos: 'si' }],
  ] as [string, Record<string, string>][])('descarta %s sin tocar lo demás', (_, raw) => {
    expect(validateAgendaSearch({ ...raw, agenda: raw.agenda ?? '7' })).toEqual({
      ...EMPTY,
      agenda: 'agenda' in raw ? undefined : 7,
    });
  });

  it('la fecha viaja como texto en la URL (sin comillas ni JSON)', () => {
    expect(stringifySearch({ fecha: '2026-11-17', agenda: 7 })).toBe('?fecha=2026-11-17&agenda=7');
    expect(parseSearch('?fecha=2026-11-17').fecha).toBe('2026-11-17');
  });
});
