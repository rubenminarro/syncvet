import { afterEach, describe, expect, it, vi } from 'vitest';
import { addClock, freeStart, slotRange } from '../../e2e/support/api';

// El horario libre de los ayudantes de Playwright (cierre de la F3, aislamiento): lo que ocupa vet-a en cualquier agenda (también
// un atendido, que la limpieza no cancela) no choca; sin horas fijas ni dependencia del orden de los specs.

type Api = Parameters<typeof freeStart>[0];

/** Una API falsa: los turnos de vet-a de cada día (hora de Asunción, UTC-3 todo el año). */
function apiWith(byDay: Record<string, [string, string, string][]>): Api {
  return {
    get: (path: string) => {
      const day = /from=(\d{4}-\d{2}-\d{2})/.exec(path)?.[1] ?? '';

      return Promise.resolve(
        (byDay[day] ?? []).map(([from, to, status]) => ({
          starts_at: new Date(`${day}T${from}:00-03:00`).toISOString(),
          ends_at: new Date(`${day}T${to}:00-03:00`).toISOString(),
          status: { value: status },
        })),
      );
    },
  } as unknown as Api;
}

const DAY = '2026-11-20';

afterEach(() => {
  vi.useRealTimers();
});

describe('freeStart', () => {
  it('saltea lo ocupado (también el atendido) y no lo cancelado ni el ausente', async () => {
    const api = apiWith({
      [DAY]: [
        ['00:00', '00:30', 'attended'],
        ['00:30', '01:00', 'cancelled'],
        ['01:00', '01:30', 'no_show'],
        ['01:30', '02:00', 'checked_in'],
      ],
    });

    // Tres medias horas seguidas: 00:30 choca a la 01:30 con el fichado; desde las 02:00, libres.
    await expect(
      freeStart(api, 4, { days: [DAY], offsets: [0, 30, 60], from: '00:00', to: '23:55' }),
    ).resolves.toBe('02:00');
    // Una sola: la de las 00:30 (el cancelado no ocupa).
    await expect(
      freeStart(api, 4, { days: [DAY], offsets: [0], from: '00:00', to: '23:55' }),
    ).resolves.toBe('00:30');
  });

  // 08:00, 09:00 y 09:30 chocan en algún día (con la hora siguiente); 10:00 y 11:00, libres en los dos.
  it('con varios días, la misma hora libre en todos', async () => {
    const api = apiWith({
      '2026-11-27': [['08:00', '08:30', 'confirmed']],
      '2026-12-04': [['09:00', '10:00', 'pending_confirmation']],
    });

    await expect(
      freeStart(api, 4, {
        days: ['2026-11-27', '2026-12-04'],
        offsets: [0, 60],
        from: '08:00',
        to: '20:00',
      }),
    ).resolves.toBe('10:00');
  });

  it('con started, solo un inicio que ya pasó; si no hay, un error que lo dice', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(`${DAY}T00:40:00-03:00`));
    const api = apiWith({ [DAY]: [['00:00', '00:30', 'attended']] });

    await expect(
      freeStart(api, 4, {
        days: [DAY],
        offsets: [0, 30],
        from: '00:00',
        to: '23:55',
        started: true,
      }),
    ).resolves.toBe('00:30');
    await expect(
      freeStart(apiWith({ [DAY]: [['00:00', '01:00', 'attended']] }), 4, {
        days: [DAY],
        offsets: [0],
        from: '00:00',
        to: '23:55',
        started: true,
      }),
    ).rejects.toThrow(
      'Sin un horario libre del veterinario 4 en 2026-11-20 (00:00 a 23:55, ya empezado).',
    );
  });

  it('addClock y slotRange', () => {
    expect(addClock('09:30', 90)).toBe('11:00');
    expect(slotRange('23:00')).toBe('23:00 a 23:30');
  });
});
