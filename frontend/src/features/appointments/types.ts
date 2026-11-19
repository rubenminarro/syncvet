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

/** Los estados que se editan y se mueven (`AppointmentService::EDITABLE`): a confirmar y confirmado. */
export const EDITABLE_STATUSES: ReadonlySet<string> = new Set([
  'pending_confirmation',
  'confirmed',
]);

/** GET appointments/{id} (ShowAppointmentResource): suma las notas, quién lo creó y las horas de cada estado (UTC). */
export interface AppointmentDetail extends Appointment {
  notes: string | null;
  created_by: { id: number; name: string } | null;
  confirmed_at: string | null;
  checked_in_at: string | null;
  attended_at: string | null;
  no_show_at: string | null;
  cancelled_at: string | null;
  cancelled_by: { id: number; name: string } | null;
  cancellation_reason: string | null;
  created_at: string | null;
}

/** Los orígenes que acepta la API (`AppointmentMessages::sources()`: sin la reserva online). */
export type AppointmentSource = 'reception' | 'phone' | 'whatsapp';

/**
 * El cuerpo de POST appointments y de PATCH appointments/{id} (3.4): uno de los tres (paciente, cliente o contacto; los demás,
 * null), los instantes con el desplazamiento de Asunción (`toApiDateTime`) y `overbook` solo después del 409 y con el permiso.
 * La edición manda solo lo que cambió (`AppointmentUpdatePayload`).
 */
export interface AppointmentPayload {
  schedule_id: number;
  service_id: number | null;
  patient_id: number | null;
  client_id: number | null;
  contact_name: string | null;
  contact_phone: string | null;
  starts_at: string;
  ends_at: string;
  source: AppointmentSource;
  reason: string | null;
  notes: string | null;
  overbook?: true;
}

/** El cuerpo de PATCH appointments/{id}: solo lo que cambió (la API valida solo lo que llega, P7). */
export type AppointmentUpdatePayload = Partial<AppointmentPayload>;

/** GET schedules/{id}/availability: los horarios que el alta aceptaría, día por día (UTC). */
export interface Availability {
  schedule_id: number;
  duration_minutes: number;
  days: { date: string; slots: { starts_at: string; ends_at: string }[] }[];
}
