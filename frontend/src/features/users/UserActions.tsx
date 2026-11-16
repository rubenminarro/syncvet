import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import { Button, ConfirmDialog, useToast } from '../../components/ui';
import { dialogError, type DialogFailure } from '../../lib/api/dialog-error';
import { isApiError } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { useCan } from '../auth/permissions';
import { afterUserChanged, afterUserGone, deleteUser, setUserActive } from './api';
import { useUserListSearch } from './navigation';
import type { UserDetail } from './types';
import { useUserGone } from './use-user-gone';

/** El usuario tiene registros a su nombre (backend/CLAUDE.md §6.5): se ofrece inactivarlo. */
const USER_IN_USE = 'USER_IN_USE';

type UserDialog = 'status' | 'delete';

/**
 * Inactivar o reactivar (`users.update`) y eliminar (`users.destroy`) desde el detalle (2.2b, pregunta 13 del diseño). Nunca
 * en el propio usuario: la API lo rechaza (USER_SELF_ACTION) y el botón no se ofrece.
 */
export function UserActions({ user, isSelf }: { user: UserDetail; isSelf: boolean }) {
  const can = useCan();
  const [dialog, setDialog] = useState<UserDialog | null>(null);
  const canUpdate = can(PERMISSIONS.usersUpdate);
  const canDestroy = can(PERMISSIONS.usersDestroy);

  if (isSelf) {
    return null;
  }

  const close = () => {
    setDialog(null);
  };

  return (
    <>
      {canUpdate ? (
        <Button
          variant="secondary"
          className="user-status"
          onClick={() => {
            setDialog('status');
          }}
        >
          {user.is_active ? 'Inactivar' : 'Reactivar'}
        </Button>
      ) : null}
      {canDestroy ? (
        <Button
          variant="danger"
          className="user-delete"
          onClick={() => {
            setDialog('delete');
          }}
        >
          Eliminar
        </Button>
      ) : null}
      {dialog === 'status' ? <UserStatusDialog user={user} onClose={close} /> : null}
      {dialog === 'delete' ? (
        <DeleteUserDialog
          user={user}
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
  user: UserDetail;
  onClose: () => void;
}

/**
 * Inactivar (avisa que se cierran todas sus sesiones y que no va a poder ingresar) o reactivar (va a tener que ingresar de
 * nuevo: reactivar no devuelve los tokens). Manda solo `{ is_active }`; con el 200, el detalle toma la respuesta y los
 * usuarios y los veterinarios quedan viejos.
 */
export function UserStatusDialog({ user, onClose }: DialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useUserGone(user.id);
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const activate = !user.is_active;

  const mutation = useMutation({
    mutationFn: () => setUserActive(user.id, activate),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      void afterUserChanged(queryClient, response.data);
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
      title={activate ? 'Reactivar el usuario' : 'Inactivar el usuario'}
      confirmLabel={activate ? `Reactivar a ${user.name}` : `Inactivar a ${user.name}`}
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
      {/* Con un error que no se arregla probando de nuevo, queda solo el mensaje de la API (como la 1.5). */}
      {failure?.final === true ? null : activate ? (
        <>
          ¿Querés reactivar a <strong>{user.name}</strong>? Va a tener que ingresar de nuevo.
        </>
      ) : (
        <>
          ¿Querés inactivar a <strong>{user.name}</strong>? Se van a cerrar todas sus sesiones y no
          va a poder ingresar hasta que lo reactives.
        </>
      )}
    </ConfirmDialog>
  );
}

/**
 * La baja, con confirmación. Con USER_IN_USE, el mensaje de la API y, si está activo y se puede editar, "Inactivar", que
 * cierra este diálogo y abre el de inactivar (como el fallecimiento del paciente); con USER_LAST_ADMIN o USER_SELF_ACTION,
 * el mensaje y "Cerrar". Con el 200, al listado: los listados se vacían y el detalle sale de la caché al llegar.
 */
export function DeleteUserDialog({
  user,
  canInactivate,
  onClose,
  onInactivate,
}: DialogProps & { canInactivate: boolean; onInactivate: () => void }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useUserGone(user.id);
  const listSearch = useUserListSearch();
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const [inUse, setInUse] = useState(false);
  const offerInactivate = inUse && canInactivate && user.is_active;

  const mutation = useMutation({
    mutationFn: () => deleteUser(user.id),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      toast.show(response.message, 'success');
      const forgetDetail = afterUserGone(queryClient, user.id);
      void navigate({ to: '/configuracion/usuarios', search: listSearch, replace: true }).then(
        forgetDetail,
      );
    },
    onError: (error) => {
      if (isApiError(error) && error.errorCode === USER_IN_USE) {
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
      title="Eliminar el usuario"
      confirmLabel={offerInactivate ? 'Inactivar' : `Eliminar a ${user.name}`}
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
      {/* Con un error que no se arregla probando de nuevo (USER_IN_USE, USER_LAST_ADMIN), queda solo el mensaje de la API. */}
      {failure?.final === true ? null : (
        <>
          ¿Querés eliminar a <strong>{user.name}</strong>? No se puede deshacer.
        </>
      )}
    </ConfirmDialog>
  );
}
