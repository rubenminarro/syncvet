import type { AuditLog, AuditLogDetail } from '../features/audit/types';

/** Un cambio de un cliente como lo manda la API (ShowAuditLogResource): las notas antes y después. */
export const CLIENT_UPDATED: AuditLogDetail = {
  id: 41,
  event: { value: 'updated', label: 'Modificado' },
  auditable_type: 'client',
  auditable_id: 12,
  user: { id: 3, name: 'Ana Gómez' },
  ip_address: '127.0.0.1',
  // 13:30:05 en Asunción.
  created_at: '2026-11-09T16:30:05.000000Z',
  old_values: { notes: 'Llamar a la tarde.', phone: '+595981400101' },
  new_values: { notes: 'Prefiere WhatsApp.\nNo llamar.', phone: null },
  user_agent: 'Mozilla/5.0 (Windows NT 10.0) Edg/142.0',
};

/**
 * El alta de un usuario que la API dejara pasar sin ocultar (no lo hace: es lo que la pantalla tapa igual), con una lista,
 * un texto con HTML y la ruta de un archivo.
 */
export const USER_CREATED_LEAKY: AuditLogDetail = {
  id: 42,
  event: { value: 'created', label: 'Creado' },
  auditable_type: 'user',
  auditable_id: 8,
  user: null,
  ip_address: null,
  created_at: '2026-11-10T02:59:59.000000Z',
  old_values: null,
  new_values: {
    first_name: '<img src=x onerror=alert(1)>',
    email: 'nuevo@example.com',
    password: '$2y$12$hash-que-no-tiene-que-verse',
    remember_token: 'token-que-no-tiene-que-verse',
    photo_path: 'clinics/1/users/8/foto.jpg',
    branch_ids: [10, 11],
    settings: { logo: 'clinics/1/logo/x.png', theme: 'claro' },
  },
  user_agent: null,
};

/** La baja de un paciente: solo los valores de antes. */
export const PATIENT_DELETED: AuditLogDetail = {
  id: 43,
  event: { value: 'deleted', label: 'Eliminado' },
  auditable_type: 'patient',
  auditable_id: 7,
  user: { id: 3, name: 'Ana Gómez' },
  ip_address: '10.0.0.5',
  created_at: '2026-11-08T12:00:00.000000Z',
  old_values: { name: 'Firulais', photo_path: '(archivo)' },
  new_values: null,
  user_agent: 'Mozilla/5.0',
};

/** Un ingreso: sin valores. */
export const LOGIN: AuditLogDetail = {
  id: 44,
  event: { value: 'login', label: 'Inicio de sesión' },
  auditable_type: 'user',
  auditable_id: 3,
  user: { id: 3, name: 'Ana Gómez' },
  ip_address: '127.0.0.1',
  created_at: '2026-11-08T11:00:00.000000Z',
  old_values: null,
  new_values: null,
  user_agent: 'Mozilla/5.0',
};

/** Un ítem del listado (AuditLogResource): sin los valores ni el navegador. */
export function auditListItem(detail: AuditLogDetail): AuditLog {
  return {
    id: detail.id,
    event: detail.event,
    auditable_type: detail.auditable_type,
    auditable_id: detail.auditable_id,
    user: detail.user,
    ip_address: detail.ip_address,
    created_at: detail.created_at,
  };
}
