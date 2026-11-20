import type { Appointment } from '../features/appointments/types';
import type { ScheduleBlock } from '../features/schedules/types';

const STATUS_LABELS: Record<string, string> = {
  pending_confirmation: 'A confirmar',
  confirmed: 'Confirmado',
  checked_in: 'Fichado',
  attended: 'Atendido',
  no_show: 'Ausente',
  cancelled: 'Cancelado',
};

/**
 * Un turno del listado (AppointmentResource) en Consultorio 1 (src/test/schedules.ts) del martes 17/11/2026, de 09:00 a
 * 09:30 en Asunción (12:00 a 12:30 en UTC), con Luna de Laura Martínez. `at` y `until` son la hora de pared "HH:mm" de ese
 * día (UTC-3).
 */
export function appointment(
  id: number,
  overrides: Omit<Partial<Appointment>, 'status'> & {
    at?: string;
    until?: string;
    status?: string;
    day?: string;
  } = {},
): Appointment {
  const { at = '09:00', until, status = 'confirmed', day = '2026-11-17', ...rest } = overrides;
  const starts = new Date(`${day}T${at}:00-03:00`);
  const ends =
    until === undefined
      ? new Date(starts.getTime() + 30 * 60_000)
      : new Date(`${day}T${until}:00-03:00`);

  return {
    id,
    schedule: { id: 7, name: 'Consultorio 1' },
    branch: { id: 10, name: 'Casa central' },
    veterinarian: { id: 4, code: 'VET-0001', name: 'Víctor Acosta' },
    service: { id: 3, code: 'SRV-0001', name: 'Consulta' },
    patient: { id: 9, code: 'PAC-0009', name: 'Luna' },
    client: { id: 3, display_name: 'Laura Martínez' },
    contact_name: null,
    contact_phone: null,
    starts_at: starts.toISOString().replace('.000Z', '.000000Z'),
    ends_at: ends.toISOString().replace('.000Z', '.000000Z'),
    status: { value: status, label: STATUS_LABELS[status] ?? status },
    source: { value: 'reception', label: 'Recepción' },
    is_overbooked: false,
    series_id: null,
    reason: null,
    ...rest,
  };
}

/** Un bloqueo de la agenda 7 el 17/11/2026, entre dos horas de pared de Asunción. */
export function block(
  id: number,
  from: string,
  to: string,
  reason: string | null = 'Capacitación',
): ScheduleBlock {
  return {
    id,
    schedule_id: 7,
    starts_at: new Date(`2026-11-17T${from}:00-03:00`).toISOString(),
    ends_at: new Date(`2026-11-17T${to}:00-03:00`).toISOString(),
    reason,
    created_by: { id: 1, name: 'Ana Gómez' },
    created_at: '2026-11-10T12:00:00.000000Z',
  };
}
