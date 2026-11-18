import { useSearch } from '@tanstack/react-router';
import { AgendaDayPage } from './AgendaDayPage';
import { AgendaWeekPage } from './AgendaWeekPage';

/** /agenda: el día (sin `vista` o con `vista=dia`, 3.2) o la semana (`vista=semana`, 3.3). */
export function AgendaPage() {
  const { vista } = useSearch({ from: '/_sesion/agenda/' });

  return vista === 'semana' ? <AgendaWeekPage /> : <AgendaDayPage />;
}
