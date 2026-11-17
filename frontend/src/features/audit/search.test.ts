import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { parseSearch, stringifySearch } from '../../app/search-params';
import {
  AUDIT_RECORD_MESSAGE,
  AUDIT_TIME_WITHOUT_DATE_MESSAGE,
  AUDIT_TYPE_REQUIRED_MESSAGE,
  validateAuditFilterForm,
  type AuditFilterForm,
} from './filters';
import { AUDIT_DATE_FORMAT_MESSAGE, AUDIT_TO_BEFORE_FROM_MESSAGE } from './dates';
import { toAuditListFilters, validateAuditListSearch } from './search';
import { AUDIT_EVENTS, AUDITABLE_TYPE_VALUES } from './types';

const EMPTY = {
  desde: undefined,
  hasta: undefined,
  usuario: undefined,
  evento: undefined,
  entidad: undefined,
  registro: undefined,
  buscar: undefined,
  pagina: undefined,
};

/** La URL como la lee el router (parseSearch) y la valida la ruta. */
const fromUrl = (search: string) => validateAuditListSearch(parseSearch(search));

describe('validateAuditListSearch: los filtros de la URL', () => {
  it('sin nada, todas las claves en undefined', () => {
    expect(validateAuditListSearch({})).toEqual(EMPTY);
  });

  it('todos los filtros válidos', () => {
    expect(
      fromUrl(
        '?desde=2026-11-09&hasta=2026-11-09T18:00&usuario=3&evento=updated&entidad=client&registro=12&buscar=ana&pagina=2',
      ),
    ).toEqual({
      desde: '2026-11-09',
      hasta: '2026-11-09T18:00',
      usuario: 3,
      evento: 'updated',
      entidad: 'client',
      registro: 12,
      buscar: 'ana',
      pagina: 2,
    });
  });

  it.each([
    ['una fecha mal formada', '?desde=2026-11-9&hasta=mañana'],
    ['una fecha que no existe', '?desde=2026-02-30&hasta=2026-11-09T24:00'],
    ['un evento desconocido', '?evento=hackeado'],
    ['un tipo (entidad) desconocido', '?entidad=password'],
    ['un usuario que no es un entero positivo', '?usuario=0'],
    ['la página 1 o una página negativa', '?pagina=1'],
  ])('%s se descarta', (_, search) => {
    expect(fromUrl(search)).toEqual(EMPTY);
  });

  it('el registro sin el tipo (entidad) se descarta (la API lo exige)', () => {
    expect(fromUrl('?registro=12')).toEqual(EMPTY);
    expect(fromUrl('?registro=abc&entidad=client')).toEqual({ ...EMPTY, entidad: 'client' });
  });

  it('"hasta" anterior a "desde" se descarta; "desde" queda', () => {
    expect(fromUrl('?desde=2026-11-10&hasta=2026-11-09')).toEqual({
      ...EMPTY,
      desde: '2026-11-10',
    });
    expect(fromUrl('?desde=2026-11-09T18:00&hasta=2026-11-09T18:00')).toEqual({
      ...EMPTY,
      desde: '2026-11-09T18:00',
      hasta: '2026-11-09T18:00',
    });
  });

  it('las fechas y la búsqueda son texto en la URL: ni JSON ni comillas', () => {
    expect(parseSearch('?desde=2026-11-09T13:00&buscar=1e3')).toMatchObject({
      desde: '2026-11-09T13:00',
      buscar: '1e3',
    });
    expect(stringifySearch({ desde: '2026-11-09T13:00', hasta: '2026-11-09', buscar: '1e3' })).toBe(
      '?desde=2026-11-09T13%3A00&hasta=2026-11-09&buscar=1e3',
    );
    expect(fromUrl('?desde=2026-11-09T13%3A00')).toEqual({ ...EMPTY, desde: '2026-11-09T13:00' });
  });
});

describe('toAuditListFilters: lo que se manda a la API', () => {
  it('sin filtros, la página 1 y nada más', () => {
    expect(toAuditListFilters(EMPTY)).toEqual({
      from: null,
      to: null,
      user_id: null,
      event: null,
      auditable_type: null,
      auditable_id: null,
      search: '',
      page: 1,
    });
  });

  it('las fechas en UTC: la fecha sola, el día completo; "hasta" con hora, con los segundos en 59', () => {
    expect(
      toAuditListFilters({ ...EMPTY, desde: '2026-11-09', hasta: '2026-11-09' }),
    ).toMatchObject({ from: '2026-11-09T03:00:00.000Z', to: '2026-11-10T02:59:59.000Z' });
    expect(
      toAuditListFilters({ ...EMPTY, desde: '2026-11-09T13:00', hasta: '2026-11-09T18:00' }),
    ).toMatchObject({ from: '2026-11-09T16:00:00.000Z', to: '2026-11-09T21:00:59.000Z' });
  });

  it('los demás, con los nombres de la API', () => {
    expect(
      toAuditListFilters({
        ...EMPTY,
        usuario: 3,
        evento: 'deleted',
        entidad: 'patient',
        registro: 7,
        buscar: 'ana',
        pagina: 4,
      }),
    ).toEqual({
      from: null,
      to: null,
      user_id: 3,
      event: 'deleted',
      auditable_type: 'patient',
      auditable_id: 7,
      search: 'ana',
      page: 4,
    });
  });
});

