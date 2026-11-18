/** Los turnos como los manda la API (AppointmentResource, el listado de GET appointments). */

/**
 * Los estados que ocupan el horario (`AppointmentStatus::occupiesSlot`): a confirmar, confirmado, fichado y atendido. Un
 * ausente o un cancelado lo liberan. La marca "Dentro de un bloqueo" usa los mismos que `affected_appointments` del alta del
 * bloqueo.
 */
export const OCCUPYING_STATUSES: ReadonlySet<string> = new Set([
  'pending_confirmation',
  'confirmed',
  'checked_in',
  'attended',
]);

/** Un ítem de GET appointments: los instantes en UTC (`Z`); el estado y el origen con su etiqueta en castellano. */
export interface Appointment {
  id: number;
  schedule: { id: number; name: string };
  branch: { id: number; name: string };
  veterinarian: { id: number; code: string; name: string } | null;
  service: { id: number; code: string; name: string } | null;
  patient: { id: number; code: string; name: string } | null;
  client: { id: number; display_name: string } | null;
  contact_name: string | null;
  contact_phone: string | null;
  starts_at: string;
  ends_at: string;
  status: { value: string; label: string };
  source: { value: string; label: string };
  is_overbooked: boolean;
  series_id: string | null;
  reason: string | null;
}
