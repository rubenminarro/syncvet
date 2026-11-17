import { createFileRoute } from '@tanstack/react-router';
import { ConfigurationPage } from '../../../app/ConfigurationPage';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /configuracion (2.1): la página de entrada de la administración, solo con system-settings.update; sin él, /sin-permiso.
export const Route = createFileRoute('/_sesion/configuracion/')({
  beforeLoad: requirePermission(PERMISSIONS.settingsUpdate),
  component: ConfigurationPage,
});
