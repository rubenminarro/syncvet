import { describe, expect, it } from 'vitest';
import { appointment, block } from '../../test/appointments';
import type { ScheduleHour } from '../schedules/types';
import {
  appointmentWho,
  buildDayGrid,
  isInBlock,
  selectColumns,
  type ColumnSchedule,
  type GridScheduleInput,
} from './grid';

// El martes 17/11/2026 (weekday 2). Los tests corren con la PC en UTC.
const DAY = '2026-11-17';

const ROOM_ONE: ColumnSchedule = {
  id: 7,
  name: 'Consultorio 1',
  color: '#206BC4',
  veterinarian: { id: 4, name: 'Víctor Acosta' },
  is_active: true,
};
const ROOM_TWO: ColumnSchedule = {
  id: 8,
  name: 'Consultorio 2',
  color: null,
  veterinarian: { id: 5, name: 'Valeria Ortiz' },
  is_active: true,
};
const GROOMING: ColumnSchedule = {
  id: 9,
  name: 'Peluquería',
  color: null,
  veterinarian: null,
  is_active: true,
};

const hour = (weekday: number, starts: string, ends: string, id = 1): ScheduleHour => ({
  id,
  weekday: weekday as ScheduleHour['weekday'],
  starts_at: starts,
  ends_at: ends,
});

function input(
  schedule: ColumnSchedule,
  hours: ScheduleHour[] | null = [hour(2, '08:00', '12:00'), hour(2, '14:00', '18:00', 2)],
  blocks: GridScheduleInput['blocks'] = [],
): GridScheduleInput {
  return { schedule, hours, blocks };
}

describe('buildDayGrid: el rango, las filas y lo sombreado', () => {
  it('una columna por agenda, en el orden recibido', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(ROOM_ONE), input(ROOM_TWO)],
      appointments: [],
      showCancelled: false,
    });

    expect(grid.columns.map((column) => column.schedule.name)).toEqual([
      'Consultorio 1',
      'Consultorio 2',
    ]);
  });

  it('el rango va del primer tramo al último del día de la semana, en filas de 15 minutos', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [
        input(ROOM_ONE, [hour(2, '09:00', '12:00'), hour(1, '06:00', '22:00', 2)]),
        input(ROOM_TWO, [hour(2, '10:00', '16:30')]),
      ],
      appointments: [],
      showCancelled: false,
    });

    expect(grid.start).toBe(9 * 60);
    expect(grid.end).toBe(17 * 60);
    expect(grid.rows).toHaveLength(32);
    expect(grid.rows[1]).toBe(9 * 60 + 15);
  });

  it('sin tramos ni turnos, de 08:00 a 18:00', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(GROOMING, [])],
      appointments: [],
      showCancelled: false,
    });

    expect([grid.start, grid.end]).toEqual([8 * 60, 18 * 60]);
    expect(grid.columns[0]?.closed).toEqual([{ start: 8 * 60, end: 18 * 60 }]);
  });

  it('un turno fuera del horario amplía el rango (a la hora entera)', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(ROOM_ONE)],
      appointments: [appointment(1, { at: '19:15', until: '19:45' })],
      showCancelled: false,
    });

    expect([grid.start, grid.end]).toEqual([8 * 60, 20 * 60]);
  });

  it('sombrea las horas sin atención (el hueco del mediodía y lo que sobra del rango) y une los tramos que se tocan', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [
        input(ROOM_ONE, [hour(2, '08:00', '10:00'), hour(2, '10:00', '12:00', 2)]),
        input(ROOM_TWO, [hour(2, '08:00', '12:00'), hour(2, '14:00', '18:00', 2)]),
      ],
      appointments: [],
      showCancelled: false,
    });

    expect(grid.columns[0]?.closed).toEqual([{ start: 12 * 60, end: 18 * 60 }]);
    expect(grid.columns[1]?.closed).toEqual([{ start: 12 * 60, end: 14 * 60 }]);
  });

  it('sin el horario todavía (el detalle no llegó), nada sombreado', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(ROOM_ONE, null)],
      appointments: [],
      showCancelled: false,
    });

    expect(grid.columns[0]?.closed).toEqual([]);
  });

  it('los bloqueos del día, recortados al rango y al día, con su motivo', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [
        input(ROOM_ONE, undefined, [
          block(1, '10:00', '11:30'),
          // Todo el día y el anterior: se recorta al rango (08:00 a 18:00).
          {
            ...block(2, '00:00', '00:00', null),
            starts_at: '2026-11-16T03:00:00.000Z',
            ends_at: '2026-11-18T03:00:00.000Z',
          },
        ]),
      ],
      appointments: [],
      showCancelled: false,
    });

    expect(grid.columns[0]?.blocks).toEqual([
      { id: 1, reason: 'Capacitación', start: 10 * 60, end: 11 * 60 + 30 },
      { id: 2, reason: null, start: 8 * 60, end: 18 * 60 },
    ]);
  });
});

