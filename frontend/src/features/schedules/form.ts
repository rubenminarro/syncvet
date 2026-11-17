import { z } from 'zod';
import {
  WEEKDAYS,
  type ScheduleDetail,
  type ScheduleHourPayload,
  type SchedulePayload,
} from './types';

/**
 * El formulario de alta y edición de la agenda (3.1a). Una función pura repite las reglas de forma de StoreScheduleRequest y
 * UpdateScheduleRequest (`ScheduleMessages`, con sus mensajes); lo que necesita la base (el nombre repetido en la sucursal,
 * el veterinario o el servicio inactivos, el veterinario sin la sucursal, `SCHEDULE_NOT_EDITABLE`) lo dice la API.
 *
 * El horario es una lista plana de tramos en el orden del formulario: el cuerpo los manda en ese mismo orden, así la clave
 * con índice de un 422 (`hours.3.starts_at`) cae en el mismo tramo de la pantalla.
 */
export interface HourFormValue {
  weekday: number;
  starts_at: string;
  ends_at: string;
}

export interface ScheduleFormValues {
  branch_id: string;
  name: string;
  veterinarian_id: string;
  service_id: string;
  slot_minutes: string;
  /** Sin la casilla, el color va en null (`<input type="color">` no puede quedar vacío). */
  use_color: boolean;
  color: string;
  hours: HourFormValue[];
}

/**
 * Los campos del formulario, para `applyValidationErrors` (las claves de `errors` de la API). La clave `hours` (un día con
 * tramos superpuestos o más de 6) y `hours.N.weekday` no tienen campo propio: van a la alerta de arriba.
 */
export const SCHEDULE_FORM_FIELDS = [
  'branch_id',
  'name',
  'veterinarian_id',
  'service_id',
  'slot_minutes',
  'color',
  'hours.*.starts_at',
  'hours.*.ends_at',
] as const;

/** `ScheduleMessages::NAME_MAX` y `MAX_HOURS_PER_DAY`. */
export const NAME_MAX = 100;
export const MAX_HOURS_PER_DAY = 6;
export const SLOT_MIN = 5;
export const SLOT_MAX = 240;
export const SLOT_STEP = 5;
export const DEFAULT_SLOT = 30;
/** El color que propone el selector al marcar "Con color" (el azul de Tabler, en mayúsculas como lo guarda la API). */
export const DEFAULT_COLOR = '#206BC4';

