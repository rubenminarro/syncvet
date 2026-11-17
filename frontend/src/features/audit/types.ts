/** La auditoría como la manda la API (AuditLogResource y ShowAuditLogResource; backend/CLAUDE.md §6.9). */

/**
 * Los eventos de la auditoría (`App\Enums\AuditEvent`), con el texto de `label()`: la API no tiene endpoint con la lista (H2
 * del diseño de la F2). `types.test.ts` la compara con el archivo PHP.
 */
export const AUDIT_EVENTS = [
  { value: 'created', label: 'Creado' },
  { value: 'updated', label: 'Modificado' },
  { value: 'deleted', label: 'Eliminado' },
  { value: 'restored', label: 'Restaurado' },
  { value: 'login', label: 'Inicio de sesión' },
  { value: 'login_failed', label: 'Inicio de sesión fallido' },
  { value: 'logout', label: 'Cierre de sesión' },
  { value: 'permissions_synced', label: 'Permisos del rol actualizados' },
  { value: 'roles_synced', label: 'Roles del usuario actualizados' },
  { value: 'downloaded', label: 'Descargado' },
] as const;

export type AuditEventValue = (typeof AUDIT_EVENTS)[number]['value'];

export const AUDIT_EVENT_VALUES: readonly AuditEventValue[] = AUDIT_EVENTS.map(
  (event) => event.value,
);

/**
 * Los tipos auditables (`AuditLog::AUDITABLE_TYPES`), en el orden de la API, con su nombre en castellano (propio del
 * frontend: la API manda solo el tipo corto). `types.test.ts` compara los valores con el archivo PHP.
 */
export const AUDITABLE_TYPES = [
  { value: 'appointment', label: 'Turno' },
  { value: 'branch', label: 'Sucursal' },
  { value: 'breed', label: 'Raza' },
  { value: 'cash_register', label: 'Caja' },
  { value: 'client', label: 'Cliente' },
  { value: 'clinic', label: 'Clínica' },
  { value: 'consultation', label: 'Consulta' },
  { value: 'consultation_item', label: 'Ítem de consulta' },
  { value: 'document_template', label: 'Plantilla de documento' },
  { value: 'invoice', label: 'Factura' },
  { value: 'invoice_stamp', label: 'Timbrado' },
  { value: 'patient', label: 'Paciente' },
  { value: 'patient_application', label: 'Aplicación al paciente' },
  { value: 'patient_document', label: 'Documento del paciente' },
  { value: 'payment', label: 'Pago' },
  { value: 'product', label: 'Producto' },
  { value: 'schedule', label: 'Agenda' },
  { value: 'schedule_block', label: 'Bloqueo de agenda' },
  { value: 'schedule_hour', label: 'Horario de agenda' },
  { value: 'service', label: 'Servicio' },
  { value: 'species', label: 'Especie' },
  { value: 'system_setting', label: 'Configuración' },
  { value: 'user', label: 'Usuario' },
  { value: 'veterinarian', label: 'Veterinario' },
  { value: 'warehouse', label: 'Depósito' },
] as const;

export type AuditableType = (typeof AUDITABLE_TYPES)[number]['value'];

export const AUDITABLE_TYPE_VALUES: readonly AuditableType[] = AUDITABLE_TYPES.map(
  (type) => type.value,
);

/** El nombre en castellano de un tipo; uno que la lista no conoce, tal cual lo manda la API. */
export function auditableTypeLabel(type: string): string {
  return AUDITABLE_TYPES.find((item) => item.value === type)?.label ?? type;
}

/** Un ítem de GET audit-logs (AuditLogResource). El usuario, también eliminado; null sin usuario. */
export interface AuditLog {
  id: number;
  event: { value: string; label: string };
  auditable_type: string | null;
  auditable_id: number | null;
  user: { id: number; name: string } | null;
  ip_address: string | null;
  created_at: string | null;
}

/** Los valores guardados de un cambio: un objeto con las claves del modelo (con listas u objetos anidados). */
export type AuditValues = Record<string, unknown>;

/** GET audit-logs/{id} (ShowAuditLogResource): suma los valores de antes y de después y el navegador. */
export interface AuditLogDetail extends AuditLog {
  old_values: AuditValues | null;
  new_values: AuditValues | null;
  user_agent: string | null;
}

/** Los filtros de GET audit-logs, con los nombres de la API (la clave de la consulta). `from` y `to`, ya convertidos. */
export interface AuditLogListFilters {
  from: string | null;
  to: string | null;
  user_id: number | null;
  event: AuditEventValue | null;
  auditable_type: AuditableType | null;
  auditable_id: number | null;
  search: string;
  page: number;
}
