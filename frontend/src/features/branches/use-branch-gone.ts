import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useToast } from '../../components/ui';
import { branchKeys } from './api';
import { useBranchListSearch } from './navigation';

/**
 * Un 404 en una acción (la sucursal ya no está, o es de otra clínica): aviso y vuelta al listado. Las sucursales no se
 * borran, pero la regla de la tabla de errores es la misma (frontend/CLAUDE.md §6): los listados se vacían antes de volver y
 * el detalle sale de la caché después.
 */
export function useBranchGone(id: number) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useBranchListSearch();

  return (message: string) => {
    toast.show(message);
    void queryClient.resetQueries({ queryKey: branchKeys.lists });
    void navigate({ to: '/configuracion/sucursales', search: listSearch, replace: true }).then(
      () => {
        queryClient.removeQueries({ queryKey: branchKeys.detail(id), exact: true });
      },
    );
  };
}
