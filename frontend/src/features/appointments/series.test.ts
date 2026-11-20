import { describe, expect, it } from 'vitest';
import { ApiError } from '../../lib/api/errors';
import {
  conflictsByDay,
  oneYearAfter,
  previewConflictText,
  onlyOverlaps,
  recurrencePayload,
  scopeAffected,
  SERIES_MESSAGES,
  seriesConflicts,
  seriesCount,
  seriesError,
  seriesOccurrences,
  seriesPosition,
  seriesSummary,
  type SeriesInput,
} from './series';

/** Los tests corren con la PC en UTC (vite.config.ts): una cuenta con la zona de la PC daría otras horas. */

const weekly = (firstDay: string, count: number, everyWeeks = 1): SeriesInput => ({
  firstDay,
  everyWeeks,
  end: 'count',
  count,
  until: null,
});

const raw = (overrides: Partial<Parameters<typeof seriesError>[0]> = {}) => ({
  firstDay: '2026-11-17',
  everyWeeks: '1',
  end: 'count' as const,
  count: '4',
  until: '',
  ...overrides,
});

describe('las fechas de la serie', () => {
  it('semanal por cantidad: el mismo día de la semana y la misma hora de Asunción', () => {
    expect(seriesOccurrences(weekly('2026-11-17', 4), '09:30', 30)).toEqual([
      {
        day: '2026-11-17',
        starts_at: '2026-11-17T09:30:00.000-03:00',
        ends_at: '2026-11-17T10:00:00.000-03:00',
      },
      {
        day: '2026-11-24',
        starts_at: '2026-11-24T09:30:00.000-03:00',
        ends_at: '2026-11-24T10:00:00.000-03:00',
      },
      {
        day: '2026-12-01',
        starts_at: '2026-12-01T09:30:00.000-03:00',
        ends_at: '2026-12-01T10:00:00.000-03:00',
      },
      {
        day: '2026-12-08',
        starts_at: '2026-12-08T09:30:00.000-03:00',
        ends_at: '2026-12-08T10:00:00.000-03:00',
      },
    ]);
  });

  it('cada 2 semanas, cruzando el fin de año', () => {
    expect(
      seriesOccurrences(weekly('2026-12-21', 3, 2), '08:00', 60).map((item) => item.day),
    ).toEqual(['2026-12-21', '2027-01-04', '2027-01-18']);
  });

  it('cruza el 23/03/2024 (25 horas): la hora de pared se conserva y el desplazamiento cambia', () => {
    const dates = seriesOccurrences(weekly('2024-03-16', 3), '09:30', 30);

    expect(dates.map((item) => item.starts_at)).toEqual([
      '2024-03-16T09:30:00.000-03:00',
      '2024-03-23T09:30:00.000-03:00',
      '2024-03-30T09:30:00.000-04:00',
    ]);
    // Siempre 30 minutos, también del otro lado del cambio.
    expect(dates[2]?.ends_at).toBe('2024-03-30T10:00:00.000-04:00');
  });

  it('cruza el 01/10/2023 (23 horas): la misma hora de pared', () => {
    expect(
      seriesOccurrences(weekly('2023-09-24', 2), '09:30', 30).map((item) => item.starts_at),
    ).toEqual(['2023-09-24T09:30:00.000-04:00', '2023-10-01T09:30:00.000-03:00']);
  });

  it('por fecha de fin: la fecha de fin entra, la del día anterior no', () => {
    const until = (date: string): SeriesInput => ({
      firstDay: '2026-11-16',
      everyWeeks: 2,
      end: 'until',
      count: null,
      until: date,
    });

    expect(seriesCount(until('2026-12-28'))).toBe(4);
    expect(seriesCount(until('2026-12-27'))).toBe(3);
    expect(seriesOccurrences(until('2026-12-28'), '11:00', 30).at(-1)?.day).toBe('2026-12-28');
  });

  it('el cuerpo de recurrence: uno de count o until', () => {
    expect(recurrencePayload(weekly('2026-11-17', 8, 2))).toEqual({
      every_weeks: 2,
      count: 8,
      until: null,
    });
    expect(
      recurrencePayload({
        firstDay: '2026-11-17',
        everyWeeks: 1,
        end: 'until',
        count: 9,
        until: '2027-01-12',
      }),
    ).toEqual({ every_weeks: 1, count: null, until: '2027-01-12' });
  });
});

