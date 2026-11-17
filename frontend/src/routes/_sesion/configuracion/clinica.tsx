import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { settingsQueryOptions } from '../../../features/settings/api';
import { ClinicSettingsPage } from '../../../features/settings/ClinicSettingsPage';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /configuracion/clinica (2.5): los datos de la clínica y el logo, solo el administrador (system-settings.update; el diseño
// de la F2). La configuración se pide antes de dibujar: un 403, la de sin permiso; una falla, la de error con "Reintentar".
export const Route = createFileRoute('/_sesion/configuracion/clinica')({
  beforeLoad: requirePermission(PERMISSIONS.settingsUpdate),
  loader: async ({ context }) => {
    // Siempre fresca al entrar (staleTime 0): el formulario arranca con lo guardado, no con una copia vieja.
    await context.queryClient.query(settingsQueryOptions());
  },
  component: ClinicSettingsPage,
});
