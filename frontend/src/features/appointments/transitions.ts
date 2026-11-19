import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDate, formatTime, todayInAppZone } from '../../lib/format/dates';
import type { Me } from '../auth/types';
import { wallDay } from './dates';
import type { Appointment } from './types';

/**
 * Los estados del turno (3.5): la tabla de las acciones, espejo de `AppointmentTransition` del backend (origen, destino y
 * ruta), con el permiso de cada una (`AppointmentTransitionController`) y las reglas que el navegador conoce antes de pedir
 * (pregunta 14 del diseño de la F3: ocultas las de un turno ajeno y deshacer el ausente de otro día; deshabilitadas, con el
 * motivo, fichar sin paciente u otro día y el ausente antes del inicio). La API vuelve a controlar todo: "ahora" es la hora
 * del navegador y, si difiere de la del servidor, decide la API. La FASE 4 podrá sumar una entrada ("Abrir la consulta")
 * sin tocar las pantallas.
 */

/** Los valores de `AppointmentTransition` (backend). */
export type TransitionName =
  'confirm' | 'check_in' | 'attend' | 'no_show' | 'undo_no_show' | 'cancel';

export interface Transition {
  name: TransitionName;
  /** El botón ("Confirmar"). */
  label: string;
  /** El tramo de la ruta: POST appointments/{id}/<path>. */
  path: string;
  /** Los estados desde los que se admite (`AppointmentTransition::origins`). */
  from: readonly string[];
  /** El estado al que lleva (`AppointmentTransition::to`). */
  to: string;
  /** El permiso de la ruta (deshacer el ausente usa el del ausente). */
  permission: string;
  /** Con diálogo: cancelar (el motivo) y deshacer el ausente (la confirmación). Las demás van directo. */
  dialog: boolean;
}

export const TRANSITIONS: readonly Transition[] = [
  {
    name: 'confirm',
    label: 'Confirmar',
    path: 'confirm',
    from: ['pending_confirmation'],
    to: 'confirmed',
    permission: PERMISSIONS.appointmentsConfirm,
    dialog: false,
  },
  {
    name: 'check_in',
    label: 'Fichar',
    path: 'check-in',
    from: ['pending_confirmation', 'confirmed'],
    to: 'checked_in',
    permission: PERMISSIONS.appointmentsCheckIn,
    dialog: false,
  },
  {
    name: 'attend',
    label: 'Marcar atendido',
    path: 'attend',
    from: ['checked_in'],
    to: 'attended',
    permission: PERMISSIONS.appointmentsAttend,
    dialog: false,
  },
  {
    name: 'no_show',
    label: 'Ausente',
    path: 'no-show',
    from: ['pending_confirmation', 'confirmed'],
    to: 'no_show',
    permission: PERMISSIONS.appointmentsNoShow,
    dialog: false,
  },
  {
    name: 'undo_no_show',
    label: 'Deshacer el ausente',
    path: 'undo-no-show',
    from: ['no_show'],
    to: 'confirmed',
    permission: PERMISSIONS.appointmentsNoShow,
    dialog: true,
  },
  {
    name: 'cancel',
    label: 'Cancelar el turno',
    path: 'cancel',
    from: ['pending_confirmation', 'confirmed', 'checked_in'],
    to: 'cancelled',
    permission: PERMISSIONS.appointmentsCancel,
    dialog: true,
  },
];

/** El aviso de fichar un turno sin paciente (el `message` de `APPOINTMENT_PATIENT_REQUIRED`, más corto). */
export const PATIENT_REQUIRED_HINT = 'Para fichar, cargá el paciente.';

/** El aviso de fichar otro día (el `message` de `APPOINTMENT_CHECK_IN_NOT_TODAY`). */
export const NOT_TODAY_HINT = 'Solo se fichan los turnos del día.';

/** Lo que una acción necesita saber del turno. */
export type TransitionAppointment = Pick<
  Appointment,
  'status' | 'starts_at' | 'patient' | 'veterinarian'
>;

