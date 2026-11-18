import { createFileRoute } from '@tanstack/react-router';
import { AppointmentListPage } from '../../../features/appointments/AppointmentListPage';
import { validateAppointmentListSearch } from '../../../features/appointments/search';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /turnos?desde=&hasta=&agenda=&veterinario=&estados=&buscar=&pagina= (3.3): la lista de turnos, los tres roles.
export const Route = createFileRoute('/_sesion/turnos/')({
  validateSearch: validateAppointmentListSearch,
  beforeLoad: requirePermission(PERMISSIONS.appointmentsIndex),
  component: AppointmentListPage,
});
