import { IconHome, IconSettings, type Icon } from '@tabler/icons-react';
import { PERMISSIONS } from '../lib/auth/permissions';

export interface MenuItem {
  to: '/' | '/configuracion';
  label: string;
  icon: Icon;
  /** Sin permiso: lo ve todo usuario con sesión. */
  permission?: string;
}

/** El menú: solo los módulos ya hechos (en la F0, Inicio y la Configuración provisoria, decisión del usuario en la 0.2b). */
export const MENU: readonly MenuItem[] = [
  { to: '/', label: 'Inicio', icon: IconHome },
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
