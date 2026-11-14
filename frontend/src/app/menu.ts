import { IconHome, IconPaw, IconSettings, IconUsers, type Icon } from '@tabler/icons-react';
import { PERMISSIONS } from '../lib/auth/permissions';

export interface MenuItem {
  to: '/' | '/clientes' | '/pacientes' | '/configuracion';
  label: string;
  icon: Icon;
  /** Sin permiso: lo ve todo usuario con sesión. */
  permission?: string;
}

/**
 * El menú: solo los módulos ya hechos (Inicio; Clientes, desde la 1.1; Pacientes, desde la 1.3a; la Configuración
 * provisoria de la 0.2b).
 */
export const MENU: readonly MenuItem[] = [
  { to: '/', label: 'Inicio', icon: IconHome },
  { to: '/clientes', label: 'Clientes', icon: IconUsers, permission: PERMISSIONS.clientsIndex },
  { to: '/pacientes', label: 'Pacientes', icon: IconPaw, permission: PERMISSIONS.patientsIndex },
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
