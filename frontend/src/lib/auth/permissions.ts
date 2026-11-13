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