/** `CatalogMessages::NAME_REGEX` (`/^(?=[^\pL]*\pL)[\pL\pN\s.\-'()]+$/u`): al menos una letra; letras, números, espacios y `. - ' ( )`. */
export const SCHEDULE_NAME_PATTERN = /^(?=[^\p{L}]*\p{L})[\p{L}\p{N}\s.\-'()]+$/u;

/** `ScheduleMessages::CLOCK_TIME_REGEX`: "HH:MM" de 00:00 a 23:55, en múltiplos de 5 minutos. */
export const CLOCK_TIME_PATTERN = /^(?:[01]\d|2[0-3]):[0-5][05]$/;

/** `ScheduleMessages::COLOR_REGEX`. */
export const COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/;

const CLOCK = 'debe ser una hora HH:MM de 00:00 a 23:55, en múltiplos de 5 minutos.';

/** Los mensajes de `ScheduleMessages::messages()` y de `validateHours()`. */
export const SCHEDULE_MESSAGES = {
  branch_id: { required: 'La sucursal es obligatoria.' },
  name: {
    required: 'El nombre de la agenda es obligatorio.',
    min: 'El nombre de la agenda debe tener al menos 2 caracteres.',
    max: 'El nombre de la agenda no debe tener más de 100 caracteres.',
    regex:
      'El nombre de la agenda debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.',
  },
  owner: 'Elegí un veterinario, un servicio o los dos.',
  slot_minutes: {
    required: 'La duración del turno es obligatoria.',
    integer: 'La duración del turno debe ser un número entero de minutos.',
    between: 'La duración del turno debe estar entre 5 y 240 minutos.',
    multiple_of: 'La duración del turno debe ser un múltiplo de 5 minutos.',
  },
  color: 'El color debe tener el formato #RRGGBB.',
  hour: {
    startsRequired: 'La hora de inicio del tramo es obligatoria.',
    startsFormat: `La hora de inicio del tramo ${CLOCK}`,
    endsRequired: 'La hora de fin del tramo es obligatoria.',
    endsFormat: `La hora de fin del tramo ${CLOCK}`,
    endsBeforeStart: 'La hora de fin del tramo debe ser posterior a la de inicio.',
  },
  tooManyHours: (day: string) =>
    `El horario del ${day} tiene más de ${String(MAX_HOURS_PER_DAY)} tramos.`,
  overlap: (day: string) => `El horario del ${day} tiene tramos que se superponen.`,
} as const;

export const EMPTY_SCHEDULE_FORM: ScheduleFormValues = {
  branch_id: '',
  name: '',
  veterinarian_id: '',
  service_id: '',
  slot_minutes: String(DEFAULT_SLOT),
  use_color: false,
  color: DEFAULT_COLOR,
  hours: [],
};

/** La agenda guardada como valores del formulario de edición (null → vacío), con el horario en el orden de la API. */
export function scheduleToForm(schedule: ScheduleDetail): ScheduleFormValues {
  return {
    branch_id: String(schedule.branch.id),
    name: schedule.name,
    veterinarian_id: schedule.veterinarian === null ? '' : String(schedule.veterinarian.id),
    service_id: schedule.service === null ? '' : String(schedule.service.id),
    slot_minutes: String(schedule.slot_minutes),
    use_color: schedule.color !== null,
    color: schedule.color ?? DEFAULT_COLOR,
    hours: schedule.hours.map((hour) => ({
      weekday: hour.weekday,
      starts_at: hour.starts_at,
      ends_at: hour.ends_at,
    })),
  };
}

/** El nombre del día en minúscula ("lunes"), como en los mensajes de la API. */
export function weekdayName(weekday: number): string {
  return WEEKDAYS.find((day) => day.value === weekday)?.lower ?? String(weekday);
}

/** Los tramos de un día, con su índice en la lista del formulario. */
export function hoursOfDay(hours: readonly HourFormValue[], weekday: number) {
  return hours
    .map((hour, index) => ({ hour, index }))
    .filter((entry) => entry.hour.weekday === weekday);
}

/**
 * "Copiar el lunes a los días hábiles": los tramos del martes al viernes se reemplazan por los del lunes; el sábado y el
 * domingo no cambian. Devuelve la lista nueva, ordenada por día (dentro del día, en el orden del lunes).
 */
export function copyMondayToWeekdays(hours: readonly HourFormValue[]): HourFormValue[] {
  const monday = hours.filter((hour) => hour.weekday === 1);
  const kept = hours.filter((hour) => hour.weekday < 2 || hour.weekday > 5);
  const copies = [2, 3, 4, 5].flatMap((weekday) =>
    monday.map((hour) => ({ weekday, starts_at: hour.starts_at, ends_at: hour.ends_at })),
  );

  return [...kept, ...copies].sort((a, b) => a.weekday - b.weekday);
}

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

function nameError(value: string): string | null {
  const m = SCHEDULE_MESSAGES.name;
  const name = value.trim();
  const size = length(name);

  if (size === 0) {
    return m.required;
  }

  if (size < 2) {
    return m.min;
  }

  if (size > NAME_MAX) {
    return m.max;
  }

  return SCHEDULE_NAME_PATTERN.test(name) ? null : m.regex;
}

/** La duración del turno: un entero (como `integer` de Laravel, con signo opcional), de 5 a 240 y múltiplo de 5. */
function slotError(value: string): string | null {
  const m = SCHEDULE_MESSAGES.slot_minutes;
  const slot = value.trim();

  if (slot === '') {
    return m.required;
  }

  if (!/^[+-]?\d+$/.test(slot)) {
    return m.integer;
  }

  const minutes = Number(slot);

  if (minutes < SLOT_MIN || minutes > SLOT_MAX) {
    return m.between;
  }

  return minutes % SLOT_STEP === 0 ? null : m.multiple_of;
}

/**
 * El horario, en el orden de `ScheduleMessages::validateHours()`: primero la forma de cada hora (si alguna falla, nada más);
 * después, el fin antes del inicio (en su tramo, que no cuenta para su día); por día, más de 6 tramos y, si no, los tramos
 * que se superponen (tocarse pasa: "HH:MM" se compara como texto). El error de un día va en el tramo que lo provoca.
 */
export function hoursErrors(hours: readonly HourFormValue[]): Record<string, string> {
  const m = SCHEDULE_MESSAGES.hour;
  const errors: Record<string, string> = {};

  hours.forEach((hour, index) => {
    if (hour.starts_at === '') {
      errors[`hours.${String(index)}.starts_at`] = m.startsRequired;
    } else if (!CLOCK_TIME_PATTERN.test(hour.starts_at)) {
      errors[`hours.${String(index)}.starts_at`] = m.startsFormat;
    }

    if (hour.ends_at === '') {
      errors[`hours.${String(index)}.ends_at`] = m.endsRequired;
    } else if (!CLOCK_TIME_PATTERN.test(hour.ends_at)) {
      errors[`hours.${String(index)}.ends_at`] = m.endsFormat;
    }
  });

  if (Object.keys(errors).length > 0) {
    return errors;
  }

  const byDay = new Map<number, { hour: HourFormValue; index: number }[]>();

  hours.forEach((hour, index) => {
    if (hour.starts_at >= hour.ends_at) {
      errors[`hours.${String(index)}.ends_at`] = m.endsBeforeStart;

      return;
    }

    byDay.set(hour.weekday, [...(byDay.get(hour.weekday) ?? []), { hour, index }]);
  });

  for (const [weekday, entries] of byDay) {
    const day = weekdayName(weekday);

    if (entries.length > MAX_HOURS_PER_DAY) {
      const extra = entries[MAX_HOURS_PER_DAY];

      if (extra !== undefined) {
        errors[`hours.${String(extra.index)}.starts_at`] = SCHEDULE_MESSAGES.tooManyHours(day);
      }

      continue;
    }

    const sorted = [...entries].sort((a, b) => a.hour.starts_at.localeCompare(b.hour.starts_at));

    for (let i = 1; i < sorted.length; i++) {
      const previous = sorted[i - 1];
      const current = sorted[i];

      if (
        previous !== undefined &&
        current !== undefined &&
        current.hour.starts_at < previous.hour.ends_at
      ) {
        errors[`hours.${String(current.index)}.starts_at`] = SCHEDULE_MESSAGES.overlap(day);
        break;
      }
    }
  }

  return errors;
}

/** Los errores del formulario por ruta del campo (`name`, `hours.2.ends_at`); vacío si se puede mandar. */
export function scheduleFormErrors(values: ScheduleFormValues): Record<string, string> {
  const errors: Record<string, string | null> = {
    branch_id: values.branch_id === '' ? SCHEDULE_MESSAGES.branch_id.required : null,
    name: nameError(values.name),
    veterinarian_id:
      values.veterinarian_id === '' && values.service_id === '' ? SCHEDULE_MESSAGES.owner : null,
    slot_minutes: slotError(values.slot_minutes),
    color: values.use_color && !COLOR_PATTERN.test(values.color) ? SCHEDULE_MESSAGES.color : null,
  };

  return {
    ...Object.fromEntries(
      Object.entries(errors).filter(
        (entry): entry is [string, string] => typeof entry[1] === 'string',
      ),
    ),
    ...hoursErrors(values.hours),
  };
}

const shape = z.object({
  branch_id: z.string(),
  name: z.string(),
  veterinarian_id: z.string(),
  service_id: z.string(),
  slot_minutes: z.string(),
  use_color: z.boolean(),
  color: z.string(),
  hours: z.array(z.object({ weekday: z.number(), starts_at: z.string(), ends_at: z.string() })),
});

/** El esquema de React Hook Form: cada error de `scheduleFormErrors` en su campo (`hours.2.ends_at` → `['hours', 2, 'ends_at']`). */
export const scheduleFormSchema = shape.superRefine((values, context) => {
  for (const [field, message] of Object.entries(scheduleFormErrors(values))) {
    const path = field.split('.').map((part) => (/^\d+$/.test(part) ? Number(part) : part));
    context.addIssue({ code: 'custom', path, message });
  }
});

/**
 * El cuerpo del alta y de la edición (ya validado): el nombre sin los espacios de los extremos, los ids como números (o null
 * sin elegir), el color en mayúsculas (o null sin "Con color") y el horario **entero**, en el orden del formulario: un día
 * vaciado no lleva tramos y la API reemplaza el horario anterior (`hours: []` lo vacía).
 */
export function scheduleFormToPayload(values: ScheduleFormValues): SchedulePayload {
  const hours: ScheduleHourPayload[] = values.hours.map((hour) => ({
    weekday: hour.weekday,
    starts_at: hour.starts_at,
    ends_at: hour.ends_at,
  }));

  return {
    branch_id: Number(values.branch_id),
    name: values.name.trim(),
    veterinarian_id: values.veterinarian_id === '' ? null : Number(values.veterinarian_id),
    service_id: values.service_id === '' ? null : Number(values.service_id),
    slot_minutes: Number(values.slot_minutes.trim()),
    color: values.use_color ? values.color.toUpperCase() : null,
    hours,
  };
}

/** La duración del turno para mostrar: "30 min". */
export function formatSlot(minutes: number): string {
  return `${String(minutes)} min`;
}

/** Los tramos de un día para mostrar ("08:00 a 12:00, 14:00 a 18:00"), o null sin atención ese día. */
export function formatDayHours(
  hours: readonly { weekday: number; starts_at: string; ends_at: string }[],
  weekday: number,
): string | null {
  const ranges = hours
    .filter((hour) => hour.weekday === weekday)
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at))
    .map((hour) => `${hour.starts_at} a ${hour.ends_at}`);

  return ranges.length === 0 ? null : ranges.join(', ');
}
