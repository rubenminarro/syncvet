import { z } from 'zod';
import { normalizeSearchText } from '../../lib/search-text';
import type { AppointmentListFilters } from './api';
import { daysBetween, isAgendaDay } from './dates';
import { parseInicio } from './times';

/**
 * La búsqueda de /agenda en la URL (diseño de la F3, "URL en castellano"):
 * `?vista=dia&fecha=2026-11-17&agenda=7&veterinario=4&cancelados=1&todos=1`. Sin un valor, no va: el día de hoy en Asunción,
 * todas las agendas que se ven, sin los cancelados y, para el veterinario, su vista por defecto (pregunta 3). `fecha` se lee
 * como texto (`TEXT_SEARCH_PARAMS`): `2026-11-17` no es un número, pero el router lo leería como JSON si pareciera uno.
 * `vista`: `dia` (o ninguna) y, desde la 3.3, `semana`; otra se descarta.
 */
export type AgendaView = 'dia' | 'semana';

export interface AgendaSearch {
  vista?: AgendaView | undefined;
  fecha?: string | undefined;
  agenda?: number | undefined;
  veterinario?: number | undefined;
  cancelados?: 1 | undefined;
  todos?: 1 | undefined;
}

const vistaSchema = z.enum(['dia', 'semana']);
const fechaSchema = z.string().refine(isAgendaDay);
const idSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(1).max(Number.MAX_SAFE_INTEGER));
const flagSchema = z
  .union([z.literal(1), z.literal('1'), z.literal(true)])
  .transform(() => 1 as const);

/**
 * `validateSearch` de /agenda: un valor inválido (una fecha mal formada o que no existe, una vista desconocida, un id que no
 * es un entero positivo) se descarta. Las seis claves van siempre, con `undefined` si el valor se descarta (el router mezcla
 * lo devuelto sobre la búsqueda cruda).
 */
export function validateAgendaSearch(raw: Record<string, unknown>): AgendaSearch {
  const vista = vistaSchema.safeParse(raw.vista);
  const fecha = fechaSchema.safeParse(raw.fecha);
  const agenda = idSchema.safeParse(raw.agenda);
  const veterinario = idSchema.safeParse(raw.veterinario);
  const cancelados = flagSchema.safeParse(raw.cancelados);
  const todos = flagSchema.safeParse(raw.todos);

  return {
    vista: vista.success ? vista.data : undefined,
    fecha: fecha.success ? fecha.data : undefined,
    agenda: agenda.success ? agenda.data : undefined,
    veterinario: veterinario.success ? veterinario.data : undefined,
    cancelados: cancelados.success ? cancelados.data : undefined,
    todos: todos.success ? todos.data : undefined,
  };
}

/**
 * La búsqueda de /agenda/cola (3.7, pregunta 11): `?fecha=2026-11-17&todos=1`. Sin `fecha`, hoy en Asunción; `todos`, "Ver
 * toda la sucursal" (para quien tiene ficha de veterinario: a la API, `all=1`). Un valor inválido se descarta; las dos claves
 * van siempre.
 */
export interface QueueSearch {
  fecha?: string | undefined;
  todos?: 1 | undefined;
}

export function validateQueueSearch(raw: Record<string, unknown>): QueueSearch {
  const fecha = fechaSchema.safeParse(raw.fecha);
  const todos = flagSchema.safeParse(raw.todos);

  return {
    fecha: fecha.success ? fecha.data : undefined,
    todos: todos.success ? todos.data : undefined,
  };
}

/**
 * Los estados de la lista en la URL, en castellano (`?estados=confirmado,fichado`, una lista: `LIST_SEARCH_PARAMS`), y su
 * valor en la API. El nombre es `estados` y no `estado`, que ya usan veterinarios y servicios con otros valores.
 */