describe('los límites de la API', () => {
  it('de 2 a 52 turnos', () => {
    // 52 semanales: la última, 357 días después (dentro del año).
    expect(seriesError(raw({ count: '52' }))).toBeNull();
    expect(seriesError(raw({ count: '53' }))).toEqual({
      field: 'count',
      message: SERIES_MESSAGES.tooMany,
    });
    expect(seriesError(raw({ count: '1' }))).toEqual({
      field: 'count',
      message: SERIES_MESSAGES.count,
    });
    expect(seriesError(raw({ count: '' }))).toEqual({
      field: 'count',
      message: SERIES_MESSAGES.count,
    });
  });

  it('cada 1 a 12 semanas', () => {
    expect(seriesError(raw({ everyWeeks: '12', count: '2' }))).toBeNull();
    expect(seriesError(raw({ everyWeeks: '0' }))?.field).toBe('every_weeks');
    expect(seriesError(raw({ everyWeeks: '13' }))).toEqual({
      field: 'every_weeks',
      message: SERIES_MESSAGES.everyWeeks,
    });
  });

  it('con cantidad, la última fecha como mucho un año después', () => {
    // Cada 2 semanas: 27 turnos terminan 364 días después; 28, 378.
    expect(seriesError(raw({ everyWeeks: '2', count: '27' }))).toBeNull();
    expect(seriesError(raw({ everyWeeks: '2', count: '28' }))).toEqual({
      field: 'count',
      message: SERIES_MESSAGES.countTooFar,
    });
  });

  it('hasta: desde el primer turno, a un año y no a un año y un día', () => {
    const until = (date: string, everyWeeks = '2') =>
      seriesError(raw({ end: 'until', everyWeeks, until: date }));

    expect(until('2027-11-17')).toBeNull();
    expect(until('2027-11-18')).toEqual({ field: 'until', message: SERIES_MESSAGES.untilTooFar });
    expect(until('2026-11-16')).toEqual({ field: 'until', message: SERIES_MESSAGES.untilBefore });
    // El mismo día: un solo turno.
    expect(until('2026-11-17')).toEqual({ field: 'until', message: SERIES_MESSAGES.tooFew });
    // Semanal a un año: 53 turnos.
    expect(until('2027-11-17', '1')).toEqual({ field: 'until', message: SERIES_MESSAGES.tooMany });
    expect(until('')).toEqual({ field: 'until', message: SERIES_MESSAGES.untilFormat });
  });

  it('un año después, como Carbon (el 29/02 da el 01/03)', () => {
    expect(oneYearAfter('2026-11-17')).toBe('2027-11-17');
    expect(oneYearAfter('2028-02-29')).toBe('2029-03-01');
  });
});

describe('el resumen', () => {
  it('semanal y cada N semanas', () => {
    expect(seriesSummary(seriesOccurrences(weekly('2026-11-17', 9), '09:30', 30), 1)).toBe(
      '9 turnos, los martes a las 09:30, hasta el 12/01/2027',
    );
    expect(seriesSummary(seriesOccurrences(weekly('2026-11-16', 4, 2), '10:00', 30), 2)).toBe(
      '4 turnos, cada 2 semanas, los lunes a las 10:00, hasta el 28/12/2026',
    );
    expect(seriesSummary([], 1)).toBe('');
  });
});

describe('los conflictos', () => {
  const conflict = (conflicts: unknown) =>
    new ApiError({
      status: 409,
      errorCode: 'APPOINTMENT_SERIES_CONFLICT',
      message: 'Hay fechas de la serie que no se pueden dar: revisá la lista.',
      errors: { conflicts },
    });

  it('la lista de errors.conflicts, sin las entradas mal formadas', () => {
    const list = [
      {
        starts_at: '2026-11-24T12:30:00.000000Z',
        reason: 'overlap',
        message: 'El horario ya está ocupado.',
      },
      { starts_at: 5, reason: 'overlap', message: 'x' },
    ];

    expect(seriesConflicts(conflict(list))).toEqual([list[0]]);
    expect(seriesConflicts(conflict('nada'))).toEqual([]);
    expect(
      seriesConflicts(
        new ApiError({ status: 409, errorCode: 'APPOINTMENT_OVERLAP', message: 'x' }),
      ),
    ).toBeNull();
  });

  it('el sobreturno sirve solo si todas son superposiciones', () => {
    const item = (reason: string) => ({ starts_at: '2026-11-24T12:30:00Z', reason, message: '' });

    expect(onlyOverlaps([item('overlap'), item('overlap')])).toBe(true);
    expect(onlyOverlaps([item('overlap'), item('in_block')])).toBe(false);
    expect(onlyOverlaps([])).toBe(false);
  });
});

