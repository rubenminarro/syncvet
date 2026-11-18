import {
  IconCalendar,
  IconCategory,
  IconClipboardList,
  IconHome,
  IconPaw,
  IconSettings,
  IconStethoscope,
  IconUsers,
  type Icon,
} from '@tabler/icons-react';
import { PERMISSIONS } from '../lib/auth/permissions';

export interface MenuItem {
  to:
    | '/'
    | '/clientes'
    | '/pacientes'
    | '/agenda'
    | '/especies'
    | '/servicios'
    | '/veterinarios'
    | '/configuracion';
  label: string;
  icon: Icon;
  /** Sin permiso: lo ve todo usuario con sesión. */
  permission?: string;
}

/**
 * El menú: solo los módulos ya hechos (Inicio; Clientes, desde la 1.1; Pacientes, desde la 1.3a; Especies, desde la 1.5
 * (pregunta 8 del diseño: menú propio); Agenda, desde la 3.2 (los tres roles, después de Pacientes); Servicios, desde la 2.4, y Veterinarios, desde la 2.3 (los tres roles); la
 * Configuración, al final).
 */
export const MENU: readonly MenuItem[] = [
  { to: '/', label: 'Inicio', icon: IconHome },
  { to: '/clientes', label: 'Clientes', icon: IconUsers, permission: PERMISSIONS.clientsIndex },
  { to: '/pacientes', label: 'Pacientes', icon: IconPaw, permission: PERMISSIONS.patientsIndex },
  { to: '/agenda', label: 'Agenda', icon: IconCalendar, permission: PERMISSIONS.appointmentsIndex },
  { to: '/especies', label: 'Especies', icon: IconCategory, permission: PERMISSIONS.speciesIndex },
  {
    to: '/servicios',
    label: 'Servicios',
    icon: IconClipboardList,
    permission: PERMISSIONS.servicesIndex,
  },
  {
    to: '/veterinarios',
    label: 'Veterinarios',
    icon: IconStethoscope,
    permission: PERMISSIONS.veterinariansIndex,
  },
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
