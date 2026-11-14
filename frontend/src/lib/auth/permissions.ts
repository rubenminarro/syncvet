/**
 * Los permisos del usuario (me.permissions, los nombres de las rutas de la API: `clients.index`, `system-settings.update`).
 * El frontend solo los usa para mostrar u ocultar: la API vuelve a controlar cada pedido (un botón oculto no protege).
 */

/** Los permisos que usa el frontend, con el nombre de la API. Se suman al llegar cada módulo. */
export const PERMISSIONS = {
  /** /clientes (F1, entrega 1.1): los tres roles. */
  clientsIndex: 'clients.index',
  /** /clientes/$id (F1, entrega 1.1): los tres roles. */
  clientsShow: 'clients.show',
  /** /clientes/nuevo (F1, entrega 1.2): los tres roles. */
  clientsStore: 'clients.store',
  /** /clientes/$id/editar (F1, entrega 1.2): los tres roles. */
  clientsUpdate: 'clients.update',
  /** La baja desde el detalle (F1, entrega 1.2): solo el administrador. */
  clientsDestroy: 'clients.destroy',
  /** /pacientes (F1, entrega 1.3a): los tres roles. */
  patientsIndex: 'patients.index',
  /** /pacientes/$id (F1, entrega 1.3a): los tres roles. */
  patientsShow: 'patients.show',
  /** /clientes/$id/pacientes/nuevo (F1, entrega 1.3a): los tres roles. */
  patientsStore: 'patients.store',
  /** /pacientes/$id/editar (F1, entrega 1.3a): los tres roles. */
  patientsUpdate: 'patients.update',
  /** El cambio de dueño desde el detalle (F1, entrega 1.3b): administrador y recepcionista (el veterinario no). */
  patientsChangeOwner: 'patients.change-owner',
  /** La baja desde el detalle (F1, entrega 1.3b): solo el administrador. */
  patientsDestroy: 'patients.destroy',
  /** /configuracion (provisoria en la F0; solo el administrador). */
  settingsUpdate: 'system-settings.update',
} as const;

export interface WithPermissions {
  permissions: readonly string[];
}

/** ¿El usuario tiene el permiso? Sin usuario (sin sesión o sin `me` todavía), no. */
export function can(me: WithPermissions | null | undefined, permission: string): boolean {
  return me?.permissions.includes(permission) ?? false;
}
