import { z } from 'zod';
import { errorTreatment, type ApiError } from '../../lib/api/errors';
import { normalizePhone, PHONE_PATTERN } from '../../lib/phone';
import { parseDay } from './dates';
import { recurrencePayload, seriesError, type SeriesEnd, type SeriesInput } from './series';
import {
  appointmentTimes,
  crossesMidnight,
  instantToWall,
  minutesBetween,
  parseClock,
  sameInstant,
} from './times';
import type {
  AppointmentDetail,
  AppointmentPayload,
  AppointmentSource,
  AppointmentUpdatePayload,
} from './types';
import { trimLikeApi } from '../../lib/format/text';

/**
 * El formulario del turno (3.4): el alta y la edición (también "Reprogramar", el mismo formulario con el foco en el
 * horario). Las reglas que el navegador puede repetir, con los mensajes de `AppointmentMessages`; el horario semanal, los
 * bloqueos y la superposición los decide la API (diseño de la F3, "Las reglas que el navegador puede repetir y las que no").
 */

/** Los mensajes de la API (`AppointmentMessages`), tal cual. */
export const APPOINTMENT_MESSAGES = {
  scheduleRequired: 'La agenda es obligatoria.',
  whoRequired: 'Ingresá el paciente, el cliente o un nombre y un teléfono de contacto.',
  contactTogether: 'Ingresá el nombre y el teléfono de contacto juntos.',
  contactNameMin: 'El nombre de contacto debe tener al menos 2 caracteres.',
  contactNameMax: 'El nombre de contacto no debe tener más de 150 caracteres.',
  contactNameFormat: 'El nombre de contacto debe tener al menos una letra.',
  contactPhoneFormat:
    'El teléfono de contacto debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).',
  startRequired: 'El inicio del turno es obligatorio.',
  pastDay: 'No podés dar un turno en un día que ya pasó.',
  offGrid: 'La hora debe estar en múltiplos de 5 minutos, sin segundos.',
  endsBeforeStart: 'El fin del turno debe ser posterior a su inicio.',
  tooLong: 'El turno no puede durar más de 720 minutos.',
  crossesMidnight: 'El turno tiene que empezar y terminar el mismo día.',
  reasonMax: 'El motivo no debe tener más de 255 caracteres.',
  notesMax: 'Las notas no deben tener más de 1000 caracteres.',
} as const;

/** `AppointmentMessages::MAX_MINUTES`. */
export const MAX_MINUTES = 720;

/** La grilla de la API: múltiplos de 5 minutos. */
export const GRID_MINUTES = 5;

/** El nombre de contacto: al menos una letra, sin caracteres de control ni `<` `>` (`CONTACT_NAME_REGEX`). */
const CONTACT_NAME = /^(?=.*\p{L})[^\p{Cc}<>]+$/u;

export type WhoKind = 'patient' | 'client' | 'contact';

export const WHO_OPTIONS: { value: WhoKind; label: string }[] = [
  { value: 'patient', label: 'Paciente' },
  { value: 'client', label: 'Cliente sin paciente' },
  { value: 'contact', label: 'Primera vez, sin cliente' },
];

export const SOURCE_OPTIONS: { value: AppointmentSource; label: string }[] = [
  { value: 'reception', label: 'Recepción' },
  { value: 'phone', label: 'Teléfono' },
  { value: 'whatsapp', label: 'WhatsApp' },
];

/** El paciente elegido, con su dueño (elegirlo fija el cliente). */
export interface PatientChoice {
  id: number;
  code: string;
  name: string;
  client: { id: number; display_name: string } | null;
}

export interface ClientChoice {
  id: number;
  display_name: string;
}

export interface AppointmentFormValues {
  who: WhoKind;
  patient: PatientChoice | null;
  client: ClientChoice | null;
  contact_name: string;
  contact_phone: string;
  /** El id de la agenda como texto (el valor del `<select>`), o ''. */
  schedule_id: string;
  service_id: string;
  /** Minutos, como texto (el campo numérico). */
  duration_minutes: string;
  /** `yyyy-MM-dd` de Asunción. */
  date: string;
  /** `HH:mm` de Asunción (un horario libre o "Otra hora"). */
  time: string;
  source: AppointmentSource;
  reason: string;
  notes: string;
  /** La repetición (3.6, solo en el alta): "Repetir", cada cuántas semanas y el fin, por cantidad o por fecha. */
  repeat: boolean;
  every_weeks: string;
  series_end: SeriesEnd;
  series_count: string;
  /** `yyyy-MM-dd` (la fecha de fin de la serie, inclusive). */
  series_until: string;
}

