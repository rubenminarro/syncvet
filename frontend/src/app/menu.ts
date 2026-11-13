import { IconHome, IconSettings, IconUsers, type Icon } from '@tabler/icons-react';
import { PERMISSIONS } from '../lib/auth/permissions';

export interface MenuItem {
  to: '/' | '/clientes' | '/configuracion';
  label: string;
  icon: Icon;
  /** Sin permiso: lo ve todo usuario con sesión. */
  permission?: string;
}

/** El menú: solo los módulos ya hechos (Inicio; Clientes, desde la 1.1; la Configuración provisoria de la 0.2b). */
export const MENU: readonly MenuItem[] = [
  { to: '/', label: 'Inicio', icon: IconHome },
  { to: '/clientes', label: 'Clientes', icon: IconUsers, permission: PERMISSIONS.clientsIndex },
  {
    to: '/configuracion',
    label: 'Configuración',
    icon: IconSettings,
    permission: PERMISSIONS.settingsUpdate,
  },
];

/** Los ítems que el usuario puede ver, según sus permisos. */
export function visibleMenu(
  items: readonly MenuItem[],
  allowed: (permission: string) => boolean,
): MenuItem[] {
  return items.filter((item) => item.permission === undefined || allowed(item.permission));
}
