import { createFileRoute } from '@tanstack/react-router';
import { AgendaPage } from '../../../features/appointments/AgendaPage';
import { validateAgendaSearch } from '../../../features/appointments/search';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /agenda?vista=dia|semana&fecha=&agenda=&veterinario=&cancelados=&todos= (3.2 y 3.3): los tres roles.
export const Route = createFileRoute('/_sesion/agenda/')({
  validateSearch: validateAgendaSearch,
  beforeLoad: requirePermission(PERMISSIONS.appointmentsIndex),
  component: AgendaPage,
});
