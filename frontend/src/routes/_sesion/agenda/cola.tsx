import { createFileRoute } from '@tanstack/react-router';
import { QueuePage } from '../../../features/appointments/QueuePage';
import { validateQueueSearch } from '../../../features/appointments/search';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /agenda/cola?fecha=&todos= (3.7): la cola del día de la sucursal actual, los tres roles.
export const Route = createFileRoute('/_sesion/agenda/cola')({
  validateSearch: validateQueueSearch,
  beforeLoad: requirePermission(PERMISSIONS.appointmentsQueue),
  component: QueuePage,
});
