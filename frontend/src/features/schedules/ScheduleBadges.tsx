import type { Schedule } from './types';

/**
 * La marca "Inactivada" de una agenda ("Inactiva" parece el imperativo para el test de voseo, como en sucursales y
 * especies); nada si está activa.
 */
export function ScheduleInactiveBadge({ schedule }: { schedule: Pick<Schedule, 'is_active'> }) {
  return schedule.is_active ? null : (
    <span className="badge bg-secondary-lt schedule-inactive">Inactivada</span>
  );
}

/** El color de la agenda, como una muestra al lado del nombre (decorativa: el nombre ya la identifica). Sin color, nada. */
export function ScheduleColor({ color }: { color: string | null }) {
  return color === null ? null : (
    <span
      className="schedule-color d-inline-block rounded-circle border flex-shrink-0"
      style={{ backgroundColor: color, width: '0.875rem', height: '0.875rem' }}
      aria-hidden="true"
    />
  );
}
