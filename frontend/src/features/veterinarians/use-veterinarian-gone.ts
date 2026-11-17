import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useToast } from '../../components/ui';
import { userKeys } from '../users/api';
import { veterinarianKeys } from './api';
import { useVeterinarianListSearch } from './navigation';

/**
 * Un 404 en una acción (la ficha se eliminó en otra pestaña): aviso y vuelta al listado (frontend/CLAUDE.md §6). Los
 * listados se vacían antes de volver, los usuarios quedan viejos (su ficha) y el detalle sale de la caché después.
 */
export function useVeterinarianGone(id: number) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useVeterinarianListSearch();

  return (message: string) => {
    toast.show(message);
    void queryClient.resetQueries({ queryKey: veterinarianKeys.lists });
    void queryClient.invalidateQueries({ queryKey: userKeys.all });
    void navigate({ to: '/veterinarios', search: listSearch, replace: true }).then(() => {
      queryClient.removeQueries({ queryKey: veterinarianKeys.detail(id), exact: true });
    });
  };
}