/** Los campos del formulario, para los errores de la API. */
export const APPOINTMENT_FORM_FIELDS = [
  'patient',
  'client',
  'contact_name',
  'contact_phone',
  'schedule_id',
  'service_id',
  'duration_minutes',
  'date',
  'time',
  'source',
  'reason',
  'notes',
  'every_weeks',
  'series_count',
  'series_until',
] as const satisfies readonly (keyof AppointmentFormValues)[];

export type AppointmentFormField = (typeof APPOINTMENT_FORM_FIELDS)[number];

export const EMPTY_APPOINTMENT_FORM: AppointmentFormValues = {
  who: 'patient',
  patient: null,
  client: null,
  contact_name: '',
  contact_phone: '',
  schedule_id: '',
  service_id: '',
  duration_minutes: '',
  date: '',
  time: '',
  source: 'reception',
  reason: '',
  notes: '',
  repeat: false,
  every_weeks: '1',
  series_end: 'count',
  series_count: '4',
  series_until: '',
};

/** Los caracteres, como los cuenta la API (`max` de Laravel cuenta caracteres, no unidades UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

const patientChoice = z.object({
  id: z.number(),
  code: z.string(),
  name: z.string(),
  client: z.object({ id: z.number(), display_name: z.string() }).nullable(),
});

const clientChoice = z.object({ id: z.number(), display_name: z.string() });

/**
 * ¿El formulario mueve el turno? Como la API (`AppointmentService::updateLocked`): otra agenda, otro inicio u otra duración.
 * Sin `original` (el alta), siempre.
 */
export function movesAppointment(
  values: Pick<AppointmentFormValues, 'schedule_id' | 'date' | 'time' | 'duration_minutes'>,
  original: AppointmentFormValues | undefined,
): boolean {
  if (original === undefined) {
    return true;
  }

  return (
    values.schedule_id !== original.schedule_id ||
    values.date !== original.date ||
    values.time !== original.time ||
    Number(trimLikeApi(values.duration_minutes)) !== Number(original.duration_minutes)
  );
}

/**
 * Las reglas del formulario. `today` es el día de Asunción (`todayInAppZone()`), no el de la PC: un turno de un día que ya
 * pasó se rechaza (la API acepta cualquier hora de hoy, también una que ya empezó: el que llegó sin turno, H7). En la
 * edición (`original`, el turno como formulario), uno que no se mueve no se rechaza por su día: la API revisa el horario
 * solo al mover (así se corrigen las notas de un turno de ayer que quedó confirmado).
 */
export function appointmentFormSchema(today: string, original?: AppointmentFormValues) {
  return z
    .object({
      who: z.enum(['patient', 'client', 'contact']),
      patient: patientChoice.nullable(),
      client: clientChoice.nullable(),
      contact_name: z.string(),
      contact_phone: z.string(),
      schedule_id: z.string(),
      service_id: z.string(),
      duration_minutes: z.string(),
      date: z.string(),
      time: z.string(),
      source: z.enum(['reception', 'phone', 'whatsapp']),
      reason: z.string(),
      notes: z.string(),
      repeat: z.boolean(),
      every_weeks: z.string(),
      series_end: z.enum(['count', 'until']),
      series_count: z.string(),
      series_until: z.string(),
    })
    .superRefine((values, context) => {
      const issue = (path: AppointmentFormField, message: string) => {
        context.addIssue({ code: 'custom', path: [path], message });
      };

      checkWho(values, issue);

      if (values.schedule_id === '') {
        issue('schedule_id', APPOINTMENT_MESSAGES.scheduleRequired);
      }

      const dateOk = parseDay(values.date) !== null;

      if (!dateOk) {
        issue('date', APPOINTMENT_MESSAGES.startRequired);
      } else if (values.date < today && movesAppointment(values, original)) {
        issue('date', APPOINTMENT_MESSAGES.pastDay);
      }

      const clock = parseClock(values.time);

      if (clock === null) {
        issue('time', APPOINTMENT_MESSAGES.startRequired);
      } else if (clock.minutes % GRID_MINUTES !== 0) {
        issue('time', APPOINTMENT_MESSAGES.offGrid);
      }

      const duration = /^\d+$/.test(trimLikeApi(values.duration_minutes))
        ? Number(trimLikeApi(values.duration_minutes))
        : null;

      if (duration === null || duration <= 0) {
        issue('duration_minutes', APPOINTMENT_MESSAGES.endsBeforeStart);
      } else if (duration % GRID_MINUTES !== 0) {
        issue('duration_minutes', APPOINTMENT_MESSAGES.offGrid);
      } else if (duration > MAX_MINUTES) {
        issue('duration_minutes', APPOINTMENT_MESSAGES.tooLong);
      } else if (dateOk && clock !== null && crossesMidnight(values.date, values.time, duration)) {
        issue('duration_minutes', APPOINTMENT_MESSAGES.crossesMidnight);
      }

      if (length(values.reason) > 255) {
        issue('reason', APPOINTMENT_MESSAGES.reasonMax);
      }

      if (length(values.notes) > 1000) {
        issue('notes', APPOINTMENT_MESSAGES.notesMax);
      }

      // La serie, solo en el alta (la edición no la muestra: la API rechaza `recurrence` en el PATCH).
      if (original === undefined && values.repeat) {
        const found = seriesFormError(values);

        if (found !== null) {
          issue(SERIES_FORM_FIELDS[found.field], found.message);
        }
      }
    });
}

