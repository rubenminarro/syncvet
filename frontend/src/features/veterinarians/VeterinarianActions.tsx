import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import { Button, ConfirmDialog, useToast } from '../../components/ui';
import { dialogError, type DialogFailure } from '../../lib/api/dialog-error';
import { isApiError } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { useCan } from '../auth/permissions';
import {
  afterVeterinarianChanged,
  afterVeterinarianGone,
  deleteVeterinarian,
  setVeterinarianStatus,
} from './api';
import { useVeterinarianListSearch } from './navigation';
import type { VeterinarianDetail } from './types';
import { useVeterinarianGone } from './use-veterinarian-gone';

/** El veterinario tiene registros (backend/CLAUDE.md §6.6): se ofrece inactivarlo. */
export const VETERINARIAN_IN_USE = 'VETERINARIAN_IN_USE';

type VeterinarianDialog = 'status' | 'delete';

/**
 * Inactivar o activar (`veterinarians.update`) y eliminar (`veterinarians.destroy`) desde el detalle (2.3, pregunta 13 del
 * diseño de la F2): botones con su confirmación, no un campo del formulario.
 */
export function VeterinarianActions({ veterinarian }: { veterinarian: VeterinarianDetail }) {
  const can = useCan();
  const [dialog, setDialog] = useState<VeterinarianDialog | null>(null);
  const canUpdate = can(PERMISSIONS.veterinariansUpdate);
  const canDestroy = can(PERMISSIONS.veterinariansDestroy);
  const active = veterinarian.status.value === 'active';

  const close = () => {
    setDialog(null);
  };

  return (
    <>
      {canUpdate ? (
        <Button
          variant="secondary"
          className="veterinarian-status-action"
          onClick={() => {
            setDialog('status');
          }}
        >
          {active ? 'Inactivar' : 'Activar'}
        </Button>
      ) : null}
      {canDestroy ? (
        <Button
          variant="danger"
          className="veterinarian-delete"
          onClick={() => {
            setDialog('delete');
          }}
        >
          Eliminar
        </Button>
      ) : null}
      {dialog === 'status' ? (
        <VeterinarianStatusDialog veterinarian={veterinarian} onClose={close} />
      ) : null}
      {dialog === 'delete' ? (
        <DeleteVeterinarianDialog
          veterinarian={veterinarian}
          canInactivate={canUpdate}
          onClose={close}
          onInactivate={() => {
            setDialog('status');
          }}
        />
      ) : null}
    </>
  );
}

interface DialogProps {
  veterinarian: VeterinarianDetail;
  onClose: () => void;
}

/** Inactivar o activar: manda solo `{ status }` (se inactiva aunque esté en uso); con el 200, el detalle toma la respuesta. */
export function VeterinarianStatusDialog({ veterinarian, onClose }: DialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useVeterinarianGone(veterinarian.id);
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const activate = veterinarian.status.value !== 'active';
  const name = veterinarian.user.name;

  const mutation = useMutation({
    mutationFn: () => setVeterinarianStatus(veterinarian.id, activate ? 'active' : 'inactive'),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterVeterinarianChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      onClose();
    },
    onError: (error) => {
      setFailure(dialogError(error, onGone));
    },
  });

  return (
    <ConfirmDialog
      open
      tone="primary"
      title={activate ? 'Activar el veterinario' : 'Inactivar el veterinario'}
      confirmLabel={activate ? `Activar a ${name}` : `Inactivar a ${name}`}
      busy={mutation.isPending}
      error={failure?.message ?? null}
      canConfirm={failure?.final !== true}
      onConfirm={() => {
        if (single.tryStart()) {
          mutation.mutate();
        }
      }}
      onClose={onClose}
    >
      {failure?.final === true ? null : activate ? (
        <>
          ¿Querés activar la ficha de <strong>{name}</strong>?
        </>
      ) : (
        <>
          ¿Querés inactivar la ficha de <strong>{name}</strong>? Sus registros se conservan y la
          podés volver a activar.
        </>
      )}
    </ConfirmDialog>
  );
}

/**
 * La baja, con confirmación. Con VETERINARIAN_IN_USE, el mensaje de la API y, si está activa y se puede editar,
 * "Inactivar", que cierra este diálogo y abre el de inactivar (como usuarios); si ya está inactiva, el mensaje y "Cerrar".
 * Con el 200, al listado: los listados se vacían y el detalle sale de la caché al llegar.
 */
export function DeleteVeterinarianDialog({
  veterinarian,
  canInactivate,
  onClose,
  onInactivate,
}: DialogProps & { canInactivate: boolean; onInactivate: () => void }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useVeterinarianGone(veterinarian.id);
  const listSearch = useVeterinarianListSearch();
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const [inUse, setInUse] = useState(false);
  const offerInactivate = inUse && canInactivate && veterinarian.status.value === 'active';
  const name = veterinarian.user.name;

  const mutation = useMutation({
    mutationFn: () => deleteVeterinarian(veterinarian.id),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      toast.show(response.message, 'success');
      const forgetDetail = afterVeterinarianGone(queryClient, veterinarian);
      void navigate({ to: '/veterinarios', search: listSearch, replace: true }).then(forgetDetail);
    },
    onError: (error) => {
      if (isApiError(error) && error.errorCode === VETERINARIAN_IN_USE) {
        setInUse(true);
        setFailure({ message: error.message, final: true });

        return;
      }

      setFailure(dialogError(error, onGone));
    },
  });

  return (
    <ConfirmDialog
      open
      tone={offerInactivate ? 'primary' : 'danger'}
      title="Eliminar el veterinario"
      confirmLabel={offerInactivate ? 'Inactivar' : `Eliminar a ${name}`}
      busy={mutation.isPending}
      error={failure?.message ?? null}
      canConfirm={offerInactivate || failure?.final !== true}
      onConfirm={() => {
        if (offerInactivate) {
          onInactivate();

          return;
        }

        if (single.tryStart()) {
          mutation.mutate();
        }
      }}
      onClose={onClose}
    >
      {/* Con un error que no se arregla probando de nuevo (VETERINARIAN_IN_USE), queda solo el mensaje de la API. */}
      {failure?.final === true ? null : (
        <>
          ¿Querés eliminar la ficha de veterinario de <strong>{name}</strong>? El usuario no se
          elimina.
        </>
      )}
    </ConfirmDialog>
  );
}
