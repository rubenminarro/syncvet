import {
  AUDIT_DATE_FORMAT_MESSAGE,
  AUDIT_TO_BEFORE_FROM_MESSAGE,
  isAuditDate,
  isAuditRangeReversed,
  joinAuditDate,
} from './dates';

/** El mensaje de la API para el registro sin el tipo (IndexAuditLogRequest::TYPE_REQUIRED). */
export const AUDIT_TYPE_REQUIRED_MESSAGE =
  'Para filtrar por el id del registro, indicá también su tipo.';

/** Una hora sin la fecha: la API no la recibe nunca (se avisa antes). */
export const AUDIT_TIME_WITHOUT_DATE_MESSAGE = 'Elegí también la fecha.';

/** El registro que no es un entero positivo (la regla `integer|min:1` de la API). */
export const AUDIT_RECORD_MESSAGE = 'Ingresá el número del registro (solo dígitos).';

/** Lo escrito en el formulario de las fechas y el registro: la fecha `yyyy-MM-dd`, la hora `HH:mm`, cada uno '' vacío. */
export interface AuditFilterForm {
  fromDate: string;
  fromTime: string;
  toDate: string;
  toTime: string;
  record: string;
  /** El campo de la fecha quedó a medio escribir (`validity.badInput`: el `<input type="date">` da ''). */
  fromBadInput?: boolean;
  toBadInput?: boolean;
}

export type AuditFilterField = 'fromDate' | 'fromTime' | 'toDate' | 'toTime' | 'record';

export type AuditFilterErrors = Partial<Record<AuditFilterField, string>>;

/** Lo que el formulario pone en la URL (sin valor, `undefined`: la clave sale de la URL). */
export interface AuditFilterValues {
  desde: string | undefined;
  hasta: string | undefined;
  registro: number | undefined;
}

/**
 * Las reglas de `IndexAuditLogRequest`, con sus mensajes, antes de cambiar la URL (y de pedir nada): la forma de cada fecha,
 * "hasta" no anterior a "desde" (comparando la hora de pared como texto, `dates.ts`) y el registro, un entero positivo que
 * exige el tipo. Con errores, `values` es null.
 */
export function validateAuditFilterForm(
  form: AuditFilterForm,
  hasType: boolean,
): { errors: AuditFilterErrors; values: AuditFilterValues | null } {
  const errors: AuditFilterErrors = {};
  const desde = joinAuditDate(form.fromDate, form.fromTime);
  const hasta = joinAuditDate(form.toDate, form.toTime);

  if (form.fromBadInput === true || (desde !== '' && !isAuditDate(desde))) {
    errors.fromDate = AUDIT_DATE_FORMAT_MESSAGE;
  } else if (form.fromDate === '' && form.fromTime !== '') {
    errors.fromDate = AUDIT_TIME_WITHOUT_DATE_MESSAGE;
  }

  if (form.toBadInput === true || (hasta !== '' && !isAuditDate(hasta))) {
    errors.toDate = AUDIT_DATE_FORMAT_MESSAGE;
  } else if (form.toDate === '' && form.toTime !== '') {
    errors.toDate = AUDIT_TIME_WITHOUT_DATE_MESSAGE;
  }

  if (
    errors.fromDate === undefined &&
    errors.toDate === undefined &&
    isAuditRangeReversed(desde, hasta)
  ) {
    errors.toDate = AUDIT_TO_BEFORE_FROM_MESSAGE;
  }

  const record = form.record.trim();
  let registro: number | undefined;

  if (record !== '') {
    registro = /^\d{1,15}$/.test(record) ? Number(record) : undefined;

    if (registro === undefined || registro < 1) {
      errors.record = AUDIT_RECORD_MESSAGE;
    } else if (!hasType) {
      errors.record = AUDIT_TYPE_REQUIRED_MESSAGE;
    }
  }

  if (Object.keys(errors).length > 0) {
    return { errors, values: null };
  }

  return {
    errors,
    values: {
      desde: desde === '' ? undefined : desde,
      hasta: hasta === '' ? undefined : hasta,
      registro,
    },
  };
}
