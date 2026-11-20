import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { ScheduleCreatePage } from '../../../../features/schedules/ScheduleFormPages';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/agendas/nueva (gana sobre $id): solo el administrador.
export const Route = createFileRoute('/_sesion/configuracion/agendas/nueva')({
  beforeLoad: requirePermission(PERMISSIONS.schedulesStore),
  component: ScheduleCreatePage,
});