export const STATUS_BY_PARAM = {
  'a-confirmar': 'pending_confirmation',
  confirmado: 'confirmed',
  fichado: 'checked_in',
  atendido: 'attended',
  ausente: 'no_show',
  cancelado: 'cancelled',
} as const;

export type StatusParam = keyof typeof STATUS_BY_PARAM;

/** Los estados en el orden de las casillas (el de la API). */
export const STATUS_PARAMS = Object.keys(STATUS_BY_PARAM) as StatusParam[];

/** La etiqueta de cada estado en las casillas (la de la API, `AppointmentStatus::label`). */
export const STATUS_LABELS: Record<StatusParam, string> = {
  'a-confirmar': 'A confirmar',
  confirmado: 'Confirmado',
  fichado: 'Fichado',
  atendido: 'Atendido',
  ausente: 'Ausente',
  cancelado: 'Cancelado',
};

/** El rango más largo de la lista, en días de calendario contando los dos extremos (`IndexAppointmentRequest::MAX_DAYS`). */
export const MAX_RANGE_DAYS = 31;

/** Los mensajes de la API para el rango (`IndexAppointmentRequest` e `IndexAuditLogRequest`). */
export const RANGE_TOO_LONG = `El rango no puede superar los ${String(MAX_RANGE_DAYS)} días.`;
export const TO_BEFORE_FROM = 'La fecha hasta no puede ser anterior a la fecha desde.';

/**
 * El error del rango, como la API: `to` anterior a `from`, o más de 31 días (la API rechaza `to` desde el día 31 después
 * de `from`: del 01/11 al 01/12 pasa, al 02/12 no). Con una sola fecha (la API la toma como ese día) o ninguna, no hay error.
 */
export function listRangeError(from: string | undefined, to: string | undefined): string | null {
  if (from === undefined || to === undefined) {
    return null;
  }

  const days = daysBetween(from, to);

  if (days < 0) {
    return TO_BEFORE_FROM;
  }

  return days >= MAX_RANGE_DAYS ? RANGE_TOO_LONG : null;
}

/**
 * La búsqueda de /turnos en la URL (3.3): `?desde=2026-11-16&hasta=2026-11-22&agenda=7&veterinario=4&estados=confirmado,fichado
 * &buscar=luna&pagina=2`. Sin un valor, no va: sin fechas, hoy (lo que hace la API); todas las agendas, los veterinarios y
 * los estados (también los cancelados: la lista los muestra, pregunta 5); la página 1. La sucursal es siempre la actual de
 * la barra (pregunta 2), no va en la URL.
 */
export interface AppointmentListSearch {
  desde?: string | undefined;
  hasta?: string | undefined;
  agenda?: number | undefined;
  veterinario?: number | undefined;
  estados?: StatusParam[] | undefined;
  buscar?: string | undefined;
  pagina?: number | undefined;
  /** La serie (3.6, `series_id`, un UUID): con ella, la lista muestra la serie entera, sin desde ni hasta. */
  serie?: string | undefined;
}

/** `series_id` es un UUID (`IndexAppointmentRequest`: `uuid`); en la URL, en minúsculas. */
const serieSchema = z
  .string()
  .regex(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i)
  .transform((value) => value.toLowerCase());

const statusParamSchema = z.enum(STATUS_PARAMS as [StatusParam, ...StatusParam[]]);
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const paginaSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(2).max(Number.MAX_SAFE_INTEGER));

/** Los estados de la URL: los conocidos, sin repetir, en el orden de las casillas; ninguno → undefined. */
function parseStatuses(raw: unknown): StatusParam[] | undefined {
  let list: unknown[] = [];

  if (Array.isArray(raw)) {
    list = raw;
  } else if (typeof raw === 'string') {
    list = raw.split(',');
  }

  const known = new Set(
    list.flatMap((item) => {
      const parsed = statusParamSchema.safeParse(item);

      return parsed.success ? [parsed.data] : [];
    }),
  );
  const ordered = STATUS_PARAMS.filter((status) => known.has(status));

  return ordered.length === 0 ? undefined : ordered;
}

