import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useToast } from '../../components/ui';
import { userKeys } from './api';
import { useUserListSearch } from './navigation';

/**
 * Un 404 al guardar (el usuario se eliminó en otra pestaña, o es de otra clínica): aviso y vuelta al listado
 * (frontend/CLAUDE.md §6). Los listados se vacían antes de volver y el detalle sale de la caché después.
 */
export function useUserGone(id: number) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useUserListSearch();

  return (message: string) => {
    toast.show(message);
    void queryClient.resetQueries({ queryKey: userKeys.lists });
    void navigate({ to: '/configuracion/usuarios', search: listSearch, replace: true }).then(() => {
      queryClient.removeQueries({ queryKey: userKeys.detail(id), exact: true });
    });
  };
}
