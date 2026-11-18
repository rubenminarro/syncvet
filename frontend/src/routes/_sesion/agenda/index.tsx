import { createFileRoute } from '@tanstack/react-router';
import { AgendaDayPage } from '../../../features/appointments/AgendaDayPage';
import { validateAgendaSearch } from '../../../features/appointments/search';
import { requirePermission } from '../../../features/auth/permissions';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /agenda?vista=dia&fecha=&agenda=&veterinario=&cancelados=&todos= (3.2): los tres roles.
export const Route = createFileRoute('/_sesion/agenda/')({
  validateSearch: validateAgendaSearch,
  beforeLoad: requirePermission(PERMISSIONS.appointmentsIndex),
  component: AgendaDayPage,
});
