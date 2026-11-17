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
  /**
   * /configuracion, la página de entrada (F2, entrega 2.1; provisoria en la F0), y /configuracion/clinica con Guardar (F2,
   * entrega 2.5): solo el administrador.
   */
  settingsUpdate: 'system-settings.update',
  /** Ver el logo de la clínica, en la barra y en la configuración (F2, entrega 2.5): los tres roles. */
  settingsShowLogo: 'system-settings.show-logo',
  /** Subir o reemplazar el logo (F2, entrega 2.5): solo el administrador. */
  settingsUploadLogo: 'system-settings.upload-logo',
  /** Quitar el logo (F2, entrega 2.5): solo el administrador. */
  settingsDeleteLogo: 'system-settings.delete-logo',
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
  /** /configuracion/usuarios (F2, entrega 2.2a): solo el administrador. */
  usersIndex: 'users.index',
  /** /configuracion/usuarios/$id (F2, entrega 2.2a): solo el administrador. */
  usersShow: 'users.show',
  /** /configuracion/usuarios/nuevo (F2, entrega 2.2a): solo el administrador. */
  usersStore: 'users.store',
  /** /configuracion/usuarios/$id/editar (F2, entrega 2.2a): solo el administrador. */
  usersUpdate: 'users.update',
  /** Eliminar un usuario desde su detalle (F2, entrega 2.2b): solo el administrador. */
  usersDestroy: 'users.destroy',
  /** /veterinarios (F2, entrega 2.3): los tres roles. */
  veterinariansIndex: 'veterinarians.index',
  /** /veterinarios/$id, con la comisión y las notas (F2, entrega 2.3): solo el administrador. */
  veterinariansShow: 'veterinarians.show',
  /** /veterinarios/nuevo y "Crear la ficha de veterinario" (F2, entrega 2.3): solo el administrador. */
  veterinariansStore: 'veterinarians.store',
  /** /veterinarios/$id/editar, activar e inactivar (F2, entrega 2.3): solo el administrador. */
  veterinariansUpdate: 'veterinarians.update',
  /** Eliminar la ficha desde su detalle (F2, entrega 2.3): solo el administrador. */
  veterinariansDestroy: 'veterinarians.destroy',
  /** /servicios (F2, entrega 2.4): los tres roles. */
  servicesIndex: 'services.index',
  /** /servicios/$id (F2, entrega 2.4): los tres roles; las acciones, solo el administrador. */
  servicesShow: 'services.show',
  /** /servicios/nuevo (F2, entrega 2.4): solo el administrador. */
  servicesStore: 'services.store',
  /** /servicios/$id/editar, activar e inactivar (F2, entrega 2.4): solo el administrador. */
  servicesUpdate: 'services.update',
  /** Eliminar el servicio desde su detalle (F2, entrega 2.4): solo el administrador. */
  servicesDestroy: 'services.destroy',
  /** /configuracion/auditoria (F2, entrega 2.6): solo el administrador. */
  auditLogsIndex: 'audit-logs.index',
  /** /configuracion/auditoria/$id (F2, entrega 2.6): solo el administrador. */
  auditLogsShow: 'audit-logs.show',
  /**
   * /configuracion/agendas, su detalle y su edición, e inactivar o activar (F3, entrega 3.1a): solo el administrador
   * (`schedules.index` y `.show` los tienen los tres; la pantalla es de la administración, como la tarjeta).
   */
  schedulesUpdate: 'schedules.update',
  /** /configuracion/agendas/nueva (F3, entrega 3.1a): solo el administrador. */
  schedulesStore: 'schedules.store',
  /** Eliminar la agenda desde su detalle (F3, entrega 3.1a): solo el administrador. */
  schedulesDestroy: 'schedules.destroy',
  /** GET roles, para el filtro y el formulario de usuarios (F2, entrega 2.2a): solo el administrador. */
  rolesIndex: 'roles.index',
} as const;

export interface WithPermissions {
  permissions: readonly string[];
}

/** ¿El usuario tiene el permiso? Sin usuario (sin sesión o sin `me` todavía), no. */
export function can(me: WithPermissions | null | undefined, permission: string): boolean {
  return me?.permissions.includes(permission) ?? false;
}