function checkWho(
  values: Pick<
    AppointmentFormValues,
    'who' | 'patient' | 'client' | 'contact_name' | 'contact_phone'
  >,
  issue: (path: AppointmentFormField, message: string) => void,
) {
  if (values.who === 'patient') {
    if (values.patient === null) {
      issue('patient', APPOINTMENT_MESSAGES.whoRequired);
    }

    return;
  }

  if (values.who === 'client') {
    if (values.client === null) {
      issue('client', APPOINTMENT_MESSAGES.whoRequired);
    }

    return;
  }

  const name = trimLikeApi(values.contact_name);
  const phone = normalizePhone(trimLikeApi(values.contact_phone));

  if (name === '' && phone === '') {
    issue('contact_name', APPOINTMENT_MESSAGES.whoRequired);

    return;
  }

  if (name === '') {
    issue('contact_name', APPOINTMENT_MESSAGES.contactTogether);
  } else if (length(name) < 2) {
    issue('contact_name', APPOINTMENT_MESSAGES.contactNameMin);
  } else if (length(name) > 150) {
    issue('contact_name', APPOINTMENT_MESSAGES.contactNameMax);
  } else if (!CONTACT_NAME.test(name)) {
    issue('contact_name', APPOINTMENT_MESSAGES.contactNameFormat);
  }

  if (phone === '') {
    issue('contact_phone', APPOINTMENT_MESSAGES.contactTogether);
  } else if (!PHONE_PATTERN.test(phone)) {
    issue('contact_phone', APPOINTMENT_MESSAGES.contactPhoneFormat);
  }
}

const textOrNull = (value: string) => (trimLikeApi(value) === '' ? null : trimLikeApi(value));

/** El campo del formulario de cada clave de la repetición (`recurrence.*` de la API). */
const SERIES_FORM_FIELDS = {
  every_weeks: 'every_weeks',
  count: 'series_count',
  until: 'series_until',
} as const satisfies Record<string, AppointmentFormField>;

type SeriesFormValues = Pick<
  AppointmentFormValues,
  'date' | 'every_weeks' | 'series_end' | 'series_count' | 'series_until'
>;

/** El error de la repetición del formulario (`seriesError` con los campos de texto del formulario). */
function seriesFormError(values: SeriesFormValues) {
  return seriesError({
    firstDay: values.date,
    everyWeeks: values.every_weeks,
    end: values.series_end,
    count: values.series_count,
    until: values.series_until,
  });
}

/**
 * La repetición del formulario, ya leída (para la vista previa y el cuerpo), o null si "Repetir" no está marcado o algo no
 * tiene forma: la vista previa se muestra solo con la serie que la API aceptaría por su forma.
 */
export function seriesInputOf(
  values: SeriesFormValues & Pick<AppointmentFormValues, 'repeat'>,
): SeriesInput | null {
  if (!values.repeat || parseDay(values.date) === null || seriesFormError(values) !== null) {
    return null;
  }

  return {
    firstDay: values.date,
    everyWeeks: Number(trimLikeApi(values.every_weeks)),
    end: values.series_end,
    count: values.series_end === 'count' ? Number(trimLikeApi(values.series_count)) : null,
    until: values.series_end === 'until' ? values.series_until : null,
  };
}

