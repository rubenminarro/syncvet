import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import { Button, ConfirmDialog, useToast } from '../../components/ui';
import { dialogError, type DialogFailure } from '../../lib/api/dialog-error';
import { isApiError } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { useCan } from '../auth/permissions';
import { afterScheduleChanged, afterScheduleGone, deleteSchedule, setScheduleActive } from './api';
import { useScheduleListSearch } from './navigation';
import type { ScheduleDetail } from './types';
import { useScheduleGone } from './use-schedule-gone';

/** La agenda tiene turnos (backend/CLAUDE.md §6.10): se ofrece inactivarla. */
export const SCHEDULE_IN_USE = 'SCHEDULE_IN_USE';

type ScheduleDialog = 'status' | 'delete';

/**
 * Inactivar o activar (`schedules.update`) y eliminar (`schedules.destroy`) desde el detalle (3.1a): botones con su
 * confirmación, no un campo del formulario.
 */
export function ScheduleActions({ schedule }: { schedule: ScheduleDetail }) {
  const can = useCan();
  const [dialog, setDialog] = useState<ScheduleDialog | null>(null);
  const canUpdate = can(PERMISSIONS.schedulesUpdate);
  const canDestroy = can(PERMISSIONS.schedulesDestroy);

  const close = () => {
    setDialog(null);
  };

  return (
    <>
      {canUpdate ? (
        <Button
          variant="secondary"
          className="schedule-status-action"
          onClick={() => {
            setDialog('status');
          }}
        >
          {schedule.is_active ? 'Inactivar' : 'Activar'}
        </Button>
      ) : null}
      {canDestroy ? (
        <Button
          variant="danger"
          className="schedule-delete"
          onClick={() => {
            setDialog('delete');
          }}
        >
          Eliminar
        </Button>
      ) : null}
      {dialog === 'status' ? <ScheduleStatusDialog schedule={schedule} onClose={close} /> : null}
      {dialog === 'delete' ? (
        <DeleteScheduleDialog
          schedule={schedule}
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
  schedule: ScheduleDetail;
  onClose: () => void;
}

/**
 * Inactivar o activar: manda solo `{ is_active }` (se inactiva aunque tenga turnos: los que tiene siguen y no recibe
 * nuevos); con el 200, el detalle toma la respuesta.
 */
export function ScheduleStatusDialog({ schedule, onClose }: DialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useScheduleGone(schedule.id);
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const activate = !schedule.is_active;

  const mutation = useMutation({
    mutationFn: () => setScheduleActive(schedule.id, activate),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterScheduleChanged(queryClient, response.data);
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
      title={activate ? 'Activar la agenda' : 'Inactivar la agenda'}
      confirmLabel={activate ? `Activar ${schedule.name}` : `Inactivar ${schedule.name}`}
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
          ¿Querés activar <strong>{schedule.name}</strong>? Va a recibir turnos de nuevo en su
          horario.
        </>
      ) : (
        <>
          ¿Querés inactivar <strong>{schedule.name}</strong>? No recibe turnos nuevos. Los turnos
          que ya tiene siguen: reprogramalos o cancelalos.
        </>
      )}
    </ConfirmDialog>
  );
}

/**
 * La baja, con confirmación. Con SCHEDULE_IN_USE, el mensaje de la API y, si está activa y se puede editar, "Inactivar",
 * que cierra este diálogo y abre el de inactivar (como servicios); si ya está inactiva, el mensaje y "Cerrar". Con el 200,
 * al listado: los listados se vacían y el detalle sale de la caché al llegar.
 */
export function DeleteScheduleDialog({
  schedule,
  canInactivate,
  onClose,
  onInactivate,
}: DialogProps & { canInactivate: boolean; onInactivate: () => void }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useScheduleGone(schedule.id);
  const listSearch = useScheduleListSearch();
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const [inUse, setInUse] = useState(false);
  const offerInactivate = inUse && canInactivate && schedule.is_active;

  const mutation = useMutation({
    mutationFn: () => deleteSchedule(schedule.id),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      toast.show(response.message, 'success');
      const forgetDetail = afterScheduleGone(queryClient, schedule.id);
      void navigate({ to: '/configuracion/agendas', search: listSearch, replace: true }).then(
        forgetDetail,
      );
    },
    onError: (error) => {
      if (isApiError(error) && error.errorCode === SCHEDULE_IN_USE) {
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
      title="Eliminar la agenda"
      confirmLabel={offerInactivate ? 'Inactivar' : `Eliminar ${schedule.name}`}
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
      {/* Con un error que no se arregla probando de nuevo (SCHEDULE_IN_USE), queda solo el mensaje de la API. */}
      {failure?.final === true ? null : (
        <>
          ¿Querés eliminar <strong>{schedule.name}</strong>, con su horario y sus bloqueos? No se
          puede deshacer.
        </>
      )}
    </ConfirmDialog>
  );
}