/**
 * `validateSearch` de /turnos: un valor inválido se descarta (una fecha mal formada, un estado desconocido, una página que no
 * es un entero de 2 en adelante). Como la API: `hasta` anterior a `desde` o a más de 31 días se descarta (la URL editada;
 * el formulario no la deja armar), y queda el día de `desde`. Las ocho claves (con la serie, sin desde ni hasta) van siempre (el router mezcla lo devuelto
 * sobre la búsqueda cruda).
 */
export function validateAppointmentListSearch(raw: Record<string, unknown>): AppointmentListSearch {
  const desde = fechaSchema.safeParse(raw.desde);
  const hasta = fechaSchema.safeParse(raw.hasta);
  const agenda = idSchema.safeParse(raw.agenda);
  const veterinario = idSchema.safeParse(raw.veterinario);
  const buscar = buscarSchema.safeParse(raw.buscar);
  const pagina = paginaSchema.safeParse(raw.pagina);
  const serie = serieSchema.safeParse(raw.serie);
  const from = desde.success ? desde.data : undefined;
  const to = hasta.success ? hasta.data : undefined;

  if (serie.success) {
    // La serie entera: desde y hasta no se usan (front-04) y no quedan en la URL.
    return {
      desde: undefined,
      hasta: undefined,
      agenda: agenda.success ? agenda.data : undefined,
      veterinario: veterinario.success ? veterinario.data : undefined,
      estados: parseStatuses(raw.estados),
      buscar: buscar.success ? buscar.data : undefined,
      pagina: pagina.success ? pagina.data : undefined,
      serie: serie.data,
    };
  }

  return {
    serie: undefined,
    desde: from,
    hasta: listRangeError(from, to) === null ? to : undefined,
    agenda: agenda.success ? agenda.data : undefined,
    veterinario: veterinario.success ? veterinario.data : undefined,
    estados: parseStatuses(raw.estados),
    buscar: buscar.success ? buscar.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/** Los filtros de GET appointments que salen de la búsqueda de /turnos. */
export function toAppointmentListFilters(search: AppointmentListSearch): AppointmentListFilters {
  return {
    from: search.desde ?? null,
    to: search.hasta ?? null,
    schedule_id: search.agenda ?? null,
    veterinarian_id: search.veterinario ?? null,
    status: (search.estados ?? []).map((status) => STATUS_BY_PARAM[status]),
    search: search.buscar ?? '',
    page: search.pagina ?? 1,
    series_id: search.serie ?? null,
  };
}

/**
 * /turnos/nuevo?agenda=7&inicio=2026-11-17T09:30 (3.4): la celda libre de la grilla llena la agenda y el inicio (la hora de
 * pared de Asunción, como texto: `TEXT_SEARCH_PARAMS`). Un valor inválido se descarta; las dos claves van siempre.
 */
export interface NewAppointmentSearch {
  agenda?: number | undefined;
  inicio?: string | undefined;
}

const inicioSchema = z.string().refine((value) => parseInicio(value) !== null);

export function validateNewAppointmentSearch(raw: Record<string, unknown>): NewAppointmentSearch {
  const agenda = idSchema.safeParse(raw.agenda);
  const inicio = inicioSchema.safeParse(raw.inicio);

  return {
    agenda: agenda.success ? agenda.data : undefined,
    inicio: inicio.success ? inicio.data : undefined,
  };
}

/** /turnos/$id/editar?reprogramar=1: "Reprogramar" lleva al mismo formulario, con el foco en el horario. */
export interface EditAppointmentSearch {
  reprogramar?: 1 | undefined;
}

export function validateEditAppointmentSearch(raw: Record<string, unknown>): EditAppointmentSearch {
  const reprogramar = flagSchema.safeParse(raw.reprogramar);

  return { reprogramar: reprogramar.success ? reprogramar.data : undefined };
}