const FORM: AuditFilterForm = { fromDate: '', fromTime: '', toDate: '', toTime: '', record: '' };

describe('validateAuditFilterForm: las reglas de la API antes de cambiar la URL', () => {
  it('vacío: sin filtros de fecha ni registro', () => {
    expect(validateAuditFilterForm(FORM, false)).toEqual({
      errors: {},
      values: { desde: undefined, hasta: undefined, registro: undefined },
    });
  });

  it('fecha sola y fecha con hora, como van en la URL', () => {
    expect(
      validateAuditFilterForm(
        { ...FORM, fromDate: '2026-11-09', toDate: '2026-11-09', toTime: '18:00', record: ' 12 ' },
        true,
      ).values,
    ).toEqual({ desde: '2026-11-09', hasta: '2026-11-09T18:00', registro: 12 });
  });

  it('"hasta" antes de "desde": el mensaje de la API en "hasta", sin valores', () => {
    expect(
      validateAuditFilterForm(
        {
          ...FORM,
          fromDate: '2026-11-09',
          fromTime: '18:00',
          toDate: '2026-11-09',
          toTime: '17:59',
        },
        false,
      ),
    ).toEqual({ errors: { toDate: AUDIT_TO_BEFORE_FROM_MESSAGE }, values: null });
  });

  it('"hasta" a la misma hora que "desde" pasa (incluye ese minuto)', () => {
    expect(
      validateAuditFilterForm(
        {
          ...FORM,
          fromDate: '2026-11-09',
          fromTime: '18:00',
          toDate: '2026-11-09',
          toTime: '18:00',
        },
        false,
      ).values,
    ).not.toBeNull();
  });

  it('una fecha a medio escribir: el mensaje de la forma de la API', () => {
    expect(validateAuditFilterForm({ ...FORM, fromBadInput: true }, false).errors).toEqual({
      fromDate: AUDIT_DATE_FORMAT_MESSAGE,
    });
    expect(validateAuditFilterForm({ ...FORM, toDate: '2026-02-30' }, false).errors).toEqual({
      toDate: AUDIT_DATE_FORMAT_MESSAGE,
    });
  });

  it('una hora sin la fecha', () => {
    expect(validateAuditFilterForm({ ...FORM, toTime: '10:00' }, false).errors).toEqual({
      toDate: AUDIT_TIME_WITHOUT_DATE_MESSAGE,
    });
  });

  it('el registro: solo dígitos, mayor que 0 y con el tipo', () => {
    expect(validateAuditFilterForm({ ...FORM, record: '12' }, false).errors).toEqual({
      record: AUDIT_TYPE_REQUIRED_MESSAGE,
    });
    expect(validateAuditFilterForm({ ...FORM, record: '0' }, true).errors).toEqual({
      record: AUDIT_RECORD_MESSAGE,
    });
    expect(validateAuditFilterForm({ ...FORM, record: '1e3' }, true).errors).toEqual({
      record: AUDIT_RECORD_MESSAGE,
    });
  });

  it('los mensajes son los de IndexAuditLogRequest', () => {
    const php = readFileSync(
      join(process.cwd(), '..', 'backend', 'app', 'Http', 'Requests', 'IndexAuditLogRequest.php'),
      'utf8',
    );

    for (const message of [
      AUDIT_DATE_FORMAT_MESSAGE,
      AUDIT_TO_BEFORE_FROM_MESSAGE,
      AUDIT_TYPE_REQUIRED_MESSAGE,
    ]) {
      expect(php).toContain(`'${message}'`);
    }
  });
});

describe('las listas de eventos y de tipos (H2: sin endpoint)', () => {
  it('los eventos son los de App\\Enums\\AuditEvent, en su orden y con el texto de label()', () => {
    const php = readFileSync(
      join(process.cwd(), '..', 'backend', 'app', 'Enums', 'AuditEvent.php'),
      'utf8',
    );
    const cases = new Map(
      Array.from(php.matchAll(/case (\w+) = '([a-z_]+)';/g), (match) => [match[1], match[2]]),
    );
    const labels = new Map(
      Array.from(php.matchAll(/self::(\w+) => '([^']+)'/g), (match) => [match[1], match[2]]),
    );
    const backend = Array.from(cases, ([name, value]) => ({ value, label: labels.get(name) }));

    expect(backend.length).toBeGreaterThan(0);
    expect(AUDIT_EVENTS.map((event) => ({ ...event }))).toEqual(backend);
  });

  it('los tipos son los de AuditLog::AUDITABLE_TYPES, en su orden', () => {
    const php = readFileSync(
      join(process.cwd(), '..', 'backend', 'app', 'Models', 'AuditLog.php'),
      'utf8',
    );
    const block = /AUDITABLE_TYPES = \[([^\]]+)\]/.exec(php)?.[1] ?? '';
    const backend = Array.from(block.matchAll(/'([a-z_]+)'/g), (match) => match[1]);

    expect(backend.length).toBeGreaterThan(0);
    expect([...AUDITABLE_TYPE_VALUES]).toEqual(backend);
  });
});