/** El turno guardado como valores del formulario (la edición): la fecha y la hora de pared de Asunción y la duración. */
export function appointmentToForm(appointment: AppointmentDetail): AppointmentFormValues {
  const { day, clock } = instantToWall(appointment.starts_at);
  let who: WhoKind = 'contact';

  if (appointment.patient !== null) {
    who = 'patient';
  } else if (appointment.client !== null) {
    who = 'client';
  }

  return {
    who,
    patient:
      appointment.patient === null ? null : { ...appointment.patient, client: appointment.client },
    client: appointment.patient === null ? appointment.client : null,
    contact_name: appointment.contact_name ?? '',
    contact_phone: appointment.contact_phone ?? '',
    schedule_id: String(appointment.schedule.id),
    service_id: appointment.service === null ? '' : String(appointment.service.id),
    duration_minutes: String(minutesBetween(appointment.starts_at, appointment.ends_at)),
    date: day,
    time: clock,
    source: isSource(appointment.source.value) ? appointment.source.value : 'reception',
    reason: appointment.reason ?? '',
    notes: appointment.notes ?? '',
    repeat: false,
    every_weeks: '1',
    series_end: 'count',
    series_count: '4',
    series_until: '',
  };
}

function isSource(value: string): value is AppointmentSource {
  return value === 'reception' || value === 'phone' || value === 'whatsapp';
}

/** El cuerpo completo (el alta): uno de los tres "quién" con sus datos y los otros en null. */
function fullPayload(values: AppointmentFormValues): AppointmentPayload {
  const times = appointmentTimes(values.date, values.time, Number(values.duration_minutes));

  if (times === null) {
    throw new Error('El formulario del turno llegó sin validar.');
  }

  return {
    schedule_id: Number(values.schedule_id),
    service_id: values.service_id === '' ? null : Number(values.service_id),
    ...whoPayload(values),
    starts_at: times.starts_at,
    ends_at: times.ends_at,
    source: values.source,
    reason: textOrNull(values.reason),
    notes: textOrNull(values.notes),
  };
}

/** "Quién" va entero o no va: pasar de contacto a paciente vacía el contacto. */
const WHO_KEYS = ['patient_id', 'client_id', 'contact_name', 'contact_phone'] as const;
const DATA_KEYS = ['service_id', 'source', 'reason', 'notes'] as const;

/**
 * El cuerpo del alta (completo) y de la edición. Con `original` (el turno que se cargó en el formulario), solo lo que cambió
 * contra él (la API valida solo lo que llega, P7): "quién" entero si cambió algo de él; el servicio, el origen, el motivo y
 * las notas, cada uno si cambió; `schedule_id`, `starts_at` y `ends_at`, cada uno si cambió. Así el cuerpo dice lo que se
 * quiso cambiar y un guardado no pisa lo que otra PC cambió en los campos que no se tocaron. `overbook: true`, solo si se pide.
 */
export function appointmentFormToPayload(
  values: AppointmentFormValues,
  options?: { overbook?: boolean },
): AppointmentPayload;
export function appointmentFormToPayload(
  values: AppointmentFormValues,
  options: { original: AppointmentDetail; overbook?: boolean },
): AppointmentUpdatePayload;
export function appointmentFormToPayload(
  values: AppointmentFormValues,
  options: { original?: AppointmentDetail; overbook?: boolean } = {},
): AppointmentUpdatePayload {
  const full = fullPayload(values);
  const { original } = options;
  let payload: AppointmentUpdatePayload = full;

  if (original !== undefined) {
    const before = fullPayload(appointmentToForm(original));
    const changed: AppointmentUpdatePayload = {};

    if (WHO_KEYS.some((key) => full[key] !== before[key])) {
      Object.assign(changed, pick(full, WHO_KEYS));
    }

    Object.assign(
      changed,
      pick(
        full,
        DATA_KEYS.filter((key) => full[key] !== before[key]),
      ),
    );

    if (full.schedule_id !== original.schedule.id) {
      changed.schedule_id = full.schedule_id;
    }

    if (!sameInstant(original.starts_at, full.starts_at)) {
      changed.starts_at = full.starts_at;
    }

    if (!sameInstant(original.ends_at, full.ends_at)) {
      changed.ends_at = full.ends_at;
    }

    payload = changed;
  }

  return options.overbook === true ? { ...payload, overbook: true } : payload;
}

/**
 * El cuerpo del alta de una serie (3.6): el del alta con `recurrence` (`every_weeks` y uno de `count` o `until`). `overbook:
 * true`, solo después del 409 de la serie y con el permiso: vale para todas las fechas y saltea solo la superposición.
 */
export function appointmentSeriesPayload(
  values: AppointmentFormValues,
  options: { overbook?: boolean } = {},
): AppointmentPayload {
  const input = seriesInputOf(values);

  if (input === null) {
    throw new Error('La serie llegó sin validar.');
  }

  return {
    ...appointmentFormToPayload(values, options),
    recurrence: recurrencePayload(input),
  };
}

