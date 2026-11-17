import { z } from 'zod';
import { normalizeSearchText, parseRouteId } from '../../lib/search-text';
import { isAuditDate, isAuditRangeReversed, toAuditApiDate } from './dates';
import {
  AUDIT_EVENT_VALUES,
  AUDITABLE_TYPE_VALUES,
  type AuditableType,
  type AuditEventValue,
  type AuditLogListFilters,
} from './types';

export { SEARCH_MAX_LENGTH } from '../../lib/search-text';

/**
 * La búsqueda de /configuracion/auditoria en la URL:
 * `?desde=2026-11-09&hasta=2026-11-09T18:00&usuario=3&evento=updated&entidad=client&registro=12&buscar=ana&pagina=2`. Sin un
 * valor, no va. `desde` y `hasta` son la hora de pared de Asunción, sin zona (`dates.ts`), y se leen como texto
 * (`TEXT_SEARCH_PARAMS`), como `buscar`.
 */
export interface AuditListSearch {
  desde?: string | undefined;
  hasta?: string | undefined;
  usuario?: number | undefined;
  evento?: AuditEventValue | undefined;
  entidad?: AuditableType | undefined;
  registro?: number | undefined;
  buscar?: string | undefined;
  pagina?: number | undefined;
}

// Un esquema por parámetro, cada uno con safeParse: uno inválido se descarta sin tocar los demás (el modelo de /clientes).
const fechaSchema = z.string().refine(isAuditDate);
const idSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(1).max(Number.MAX_SAFE_INTEGER));
const eventoSchema = z.enum(AUDIT_EVENT_VALUES as [AuditEventValue, ...AuditEventValue[]]);
const entidadSchema = z.enum(AUDITABLE_TYPE_VALUES as [AuditableType, ...AuditableType[]]);
const buscarSchema = z
  .union([z.string(), z.number()])
  .transform((value) => normalizeSearchText(String(value)))
  .pipe(z.string().min(1));
const paginaSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(2).max(Number.MAX_SAFE_INTEGER));

/**
 * `validateSearch` de /configuracion/auditoria: un valor inválido se descarta y vuelve al valor por defecto. Además, como la
 * API: `registro` sin `entidad` se descarta, y `hasta` anterior a `desde` también (la URL editada; el formulario no la deja
 * armar). Las ocho claves van siempre, con `undefined` si el valor se descarta (el router mezcla lo devuelto sobre la
 * búsqueda cruda).
 */
export function validateAuditListSearch(raw: Record<string, unknown>): AuditListSearch {
  const desde = fechaSchema.safeParse(raw.desde);
  const hasta = fechaSchema.safeParse(raw.hasta);
  const usuario = idSchema.safeParse(raw.usuario);
  const evento = eventoSchema.safeParse(raw.evento);
  const entidad = entidadSchema.safeParse(raw.entidad);
  const registro = idSchema.safeParse(raw.registro);
  const buscar = buscarSchema.safeParse(raw.buscar);
  const pagina = paginaSchema.safeParse(raw.pagina);

  const from = desde.success ? desde.data : undefined;
  const to = hasta.success ? hasta.data : undefined;
  const type = entidad.success ? entidad.data : undefined;

  return {
    desde: from,
    hasta: isAuditRangeReversed(from, to) ? undefined : to,
    usuario: usuario.success ? usuario.data : undefined,
    evento: evento.success ? evento.data : undefined,
    entidad: type,
    registro: registro.success && type !== undefined ? registro.data : undefined,
    buscar: buscar.success ? buscar.data : undefined,
    pagina: pagina.success ? pagina.data : undefined,
  };
}

/** Los filtros de GET audit-logs que salen de la búsqueda de la URL, con las fechas ya en UTC. */
export function toAuditListFilters(search: AuditListSearch): AuditLogListFilters {
  return {
    from: search.desde === undefined ? null : toAuditApiDate(search.desde, 'from'),
    to: search.hasta === undefined ? null : toAuditApiDate(search.hasta, 'to'),
    user_id: search.usuario ?? null,
    event: search.evento ?? null,
    auditable_type: search.entidad ?? null,
    auditable_id: search.registro ?? null,
    search: search.buscar ?? '',
    page: search.pagina ?? 1,
  };
}

/** ¿Es un evento de la auditoría? (el valor de un `<select>`). */
export function isAuditEvent(value: string): value is AuditEventValue {
  return (AUDIT_EVENT_VALUES as readonly string[]).includes(value);
}

/** ¿Es un tipo auditable? (el valor de un `<select>`). */
export function isAuditableType(value: string): value is AuditableType {
  return (AUDITABLE_TYPE_VALUES as readonly string[]).includes(value);
}

/** El id de /configuracion/auditoria/$id: solo un entero positivo; si no, null (la página de no encontrado). */
export function parseAuditLogId(param: string): number | null {
  return parseRouteId(param);
}
