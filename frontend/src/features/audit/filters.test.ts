import { describe, expect, it } from 'vitest';
import { validateAuditFilterForm } from './filters';
const EMPTY = { fromDate: '', fromTime: '', toDate: '', toTime: '', record: '' };

describe('deuda 28: los invisibles de los bordes se recortan como en la API (trimLikeApi)', () => {
  it('el registro "\\u200B12" es el 12, como lo lee la API', () => {
    expect(validateAuditFilterForm({ ...EMPTY, record: '\u200B12' }, true)).toEqual({
      errors: {},
      values: { desde: undefined, hasta: undefined, registro: 12 },
    });
  });
});
