import { createFileRoute } from '@tanstack/react-router';
import { ConfigurationPage } from '../../app/ConfigurationPage';
import { requirePermission } from '../../features/auth/permissions';
import { PERMISSIONS } from '../../lib/auth/permissions';

// Provisoria en la F0 (decisión del usuario en la 0.2b): solo con system-settings.update; sin él, /sin-permiso.
export const Route = createFileRoute('/_sesion/configuracion')({
  beforeLoad: requirePermission(PERMISSIONS.settingsUpdate),
  component: ConfigurationPage,
});
