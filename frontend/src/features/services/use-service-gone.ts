import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useToast } from '../../components/ui';
import { afterServiceGone } from './api';
import { useServiceListSearch } from './navigation';

/**
 * Un 404 en una acción (el servicio se eliminó en otra pestaña): aviso y vuelta al listado (frontend/CLAUDE.md §6). Los
 * listados se vacían antes de volver y el detalle sale de la caché después.
 */
export function useServiceGone(id: number) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useServiceListSearch();

  return (message: string) => {
    toast.show(message);
    const forgetDetail = afterServiceGone(queryClient, id);
    void navigate({ to: '/servicios', search: listSearch, replace: true }).then(forgetDetail);
  };
}