/** Quién pide y cuándo: `can` del usuario, su ficha y sus roles, y "ahora" (la hora del navegador). */
export interface TransitionContext {
  can: (permission: string) => boolean;
  me: Pick<Me, 'roles' | 'veterinarian'> | undefined;
  now: Date;
}

/** Una acción que se muestra: con `disabledReason`, deshabilitada con el motivo a la vista. */
export interface AvailableTransition {
  transition: Transition;
  disabledReason: string | null;
  /** El motivo es la falta del paciente: el aviso lleva a cargarlo. */
  needsPatient: boolean;
}

/**
 * ¿El turno es de otro veterinario para quien pide? (`AppointmentTransitionService::ensureOwnAppointment`, pregunta 18): sin
 * `appointments.update`, un usuario con ficha marca solo los turnos de su ficha y los de agendas sin veterinario; uno sin
 * ficha y con el rol veterinario, solo los de agendas sin veterinario. El administrador y la recepción, cualquiera.
 */
export function isSomeoneElses(
  appointment: TransitionAppointment,
  context: TransitionContext,
): boolean {
  if (context.can(PERMISSIONS.appointmentsUpdate) || appointment.veterinarian === null) {
    return false;
  }

  const own = context.me?.veterinarian ?? null;

  if (own === null) {
    return context.me?.roles.includes('veterinario') ?? false;
  }

  return own.id !== appointment.veterinarian.id;
}

/** "Se marca desde las 09:30." (hoy) o "Se marca desde el 20/11/2026 a las 09:30." (otro día). */
export function noShowHint(startsAt: string, now: Date): string {
  return wallDay(startsAt) === todayInAppZone(now)
    ? `Se marca desde las ${formatTime(startsAt)}.`
    : `Se marca desde el ${formatDate(startsAt)} a las ${formatTime(startsAt)}.`;
}

/** Por qué no se puede todavía (deshabilitada), o null. */
function disabledReasonOf(
  transition: Transition,
  appointment: TransitionAppointment,
  now: Date,
): { reason: string; needsPatient: boolean } | null {
  switch (transition.name) {
    case 'check_in':
      // El orden de la API: el paciente antes que el día.
      if (appointment.patient === null) {
        return { reason: PATIENT_REQUIRED_HINT, needsPatient: true };
      }

      return wallDay(appointment.starts_at) === todayInAppZone(now)
        ? null
        : { reason: NOT_TODAY_HINT, needsPatient: false };
    case 'no_show':
      return new Date(appointment.starts_at).getTime() > now.getTime()
        ? { reason: noShowHint(appointment.starts_at, now), needsPatient: false }
        : null;
    default:
      return null;
  }
}

/** ¿Se oculta por una regla conocida? El turno ajeno (fichar y atendido) y deshacer el ausente de otro día. */
function hiddenByRule(
  transition: Transition,
  appointment: TransitionAppointment,
  context: TransitionContext,
): boolean {
  switch (transition.name) {
    case 'check_in':
    case 'attend':
      return isSomeoneElses(appointment, context);
    case 'undo_no_show':
      return wallDay(appointment.starts_at) !== todayInAppZone(context.now);
    default:
      return false;
  }
}

/**
 * Las acciones que se muestran para el turno, en el orden de la tabla: las que admite su estado, con el permiso y sin una
 * regla que las oculte; cada una, habilitada o con su motivo.
 */
export function availableTransitions(
  appointment: TransitionAppointment,
  context: TransitionContext,
): AvailableTransition[] {
  const result: AvailableTransition[] = [];

  for (const transition of TRANSITIONS) {
    if (
      !transition.from.includes(appointment.status.value) ||
      !context.can(transition.permission) ||
      hiddenByRule(transition, appointment, context)
    ) {
      continue;
    }

    const disabled = disabledReasonOf(transition, appointment, context.now);
    result.push({
      transition,
      disabledReason: disabled?.reason ?? null,
      needsPatient: disabled?.needsPatient ?? false,
    });
  }

  return result;
}

