import { createFileRoute } from '@tanstack/react-router';
import { AppointmentNotFound } from '../../../features/appointments/AppointmentDetailPage';
import { AppointmentEditPage } from '../../../features/appointments/AppointmentFormPages';
import { loadAppointment } from '../../../features/appointments/loader';
import { validateEditAppointmentSearch } from '../../../features/appointments/search';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /turnos/$id/editar?reprogramar=1 (3.4; con `$id_`, fuera del detalle): la edición y la reprogramación, el mismo formulario.
export const Route = createFileRoute('/_sesion/turnos/$id_/editar')({
  validateSearch: validateEditAppointmentSearch,
  beforeLoad: requirePermission(PERMISSIONS.appointmentsUpdate),
  loader: loadAppointment,
  notFoundComponent: AppointmentNotFound,
  component: AppointmentEditRoute,
});

function AppointmentEditRoute() {
  const { id } = Route.useLoaderData();
  const { reprogramar } = Route.useSearch();

  return <AppointmentEditPage key={id} id={id} reschedule={reprogramar === 1} />;
}
