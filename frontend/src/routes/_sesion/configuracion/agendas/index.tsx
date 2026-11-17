import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { ScheduleListPage } from '../../../../features/schedules/ScheduleListPage';
import { validateScheduleListSearch } from '../../../../features/schedules/search';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/agendas?buscar=&sucursal=&inactivas=&pagina= (F3, 3.1a): solo el administrador.
export const Route = createFileRoute('/_sesion/configuracion/agendas/')({
  validateSearch: validateScheduleListSearch,
  beforeLoad: requirePermission(PERMISSIONS.schedulesUpdate),
  component: ScheduleListPage,
});