/** Los mensajes del motivo de la cancelación (`CancelAppointmentRequest::messages`). */
export const CANCELLATION_MESSAGES = {
  required: 'Ingresá el motivo de la cancelación.',
  min: 'El motivo de la cancelación debe tener al menos 3 caracteres.',
  max: 'El motivo de la cancelación no debe tener más de 500 caracteres.',
} as const;

export const CANCELLATION_REASON_MIN = 3;
export const CANCELLATION_REASON_MAX = 500;

/**
 * El motivo de la cancelación, con la regla de la API (`required|string|min:3|max:500`, en caracteres, como `mb_strlen`;
 * Laravel recorta los espacios de los extremos y un texto vacío es "requerido"). null: válido.
 */
export function cancellationReasonError(reason: string): string | null {
  // En puntos de código, como mb_strlen (no en unidades UTF-16).
  const length = Array.from(reason.trim()).length;

  if (length === 0) {
    return CANCELLATION_MESSAGES.required;
  }

  if (length < CANCELLATION_REASON_MIN) {
    return CANCELLATION_MESSAGES.min;
  }

  return length > CANCELLATION_REASON_MAX ? CANCELLATION_MESSAGES.max : null;
}

/** Qué hace la acción con su error (3.5): el texto del diálogo, si otro intento daría lo mismo y lo que se ofrece. */
export interface TransitionFailure {
  message: string;
  /** Otro intento daría lo mismo (un 422 o un error de negocio): queda solo "Cerrar". */
  final: boolean;
  /** El error es del motivo de la cancelación (un 422 en `cancellation_reason`): va al campo. */
  reasonError: string | null;
  /** 409 `APPOINTMENT_OVERLAP` al deshacer el ausente: con `appointments.overbook`, "Volver como sobreturno". */
  overlap: boolean;
  /** 422 `APPOINTMENT_PATIENT_REQUIRED`: el diálogo lleva a cargar el paciente. */
  needsPatient: boolean;
  /** El turno pudo cambiar en otra PC: se vuelve a pedir (el detalle y la agenda). */
  refresh: boolean;
  /** 404: ya no existe (el aviso, y se cierra). */
  gone: boolean;
}

/**
 * El error de una acción de estado (frontend/CLAUDE.md §6 y la tabla de la F3): el 409 `CONFLICT` (la fila cambió en otra PC)
 * y los de la red o el servidor dejan probar de nuevo, sin perder el motivo escrito; los 422 de negocio
 * (`APPOINTMENT_INVALID_TRANSITION`, `_CHECK_IN_NOT_TODAY`, `_NO_SHOW_TOO_EARLY`…), el 403 `APPOINTMENT_NOT_YOURS` y
 * `FORBIDDEN`, el mensaje y "Cerrar". Todos, salvo la red, vuelven a pedir el turno.
 */
export function transitionFailure(error: unknown): TransitionFailure {
  const base: TransitionFailure = {
    message: MESSAGES.programming,
    final: false,
    reasonError: null,
    overlap: false,
    needsPatient: false,
    refresh: false,
    gone: false,
  };

  if (!isApiError(error)) {
    return base;
  }

  const treatment = errorTreatment(error.errorCode);
  const message = userMessage(error);

  switch (treatment) {
    case 'not-found':
      return { ...base, message: error.message, final: true, gone: true };
    case 'validation': {
      const fields = error.fieldErrors();
      const reason = fields.cancellation_reason?.join(' ') ?? null;
      const all = Object.values(fields).flat();

      return {
        ...base,
        message: reason ?? (all.length > 0 ? all.join(' ') : error.message),
        final: true,
        reasonError: reason,
        refresh: reason === null,
      };
    }
    case 'conflict':
      return { ...base, message, refresh: true };
    case 'network':
      return { ...base, message };
    case 'server':
      return { ...base, message, refresh: true };
    case 'business':
    case 'forbidden':
      return {
        ...base,
        message,
        final: true,
        overlap: error.errorCode === 'APPOINTMENT_OVERLAP',
        needsPatient: error.errorCode === 'APPOINTMENT_PATIENT_REQUIRED',
        refresh: true,
      };
    default:
      return { ...base, message, final: true };
  }
}
