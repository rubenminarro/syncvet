import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { loadSchedule } from '../../../../features/schedules/loader';
import {
  ScheduleDetailPage,
  ScheduleNotFound,
} from '../../../../features/schedules/ScheduleDetailPage';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/agendas/$id (solo el administrador): un id inválido y el 404 de la API son la página de no encontrado.
export const Route = createFileRoute('/_sesion/configuracion/agendas/$id')({
  beforeLoad: requirePermission(PERMISSIONS.schedulesUpdate),
  loader: loadSchedule,
  notFoundComponent: ScheduleNotFound,
  component: ScheduleDetailRoute,
});

function ScheduleDetailRoute() {
  const { id } = Route.useLoaderData();

  // key: otro id es otra pantalla (el estado local no pasa de una agenda a otra).
  return <ScheduleDetailPage key={id} id={id} />;
}
