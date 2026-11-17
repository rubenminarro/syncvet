import type { Schedule, ScheduleDetail } from '../features/schedules/types';

/** Una agenda de `VET_DETAIL` en la Casa central (src/test/branches.ts), con el horario de la mañana y la tarde del lunes. */
export const ROOM_ONE: ScheduleDetail = {
  id: 7,
  name: 'Consultorio 1',
  branch: { id: 10, name: 'Casa central' },
  veterinarian: { id: 4, code: 'VET-0001', name: 'Víctor Acosta' },
  service: { id: 3, code: 'SRV-0001', name: 'Vacuna 5+1' },
  slot_minutes: 30,
  color: '#206BC4',
  is_active: true,
  hours: [
    { id: 1, weekday: 1, starts_at: '08:00', ends_at: '12:00' },
    { id: 2, weekday: 1, starts_at: '12:00', ends_at: '16:00' },
    { id: 3, weekday: 3, starts_at: '14:00', ends_at: '18:00' },
  ],
  upcoming_blocks: [],
  created_at: '2026-11-03T12:00:00.000000Z',
  updated_at: '2026-11-04T15:30:00.000000Z',
};

/** Otra, inactiva, de solo un servicio, sin color ni horario. */
export const GROOMING: ScheduleDetail = {
  id: 8,
  name: 'Peluquería',
  branch: { id: 11, name: 'Sucursal Norte' },
  veterinarian: null,
  service: { id: 3, code: 'SRV-0001', name: 'Vacuna 5+1' },
  slot_minutes: 60,
  color: null,
  is_active: false,
  hours: [],
  upcoming_blocks: [],
  created_at: '2026-11-05T12:00:00.000000Z',
  updated_at: '2026-11-05T12:00:00.000000Z',
};

/** Un ítem del listado (ScheduleResource): sin el horario, los bloqueos ni las fechas. */
export function scheduleListItem(detail: ScheduleDetail): Schedule {
  return {
    id: detail.id,
    name: detail.name,
    branch: detail.branch,
    veterinarian: detail.veterinarian,
    service: detail.service,
    slot_minutes: detail.slot_minutes,
    color: detail.color,
    is_active: detail.is_active,
  };
}
