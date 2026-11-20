import { formatTime, todayInAppZone } from '../../lib/format/dates';
import type { Me } from '../auth/types';
import type { ScheduleBlock } from '../schedules/types';
import type { QueueFilters } from './api';
import { dayName } from './dates';
import { appointmentWho, isInBlock } from './grid';
import type { QueueSearch } from './search';
import type { TransitionName } from './transitions';
import type { QueueAppointment } from './types';

/**
 * La cola del día (3.7), sin componentes: los filtros del pedido según el rol, las filas (en el orden de la API, con la marca
 * del bloqueo y la espera de los fichados) y el anuncio de lo que cambió entre una actualización y la siguiente. Las horas,
 * siempre en Asunción (`formatTime`, `todayInAppZone`), nunca con la zona de la PC.
 */

/** El aviso de `meta.truncated` (la API corta en 200: `AppointmentQueueService::LIMIT`). */
export const QUEUE_TRUNCATED_MESSAGE = 'Se muestran los primeros 200 turnos.';

/** El aviso de `meta.includes_unassigned` (la vista por defecto de quien tiene ficha). */
export const QUEUE_UNASSIGNED_NOTE = 'Incluye las agendas sin veterinario.';

/** El `message` de `APPOINTMENT_QUEUE_NO_BRANCH`: también sin pedir, si el usuario no tiene sucursal actual. */
export const QUEUE_NO_BRANCH_MESSAGE = 'Elegí tu sucursal actual para ver la cola del día.';

/** Hasta cuántos cambios se nombran uno por uno en el anuncio; con más, solo las cuentas. */
export const ANNOUNCED_CHANGES = 3;

/** ¿Tiene ficha de veterinario? Solo a él la API le da la vista por defecto (sus turnos y los de las agendas sin veterinario). */
export function hasOwnVeterinarian(me: Pick<Me, 'veterinarian'> | undefined): boolean {
  return me?.veterinarian !== null && me?.veterinarian !== undefined;
}

/**
 * Los filtros del pedido: el día de la URL (u hoy en Asunción) y `all` solo para quien tiene ficha y marcó "Ver toda la
 * sucursal" (`todos=1`). La recepción y el administrador no lo mandan: la API ya les da toda la sucursal. El veterinario y la
 * agenda no se filtran en la cola (van null en la clave, D4).
 */
export function queueFilters(
  search: QueueSearch,
  me: Pick<Me, 'veterinarian'> | undefined,
  now: Date = new Date(),
): QueueFilters {
  return {
    date: search.fecha ?? todayInAppZone(now),
    all: search.todos === 1 && hasOwnVeterinarian(me),
    veterinarian_id: null,
    schedule_id: null,
  };
}

/** Una fila de la cola: el turno, si está dentro de un bloqueo de su agenda y el texto de la espera (los fichados). */
export interface QueueRow {
  appointment: QueueAppointment;
  inBlock: boolean;
  waiting: string | null;
}

/** "menos de 1 min", "14 min", "1 h" o "1 h 05 min". */
export function waitingDuration(minutes: number): string {
  if (minutes < 1) {
    return 'menos de 1 min';
  }

  if (minutes < 60) {
    return `${String(minutes)} min`;
  }

  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;

  return rest === 0
    ? `${String(hours)} h`
    : `${String(hours)} h ${String(rest).padStart(2, '0')} min`;
}

/**
 * La espera de un fichado: "Esperando desde las 09:12 (14 min)" en la cola de hoy en Asunción; en la de otro día, "Fichado a
 * las 09:12" (contar desde ayer no dice nada). Sin fichar, null.
 */
export function waitingText(
  appointment: Pick<QueueAppointment, 'status' | 'checked_in_at'>,
  day: string,
  now: Date,
): string | null {
  if (appointment.status.value !== 'checked_in' || appointment.checked_in_at === null) {
    return null;
  }

  const since = formatTime(appointment.checked_in_at);

  if (day !== todayInAppZone(now)) {
    return `Fichado a las ${since}`;
  }

  const minutes = Math.max(
    0,
    Math.floor((now.getTime() - new Date(appointment.checked_in_at).getTime()) / 60_000),
  );

  return `Esperando desde las ${since} (${waitingDuration(minutes)})`;
}

