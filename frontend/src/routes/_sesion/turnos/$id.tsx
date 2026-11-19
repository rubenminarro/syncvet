import { createFileRoute } from '@tanstack/react-router';
import {
  AppointmentDetailPage,
  AppointmentNotFound,
} from '../../../features/appointments/AppointmentDetailPage';
import { loadAppointment } from '../../../features/appointments/loader';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /turnos/$id (3.4): el detalle del turno, los tres roles. Un id que no es un entero positivo y el 404 de la API son la
// página de no encontrado; un 403, la de sin permiso; una falla de red o un 5xx, la de error con "Reintentar".
export const Route = createFileRoute('/_sesion/turnos/$id')({
  beforeLoad: requirePermission(PERMISSIONS.appointmentsShow),
  loader: loadAppointment,
  notFoundComponent: AppointmentNotFound,
  component: AppointmentDetailRoute,
});

function AppointmentDetailRoute() {
  const { id } = Route.useLoaderData();

  // key: otro id es otra pantalla (la regla de los detalles).
  return <AppointmentDetailPage key={id} id={id} />;
}
