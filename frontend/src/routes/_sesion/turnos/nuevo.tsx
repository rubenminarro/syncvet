import { createFileRoute } from '@tanstack/react-router';
import { AppointmentCreatePage } from '../../../features/appointments/AppointmentFormPages';
import { validateNewAppointmentSearch } from '../../../features/appointments/search';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /turnos/nuevo?agenda=&inicio= (3.4; gana sobre /turnos/$id): el alta, administrador y recepción.
export const Route = createFileRoute('/_sesion/turnos/nuevo')({
  validateSearch: validateNewAppointmentSearch,
  beforeLoad: requirePermission(PERMISSIONS.appointmentsStore),
  component: AppointmentCreateRoute,
});

function AppointmentCreateRoute() {
  const search = Route.useSearch();

  return <AppointmentCreatePage search={search} />;
}