describe('los conflictos en la vista previa', () => {
  const occurrences = seriesOccurrences(weekly('2026-11-24', 3), '09:30', 30);
  const at = (starts_at: string) => ({ starts_at, reason: 'overlap', message: 'Ocupado.' });

  it('por el instante de inicio, con la Z de la API o con el desplazamiento', () => {
    const found = conflictsByDay(occurrences, [
      at('2026-12-01T12:30:00.000000Z'),
      // Otra hora del 08/12: no es la de la serie, no se marca.
      at('2026-12-08T13:00:00.000000Z'),
    ]);

    expect([...found.keys()]).toEqual(['2026-12-01']);
    expect(conflictsByDay(occurrences, [at('2026-11-24T09:30:00-03:00')]).has('2026-11-24')).toBe(
      true,
    );
    expect(conflictsByDay(occurrences, []).size).toBe(0);
  });

  it('el anuncio, en singular y en plural', () => {
    expect(previewConflictText(0, 4)).toBe('');
    expect(previewConflictText(1, 4)).toBe(
      '1 de las 4 fechas no se puede dar: está marcada en la lista.',
    );
    expect(previewConflictText(3, 8)).toBe(
      '3 de las 8 fechas no se pueden dar: están marcadas en la lista.',
    );
  });
});

describe('el alcance de la cancelación', () => {
  const now = new Date('2026-11-20T12:00:00Z');
  const turn = (id: number, startsAt: string, status = 'confirmed') => ({
    id,
    starts_at: startsAt,
    status: { value: status, label: status },
  });
  const series = [
    turn(1, '2026-11-13T12:00:00Z'), // ya empezó
    turn(2, '2026-11-20T15:00:00Z'), // el pedido
    turn(3, '2026-11-27T12:00:00Z', 'pending_confirmation'),
    turn(4, '2026-12-04T12:00:00Z', 'cancelled'),
    turn(5, '2026-12-11T12:00:00Z'),
    turn(9, '2026-11-20T15:00:00Z'), // movido al mismo instante, id mayor: es de los siguientes
    turn(6, '2026-11-18T12:00:00Z', 'pending_confirmation'), // anterior, ya empezó
  ];
  const requested = series[1] ?? turn(2, '');

  it('solo este, este y los siguientes, toda la serie', () => {
    expect(scopeAffected(requested, series, 'single', now)).toBe(1);
    // El pedido, el 3, el 5 y el 9 (el 4 está cancelado).
    expect(scopeAffected(requested, series, 'following', now)).toBe(4);
    expect(scopeAffected(requested, series, 'series', now)).toBe(4);
  });

  it('toda la serie suma los anteriores que no empezaron; los siguientes no', () => {
    const later = [...series, turn(7, '2026-11-19T12:00:00Z'), turn(8, '2026-11-21T09:00:00Z')];
    const from = later.find((item) => item.id === 5) ?? requested;

    // El 8 (anterior al 5, sin empezar) entra con toda la serie, no con "este y los siguientes".
    expect(scopeAffected(from, later, 'following', now)).toBe(1);
    expect(scopeAffected(from, later, 'series', now)).toBe(5);
  });

  it('el lugar del turno en la serie', () => {
    expect(seriesPosition({ id: 3 }, series)).toEqual({ index: 5, total: 7 });
    expect(seriesPosition({ id: 99 }, series)).toBeNull();
  });
});

describe('deuda 28: los invisibles de los bordes se recortan como en la API (trimLikeApi)', () => {
  it('la cantidad "\\u200B4" es 4, como la lee la API', () => {
    expect(seriesError(raw({ count: '\u200B4' }))).toBeNull();
  });
});