/** La especie y la raza del paciente ("Canino · Mestizo"), o vacío. */
export function patientKind(appointment: Pick<QueueAppointment, 'patient'>): string {
  const patient = appointment.patient;

  if (patient === null) {
    return '';
  }

  return [patient.species?.name, patient.breed?.name]
    .filter((part): part is string => part !== undefined && part !== '')
    .join(' · ');
}

/**
 * Las filas de la cola, **en el orden de la API** (`starts_at` y después id: la hora del turno, no la de llegada), con la
 * marca "Dentro de un bloqueo" (los bloqueos de su agenda en el día; los estados que ocupan el horario) y la espera.
 */
export function buildQueue(
  items: readonly QueueAppointment[],
  blocksBySchedule: ReadonlyMap<number, readonly ScheduleBlock[]>,
  day: string,
  now: Date,
): QueueRow[] {
  return items.map((appointment) => ({
    appointment,
    inBlock: isInBlock(appointment, blocksBySchedule.get(appointment.schedule.id) ?? []),
    waiting: waitingText(appointment, day, now),
  }));
}

/** "Cola del martes 17 de noviembre: 5 turnos." (al entrar, al cambiar de día o de vista). */
export function queueTitleAnnouncement(day: string, count: number): string {
  const turns = count === 1 ? '1 turno' : `${String(count)} turnos`;

  return `Cola del ${dayName(day).toLowerCase()}: ${count === 0 ? 'sin turnos' : turns}.`;
}

function plural(count: number, one: string, many: string): string {
  return count === 1 ? `1 ${one}` : `${String(count)} ${many}`;
}

/**
 * Lo que cambió en la cola entre una actualización y la siguiente, para el `role="status"` (sin leer la cola entera): hasta
 * tres cambios, uno por uno ("Nuevo: 09:30, Rocky, de Carlos Duarte."); con más, las cuentas. Los cambios propios (`own`: el
 * id y el estado que devolvió la acción, que ya anunció su aviso) no se repiten. Sin cambios, null.
 */
export function queueChanges(
  previous: readonly QueueAppointment[],
  next: readonly QueueAppointment[],
  own: ReadonlyMap<number, string> = new Map(),
): string | null {
  const before = new Map(previous.map((appointment) => [appointment.id, appointment]));
  const nextIds = new Set(next.map((appointment) => appointment.id));
  const label = (appointment: QueueAppointment) =>
    [formatTime(appointment.starts_at), appointmentWho(appointment)]
      .filter((part) => part !== '')
      .join(', ');
  const added: string[] = [];
  const changed: string[] = [];
  const removed: string[] = [];

  for (const appointment of next) {
    const old = before.get(appointment.id);

    if (old === undefined) {
      added.push(`Nuevo: ${label(appointment)}`);
    } else if (
      old.status.value !== appointment.status.value &&
      own.get(appointment.id) !== appointment.status.value
    ) {
      changed.push(`${label(appointment)}: ${appointment.status.label}`);
    } else if (old.starts_at !== appointment.starts_at) {
      changed.push(`${label(appointment)}: cambió de horario`);
    }
  }

  for (const appointment of previous) {
    if (!nextIds.has(appointment.id)) {
      removed.push(`Salió de la cola: ${label(appointment)}`);
    }
  }

  const all = [...added, ...changed, ...removed];

  if (all.length === 0) {
    return null;
  }

  if (all.length <= ANNOUNCED_CHANGES) {
    return `La cola cambió. ${all.join('. ')}.`;
  }

  const counts = [
    added.length > 0 ? plural(added.length, 'turno nuevo', 'turnos nuevos') : '',
    changed.length > 0 ? plural(changed.length, 'turno cambió', 'turnos cambiaron') : '',
    removed.length > 0 ? plural(removed.length, 'turno salió', 'turnos salieron') : '',
  ].filter((part) => part !== '');

  return `La cola cambió: ${counts.join(', ')}.`;
}

/** Las acciones de la cola (diseño de la 3.7): fichar, atendido y ausente; las demás, en el detalle ("Ver el turno"). */
export const QUEUE_ACTIONS: readonly TransitionName[] = ['check_in', 'attend', 'no_show'];

/** El aviso de un día sin turnos en la cola. */
export const QUEUE_EMPTY_MESSAGE = 'No hay turnos en la cola de este día.';
