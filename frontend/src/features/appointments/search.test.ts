import { describe, expect, it } from 'vitest';
import { parseSearch, stringifySearch } from '../../app/search-params';
import {
  listRangeError,
  RANGE_TOO_LONG,
  TO_BEFORE_FROM,
  toAppointmentListFilters,
  validateAgendaSearch,
  validateAppointmentListSearch,
} from './search';

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

describe('validateAgendaSearch: la semana (3.3)', () => {
  it('acepta vista=semana', () => {
    expect(validateAgendaSearch({ vista: 'semana', fecha: '2026-11-18' })).toEqual({
      ...EMPTY,
      vista: 'semana',
      fecha: '2026-11-18',
    });
  });
});

const LIST_EMPTY = {
  desde: undefined,
  hasta: undefined,
  agenda: undefined,
  veterinario: undefined,
  estados: undefined,
  buscar: undefined,
  pagina: undefined,
  serie: undefined,
};

describe('validateAppointmentListSearch (/turnos, 3.3)', () => {
  it('sin parámetros: las siete claves, sin valor', () => {
    expect(validateAppointmentListSearch({})).toEqual(LIST_EMPTY);
  });

  it('toma los valores válidos, con los estados en el orden de las casillas y sin repetir', () => {
    expect(
      validateAppointmentListSearch(
        parseSearch(
          '?desde=2026-11-16&hasta=2026-11-22&agenda=7&veterinario=4&estados=fichado,confirmado,fichado&buscar=%20luna%20&pagina=2',
        ),
      ),
    ).toEqual({
      desde: '2026-11-16',
      hasta: '2026-11-22',
      agenda: 7,
      veterinario: 4,
      estados: ['confirmado', 'fichado'],
      buscar: 'luna',
      pagina: 2,
    });
  });

  it('la página: un número o un texto de dígitos, desde 2; lo demás se descarta', () => {
    expect(validateAppointmentListSearch({ pagina: 3 }).pagina).toBe(3);
    expect(validateAppointmentListSearch({ pagina: '3' }).pagina).toBe(3);
    expect(validateAppointmentListSearch({ pagina: 'd' }).pagina).toBeUndefined();
    expect(validateAppointmentListSearch({ pagina: 1 }).pagina).toBeUndefined();
    expect(validateAppointmentListSearch({ pagina: '-4' }).pagina).toBeUndefined();
  });

  it('descarta los estados desconocidos y, sin ninguno conocido, la clave', () => {
    expect(validateAppointmentListSearch({ estados: ['ausente', 'borrado'] }).estados).toEqual([
      'ausente',
    ]);
    expect(validateAppointmentListSearch({ estados: ['confirmed'] }).estados).toBeUndefined();
    expect(validateAppointmentListSearch({ estados: [] }).estados).toBeUndefined();
    expect(validateAppointmentListSearch({ estados: 'cancelado,a-confirmar' }).estados).toEqual([
      'a-confirmar',
      'cancelado',
    ]);
  });

  it('descarta hasta si es anterior a desde (queda el día de desde)', () => {
    expect(validateAppointmentListSearch({ desde: '2026-11-17', hasta: '2026-11-16' })).toEqual({
      ...LIST_EMPTY,
      desde: '2026-11-17',
    });
  });

  it('31 días contando los dos extremos pasan; 32, no (como la API)', () => {
    expect(validateAppointmentListSearch({ desde: '2026-11-01', hasta: '2026-12-01' }).hasta).toBe(
      '2026-12-01',
    );
    expect(
      validateAppointmentListSearch({ desde: '2026-11-01', hasta: '2026-12-02' }).hasta,
    ).toBeUndefined();
  });

  it.each([
    ['una fecha mal formada', { desde: '16/11/2026' }],
    ['una página 1 o menos', { pagina: '1' }],
    ['una agenda con letras', { agenda: 'x' }],
    ['una búsqueda vacía', { buscar: '   ' }],
  ] as [string, Record<string, string>][])('descarta %s', (_, raw) => {
    expect(validateAppointmentListSearch(raw)).toEqual(LIST_EMPTY);
  });

  it('las fechas y los estados viajan como texto y como lista en la URL', () => {
    expect(
      stringifySearch({
        desde: '2026-11-16',
        hasta: '2026-11-22',
        estados: ['confirmado', 'fichado'],
      }),
    ).toBe('?desde=2026-11-16&hasta=2026-11-22&estados=confirmado,fichado');
  });
});

describe('listRangeError', () => {
  it('como la API: invertido, más de 31 días o sin error', () => {
    expect(listRangeError('2026-11-17', '2026-11-16')).toBe(TO_BEFORE_FROM);
    expect(listRangeError('2026-11-01', '2026-12-02')).toBe(RANGE_TOO_LONG);
    expect(RANGE_TOO_LONG).toBe('El rango no puede superar los 31 días.');
    expect(listRangeError('2026-11-01', '2026-12-01')).toBeNull();
    expect(listRangeError('2026-11-17', '2026-11-17')).toBeNull();
    expect(listRangeError('2026-11-17', undefined)).toBeNull();
    expect(listRangeError(undefined, '2026-11-17')).toBeNull();
  });

  it('cuenta días de calendario también sobre el cambio de horario (del 23/03/2024 al 22/04 pasa)', () => {
    expect(listRangeError('2024-03-23', '2024-04-22')).toBeNull();
    expect(listRangeError('2024-03-23', '2024-04-23')).toBe(RANGE_TOO_LONG);
  });
});

describe('toAppointmentListFilters', () => {
  it('los estados van con el valor de la API', () => {
    expect(
      toAppointmentListFilters({
        desde: '2026-11-16',
        estados: ['a-confirmar', 'ausente'],
        buscar: 'luna',
        pagina: 3,
      }),
    ).toEqual({
      from: '2026-11-16',
      to: null,
      schedule_id: null,
      veterinarian_id: null,
      status: ['pending_confirmation', 'no_show'],
      search: 'luna',
      page: 3,
      series_id: null,
    });
  });

  it('la serie va como series_id (3.6)', () => {
    expect(toAppointmentListFilters({ serie: SERIES }).series_id).toBe(SERIES);
  });
});

const SERIES = '9b2f1c4e-7f1a-4c55-a9a3-1f1e2d3c4b5a';

describe('la serie en la URL de /turnos (3.6)', () => {
  it('un UUID, en minúsculas; con la serie, desde y hasta no se usan', () => {
    expect(
      validateAppointmentListSearch({
        serie: SERIES.toUpperCase(),
        desde: '2026-11-16',
        hasta: '2026-11-22',
        estados: 'cancelado',
      }),
    ).toEqual({ ...LIST_EMPTY, serie: SERIES, estados: ['cancelado'] });
  });

  it('descarta una serie que no es un UUID', () => {
    expect(validateAppointmentListSearch({ serie: '123' }).serie).toBeUndefined();
    expect(validateAppointmentListSearch({ serie: 'x', desde: '2026-11-16' }).desde).toBe(
      '2026-11-16',
    );
  });

  it('viaja como texto en la URL', () => {
    expect(stringifySearch({ serie: SERIES })).toBe(`?serie=${SERIES}`);
  });
});
