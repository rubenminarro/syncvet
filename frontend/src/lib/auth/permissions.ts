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
  /** Ver la foto en el detalle (F1, entrega 1.4): los tres roles. */
  patientsShowPhoto: 'patients.show-photo',
  /** Subir o reemplazar la foto (F1, entrega 1.4): los tres roles. */
  patientsUploadPhoto: 'patients.upload-photo',
  /** Quitar la foto (F1, entrega 1.4): los tres roles. */
  patientsDeletePhoto: 'patients.delete-photo',
  /** /especies (F1, entrega 1.5): los tres roles. */
  speciesIndex: 'species.index',
  /** Alta de especies y razas (F1, entrega 1.5): administrador y veterinario. */
  speciesStore: 'species.store',
  /** Renombrar, activar e inactivar especies y razas propias (F1, entrega 1.5): administrador y veterinario. */
  speciesUpdate: 'species.update',
  /** La baja de especies y razas propias (F1, entrega 1.5): administrador y veterinario. */
  speciesDestroy: 'species.destroy',
  /** /configuracion, la página de entrada (F2, entrega 2.1; provisoria en la F0): solo el administrador. */
  settingsUpdate: 'system-settings.update',
  /** /configuracion/sucursales y su detalle (F2, entrega 2.1): solo el administrador (`branches.index` lo tienen los tres). */
  branchesShow: 'branches.show',
  /** /configuracion/sucursales/nueva (F2, entrega 2.1): solo el administrador. */
  branchesStore: 'branches.store',
  /** Editar, marcar por defecto, activar e inactivar una sucursal (F2, entrega 2.1): solo el administrador. */
  branchesUpdate: 'branches.update',
  /** "Nuevo depósito" en el detalle de la sucursal (F2, entrega 2.1): solo el administrador. */
  warehousesStore: 'warehouses.store',
  /** Editar, marcar por defecto, activar e inactivar un depósito (F2, entrega 2.1): solo el administrador. */
  warehousesUpdate: 'warehouses.update',
} as const;

export interface WithPermissions {
  permissions: readonly string[];
}

/** ¿El usuario tiene el permiso? Sin usuario (sin sesión o sin `me` todavía), no. */
export function can(me: WithPermissions | null | undefined, permission: string): boolean {
  return me?.permissions.includes(permission) ?? false;
}
