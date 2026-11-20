/** Las agendas como las manda la API (ScheduleResource y ShowScheduleResource). */

/** Los días del horario: `weekday` 1 = lunes … 7 = domingo (`ScheduleMessages::WEEKDAYS`). */
export const WEEKDAYS = [
  { value: 1, label: 'Lunes', lower: 'lunes' },
  { value: 2, label: 'Martes', lower: 'martes' },
  { value: 3, label: 'Miércoles', lower: 'miércoles' },
  { value: 4, label: 'Jueves', lower: 'jueves' },
  { value: 5, label: 'Viernes', lower: 'viernes' },
  { value: 6, label: 'Sábado', lower: 'sábado' },
  { value: 7, label: 'Domingo', lower: 'domingo' },
] as const;

export type Weekday = (typeof WEEKDAYS)[number]['value'];

/** Un ítem de GET schedules (ScheduleResource): sin el horario (H3 del diseño de la F3). */
export interface Schedule {
  id: number;
  name: string;
  branch: { id: number; name: string };
  /** El veterinario con el nombre de su usuario, o null (una agenda de solo un servicio). */
  veterinarian: { id: number; code: string; name: string } | null;
  service: { id: number; code: string; name: string } | null;
  slot_minutes: number;
  /** `#RRGGBB` en mayúsculas, o null. */
  color: string | null;
  is_active: boolean;
}

/** Un tramo del horario semanal: horas de reloj de Asunción ("08:00"), sin fecha. */
export interface ScheduleHour {
  id: number;
  weekday: Weekday;
  starts_at: string;
  ends_at: string;
}

/** Un bloqueo de la agenda (ScheduleBlockResource): los instantes en UTC. Se muestran desde la 3.1b. */
export interface ScheduleBlock {
  id: number;
  schedule_id: number;
  starts_at: string;
  ends_at: string;
  reason: string | null;
  created_by: { id: number; name: string } | null;
  created_at: string | null;
}

/** GET schedules/{id} (ShowScheduleResource): suma el horario, los próximos bloqueos (hasta 20) y las fechas. */
export interface ScheduleDetail extends Schedule {
  hours: ScheduleHour[];
  upcoming_blocks: ScheduleBlock[];
  created_at: string | null;
  updated_at: string | null;
}

/** Los filtros de GET schedules, con los nombres de la API (la clave de la consulta). */
export interface ScheduleListFilters {
  search: string;
  branch_id: number | null;
  /** Sin él, solo las activas (lo de la API sin filtro). */
  include_inactive: boolean;
  page: number;
}

/** Un tramo en el cuerpo: sin id (el horario se reemplaza entero). */
export interface ScheduleHourPayload {
  weekday: number;
  starts_at: string;
  ends_at: string;
}

/**
 * El cuerpo del alta y de la edición: el formulario completo, con el horario entero (`hours: []` lo vacía). `is_active` no
 * va (el alta queda activa; se cambia con su botón en el detalle).
 */
export interface SchedulePayload {
  branch_id: number;
  name: string;
  veterinarian_id: number | null;
  service_id: number | null;
  slot_minutes: number;
  color: string | null;
  hours: ScheduleHourPayload[];
}

/** El cuerpo de POST schedules/{id}/blocks: los instantes con su desplazamiento (`toApiDateTime`) y el motivo o null. */
export interface ScheduleBlockPayload {
  starts_at: string;
  ends_at: string;
  reason: string | null;
}

/**
 * Un turno que quedó dentro del bloqueo recién creado (StoredScheduleBlockResource, pregunta 22 del backend): los de la
 * agenda que ocupan el horario (a confirmar, confirmado, fichado y atendido) y se superponen con él. La API no los cambia
 * ni rechaza el bloqueo: los lista para que la recepción los reprograme o los cancele.
 */
export interface AffectedAppointment {
  id: number;
  starts_at: string;
  ends_at: string;
  status: { value: string; label: string };
  patient: { id: number; code: string; name: string } | null;
  client: { id: number; display_name: string } | null;
  contact_name: string | null;
  contact_phone: string | null;
}

/** La respuesta del alta (201): el bloqueo con los turnos que quedaron adentro (vacío si no hay). */
export interface StoredScheduleBlock extends ScheduleBlock {
  affected_appointments: AffectedAppointment[];
}
