import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { loadSchedule } from '../../../../features/schedules/loader';
import { ScheduleNotFound } from '../../../../features/schedules/ScheduleDetailPage';
import { ScheduleEditPage } from '../../../../features/schedules/ScheduleFormPages';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/agendas/$id/editar (con `$id_`, fuera del detalle): solo el administrador.
export const Route = createFileRoute('/_sesion/configuracion/agendas/$id_/editar')({
  beforeLoad: requirePermission(PERMISSIONS.schedulesUpdate),
  loader: loadSchedule,
  notFoundComponent: ScheduleNotFound,
  component: ScheduleEditRoute,
});

function ScheduleEditRoute() {
  const { id } = Route.useLoaderData();

  return <ScheduleEditPage key={id} id={id} />;
}