function pick<K extends keyof AppointmentPayload>(
  payload: AppointmentPayload,
  keys: readonly K[],
): Pick<AppointmentPayload, K> {
  return Object.fromEntries(keys.map((key) => [key, payload[key]])) as Pick<AppointmentPayload, K>;
}

function whoPayload(
  values: AppointmentFormValues,
): Pick<AppointmentPayload, 'patient_id' | 'client_id' | 'contact_name' | 'contact_phone'> {
  if (values.who === 'patient' && values.patient !== null) {
    return {
      patient_id: values.patient.id,
      // El dueño del paciente: la API lo acepta también si es el guardado en el turno, después de un cambio de dueño.
      client_id: values.patient.client?.id ?? null,
      contact_name: null,
      contact_phone: null,
    };
  }

  if (values.who === 'client' && values.client !== null) {
    return {
      patient_id: null,
      client_id: values.client.id,
      contact_name: null,
      contact_phone: null,
    };
  }

  return {
    patient_id: null,
    client_id: null,
    contact_name: trimLikeApi(values.contact_name),
    contact_phone: normalizePhone(trimLikeApi(values.contact_phone)),
  };
}

/**
 * La clave de `errors` de la API y el campo del formulario que la recibe. `starts_at` va a la hora (un día que ya pasó, la
 * grilla de 5 minutos); `ends_at`, a la duración (el fin, la medianoche, los 720 minutos); `schedule` es la clave de la
 * disponibilidad (la agenda o su veterinario inactivos).
 */
const API_FIELDS: Record<string, AppointmentFormField> = {
  patient_id: 'patient',
  client_id: 'client',
  contact_name: 'contact_name',
  contact_phone: 'contact_phone',
  schedule_id: 'schedule_id',
  schedule: 'schedule_id',
  service_id: 'service_id',
  starts_at: 'time',
  ends_at: 'duration_minutes',
  source: 'source',
  reason: 'reason',
  notes: 'notes',
  'recurrence.every_weeks': 'every_weeks',
  'recurrence.count': 'series_count',
  'recurrence.until': 'series_until',
};

/** Los campos que se ven con cada "quién" (un error de un campo oculto va a la alerta: si no, nadie lo vería). */
const HIDDEN_BY_WHO: Record<WhoKind, readonly AppointmentFormField[]> = {
  patient: ['client', 'contact_name', 'contact_phone'],
  client: ['patient', 'contact_name', 'contact_phone'],
  contact: ['patient', 'client'],
};

/**
 * Un 422 `VALIDATION_ERROR` repartido: cada mensaje a su campo (juntos si caen en el mismo) y lo demás a la alerta de arriba.
 * Devuelve null si el error no es de validación (lo trata quien llama). El cliente de un paciente elegido (`client_id`) va
 * al paciente, que es lo que se ve.
 */
export function mapAppointmentErrors(
  error: ApiError,
  who: WhoKind,
): { fields: [AppointmentFormField, string][]; others: string[] } | null {
  if (errorTreatment(error.errorCode) !== 'validation') {
    return null;
  }

  const byField = new Map<AppointmentFormField, string[]>();
  const others: string[] = [];

  for (const [key, messages] of Object.entries(error.fieldErrors())) {
    let field: AppointmentFormField | undefined = API_FIELDS[key];

    if (field === 'client' && who === 'patient') {
      field = 'patient';
    }

    if (field === undefined || HIDDEN_BY_WHO[who].includes(field)) {
      others.push(messages.join(' '));
      continue;
    }

    byField.set(field, [...(byField.get(field) ?? []), ...messages]);
  }

  return {
    fields: [...byField].map(([field, messages]) => [field, messages.join(' ')]),
    others,
  };
}

/** Los códigos de la API que van al horario, con la disponibilidad vuelta a pedir (diseño de la 3.4, "Errores"). */
export const SLOT_ERROR_CODES: ReadonlySet<string> = new Set([
  'APPOINTMENT_OVERLAP',
  'APPOINTMENT_OUTSIDE_HOURS',
  'APPOINTMENT_IN_BLOCK',
]);

/** La duración por defecto: la del servicio, o `slot_minutes` de la agenda (P5). */
export function defaultDuration(
  serviceDuration: number | null | undefined,
  slotMinutes: number | null | undefined,
): string {
  const minutes = serviceDuration ?? slotMinutes;

  return minutes === null || minutes === undefined ? '' : String(minutes);
}
