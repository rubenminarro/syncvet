import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useToast } from '../../components/ui';
import { afterScheduleGone } from './api';
import { useScheduleListSearch } from './navigation';

/**
 * Un 404 en una acción (la agenda se eliminó en otra pestaña): aviso y vuelta al listado (frontend/CLAUDE.md §6). Los
 * listados se vacían antes de volver y el detalle sale de la caché después.
 */
export function useScheduleGone(id: number) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useScheduleListSearch();

  return (message: string) => {
    toast.show(message);
    const forgetDetail = afterScheduleGone(queryClient, id);
    void navigate({ to: '/configuracion/agendas', search: listSearch, replace: true }).then(
      forgetDetail,
    );
  };
}