describe('buildDayGrid: los turnos', () => {
  it('cada turno en su columna, con el inicio y el fin por la hora de pared de Asunción', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(ROOM_ONE), input(ROOM_TWO)],
      appointments: [
        appointment(1, { at: '09:00', until: '09:45' }),
        appointment(2, { at: '10:05', schedule: { id: 8, name: 'Consultorio 2' } }),
      ],
      showCancelled: false,
    });

    expect(grid.columns[0]?.items.map(({ start, end }) => [start, end])).toEqual([[540, 585]]);
    expect(grid.columns[1]?.items.map(({ start, end }) => [start, end])).toEqual([[605, 635]]);
  });

  it('los que se superponen (un sobreturno) van lado a lado; los que solo se tocan, no', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(ROOM_ONE)],
      appointments: [
        appointment(1, { at: '09:00', until: '10:00' }),
        appointment(2, { at: '09:30', until: '10:00', is_overbooked: true }),
        appointment(3, { at: '10:00', until: '10:30' }),
        appointment(4, { at: '09:15', until: '09:30', is_overbooked: true }),
      ],
      showCancelled: false,
    });
    const lanes = Object.fromEntries(
      (grid.columns[0]?.items ?? []).map((item) => [item.appointment.id, [item.lane, item.lanes]]),
    );

    expect(lanes).toEqual({ 1: [0, 2], 4: [1, 2], 2: [1, 2], 3: [0, 1] });
  });

  it('los cancelados ocultos; con "Mostrar los cancelados", también', () => {
    const appointments = [
      appointment(1, { at: '09:00' }),
      appointment(2, { at: '10:00', status: 'cancelled' }),
      appointment(3, { at: '11:00', status: 'no_show' }),
    ];
    const ids = (showCancelled: boolean) =>
      buildDayGrid({
        day: DAY,
        schedules: [input(ROOM_ONE)],
        appointments,
        showCancelled,
      }).columns[0]?.items.map((item) => item.appointment.id);

    expect(ids(false)).toEqual([1, 3]);
    expect(ids(true)).toEqual([1, 2, 3]);
  });

  it('un turno que cruzaría la medianoche (la API no lo permite) se corta a las 24:00 con la marca', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(ROOM_ONE)],
      appointments: [
        {
          ...appointment(1, { at: '23:30' }),
          ends_at: '2026-11-18T03:30:00.000000Z',
        },
      ],
      showCancelled: false,
    });

    expect(grid.end).toBe(24 * 60);
    expect(grid.columns[0]?.items[0]).toMatchObject({
      start: 23 * 60 + 30,
      end: 24 * 60,
      continuesNextDay: true,
    });
  });

  it('el día de 25 horas (23/03/2024): los dos turnos de las 23:30 (UTC-3 y UTC-4) caen en la misma fila, lado a lado', () => {
    const late = (id: number, starts: string, ends: string) => ({
      ...appointment(id, { day: '2024-03-23' }),
      starts_at: starts,
      ends_at: ends,
    });
    const grid = buildDayGrid({
      day: '2024-03-23',
      schedules: [input(ROOM_ONE, [hour(6, '08:00', '23:55')])],
      appointments: [
        late(1, '2024-03-24T02:30:00.000Z', '2024-03-24T03:00:00.000Z'),
        late(2, '2024-03-24T03:30:00.000Z', '2024-03-24T03:55:00.000Z'),
      ],
      showCancelled: false,
    });
    const items = grid.columns[0]?.items ?? [];

    expect(items.map((item) => [item.start, item.end, item.lanes])).toEqual([
      [1410, 1440, 2],
      [1410, 1435, 2],
    ]);
    expect(items.some((item) => item.continuesNextDay)).toBe(false);
  });

  it('el día de 23 horas (01/10/2023): un turno de las 08:00 va en la fila de las 08:00', () => {
    const grid = buildDayGrid({
      day: '2023-10-01',
      schedules: [input(ROOM_ONE, [hour(7, '08:00', '12:00')])],
      appointments: [
        {
          ...appointment(1),
          starts_at: '2023-10-01T11:00:00.000Z',
          ends_at: '2023-10-01T11:30:00.000Z',
        },
      ],
      showCancelled: false,
    });

    expect(grid.start).toBe(8 * 60);
    expect(grid.columns[0]?.items[0]?.start).toBe(8 * 60);
  });
});

