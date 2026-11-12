import { IconBuildingStore } from '@tabler/icons-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { useSessionManager } from '../../lib/auth/use-session';
import { applyBranchChange, authKeys, updateCurrentBranch } from './api';
import type { Me } from './types';

/**
 * La sucursal actual en la barra: un selector con `me.branches` (con una sola sucursal, que ya es la actual, solo el nombre).
 * El valor sale de `me.current_branch`: si el cambio falla, vuelve solo a la actual.
 */
export function BranchSelector({ me }: { me: Me }) {
  const queryClient = useQueryClient();
  const manager = useSessionManager();
  const toast = useToast();

  const mutation = useMutation({
    mutationFn: updateCurrentBranch,
    onSuccess: async (updated) => {
      await applyBranchChange(queryClient, updated);
      manager.publishBranchChanged();
      toast.show(`Tu sucursal actual es ${updated.current_branch?.name ?? ''}.`, 'success');
    },
    onError: (error) => {
      toast.show(isApiError(error) ? userMessage(error) : MESSAGES.programming);

      // Un 422: la sucursal ya no está disponible (inactivada o desasignada); la lista pudo cambiar.
      if (isApiError(error) && errorTreatment(error.errorCode) === 'validation') {
        void queryClient.invalidateQueries({ queryKey: authKeys.me });
      }
    },
  });

  const current = me.current_branch;
  const onlyCurrent =
    me.branches.length === 0 ||
    (me.branches.length === 1 && current !== null && me.branches[0]?.id === current.id);

  return (
    <div className="d-flex align-items-center gap-2 text-secondary">
      <IconBuildingStore size={20} stroke={1.5} aria-hidden="true" />
      <div className="lh-sm">
        <div className="session-clinic d-none d-md-block">{me.clinic.name}</div>
        {onlyCurrent ? (
          <div className="small session-branch">{current?.name ?? 'Sin sucursal actual'}</div>
        ) : (
          <select
            className="form-select form-select-sm branch-selector"
            aria-label="Sucursal actual"
            value={current === null ? '' : String(current.id)}
            disabled={mutation.isPending}
            onChange={(event) => {
              const id = Number(event.target.value);

              if (Number.isInteger(id) && id > 0 && id !== current?.id) {
                mutation.mutate(id);
              }
            }}
          >
            {current === null ? (
              <option value="" disabled>
                Elegí tu sucursal
              </option>
            ) : null}
            {me.branches.map((branch) => (
              <option key={branch.id} value={branch.id}>
                {branch.name}
              </option>
            ))}
          </select>
        )}
      </div>
    </div>
  );
}