describe('la marca "Dentro de un bloqueo"', () => {
  const blocks = [block(1, '10:00', '11:00')];

  it('un turno adentro la lleva; uno que solo toca el bloqueo, no; uno cancelado o ausente, tampoco', () => {
    expect(isInBlock(appointment(1, { at: '10:30' }), blocks)).toBe(true);
    expect(isInBlock(appointment(2, { at: '09:45', until: '10:15' }), blocks)).toBe(true);
    expect(isInBlock(appointment(3, { at: '09:30', until: '10:00' }), blocks)).toBe(false);
    expect(isInBlock(appointment(4, { at: '11:00' }), blocks)).toBe(false);
    expect(isInBlock(appointment(5, { at: '10:00', status: 'cancelled' }), blocks)).toBe(false);
    expect(isInBlock(appointment(6, { at: '10:00', status: 'no_show' }), blocks)).toBe(false);
    expect(isInBlock(appointment(7, { at: '10:00', status: 'checked_in' }), blocks)).toBe(true);
  });

  it('en la grilla, con los bloqueos de su propia agenda', () => {
    const grid = buildDayGrid({
      day: DAY,
      schedules: [input(ROOM_ONE, undefined, blocks), input(ROOM_TWO)],
      appointments: [
        appointment(1, { at: '10:00' }),
        appointment(2, { at: '10:00', schedule: { id: 8, name: 'Consultorio 2' } }),
        appointment(3, { at: '10:30', status: 'cancelled' }),
      ],
      showCancelled: true,
    });

    expect(grid.columns[0]?.items.map((item) => [item.appointment.id, item.inBlock])).toEqual([
      [1, true],
      [3, false],
    ]);
    expect(grid.columns[1]?.items[0]?.inBlock).toBe(false);
  });
});

describe('selectColumns: qué agendas se ven', () => {
  const active = [ROOM_ONE, ROOM_TWO, GROOMING];
  const none = { scheduleId: undefined, veterinarianId: undefined, ownVeterinarianId: null };

  it('sin filtros, todas las activas de la sucursal', () => {
    expect(selectColumns(active, [], none).map((s) => s.id)).toEqual([7, 8, 9]);
  });

  it('la vista por defecto del veterinario: sus agendas y las que no tienen veterinario', () => {
    expect(selectColumns(active, [], { ...none, ownVeterinarianId: 4 }).map((s) => s.id)).toEqual([
      7, 9,
    ]);
  });

  it('el filtro de veterinario: solo las suyas; el de agenda, una sola', () => {
    expect(selectColumns(active, [], { ...none, veterinarianId: 5 }).map((s) => s.id)).toEqual([8]);
    expect(selectColumns(active, [], { ...none, scheduleId: 9 }).map((s) => s.id)).toEqual([9]);
  });

  it('una agenda que ya no está activa pero tiene turnos ese día se suma al final (no se pierden)', () => {
    const columns = selectColumns(
      [ROOM_ONE],
      [appointment(1, { schedule: { id: 20, name: 'Consultorio viejo' } })],
      none,
    );

    expect(columns.map((s) => [s.id, s.is_active])).toEqual([
      [7, true],
      [20, false],
    ]);
  });
});

describe('appointmentWho', () => {
  it('el paciente con su dueño, el cliente o el contacto', () => {
    expect(appointmentWho(appointment(1))).toBe('Luna, de Laura Martínez');
    expect(appointmentWho(appointment(2, { patient: null }))).toBe('Laura Martínez');
    expect(
      appointmentWho(appointment(3, { patient: null, client: null, contact_name: 'Pedro Gómez' })),
    ).toBe('Pedro Gómez');
  });
});
