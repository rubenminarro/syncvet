import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import { Button, ConfirmDialog, useToast } from '../../components/ui';
import { dialogError, type DialogFailure } from '../../lib/api/dialog-error';
import { isApiError } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { useCan } from '../auth/permissions';
import { afterServiceChanged, afterServiceGone, deleteService, setServiceStatus } from './api';
import { useServiceListSearch } from './navigation';
import type { ServiceDetail } from './types';
import { useServiceGone } from './use-service-gone';

/** El servicio ya se usó (backend/CLAUDE.md §6.7): se ofrece inactivarlo. */
export const SERVICE_IN_USE = 'SERVICE_IN_USE';

type ServiceDialog = 'status' | 'delete';

/**
 * Inactivar o activar (`services.update`) y eliminar (`services.destroy`) desde el detalle (2.4, pregunta 13 del diseño de
 * la F2): botones con su confirmación, no un campo del formulario. El recepcionista y el veterinario no los ven.
 */
export function ServiceActions({ service }: { service: ServiceDetail }) {
  const can = useCan();
  const [dialog, setDialog] = useState<ServiceDialog | null>(null);
  const canUpdate = can(PERMISSIONS.servicesUpdate);
  const canDestroy = can(PERMISSIONS.servicesDestroy);
  const active = service.status.value === 'active';

  const close = () => {
    setDialog(null);
  };

  return (
    <>
      {canUpdate ? (
        <Button
          variant="secondary"
          className="service-status-action"
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
          className="service-delete"
          onClick={() => {
            setDialog('delete');
          }}
        >
          Eliminar
        </Button>
      ) : null}
      {dialog === 'status' ? <ServiceStatusDialog service={service} onClose={close} /> : null}
      {dialog === 'delete' ? (
        <DeleteServiceDialog
          service={service}
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
  service: ServiceDetail;
  onClose: () => void;
}

/** Inactivar o activar: manda solo `{ status }` (se inactiva aunque esté usado); con el 200, el detalle toma la respuesta. */
export function ServiceStatusDialog({ service, onClose }: DialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useServiceGone(service.id);
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const activate = service.status.value !== 'active';

  const mutation = useMutation({
    mutationFn: () => setServiceStatus(service.id, activate ? 'active' : 'inactive'),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterServiceChanged(queryClient, response.data);
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
      title={activate ? 'Activar el servicio' : 'Inactivar el servicio'}
      confirmLabel={activate ? `Activar ${service.name}` : `Inactivar ${service.name}`}
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
          ¿Querés activar <strong>{service.name}</strong>? Se va a poder usar de nuevo en la agenda.
        </>
      ) : (
        <>
          ¿Querés inactivar <strong>{service.name}</strong>? Lo que ya lo usa se conserva y lo podés
          volver a activar.
        </>
      )}
    </ConfirmDialog>
  );
}

/**
 * La baja, con confirmación. Con SERVICE_IN_USE, el mensaje de la API y, si está activo y se puede editar, "Inactivar",
 * que cierra este diálogo y abre el de inactivar (como veterinarios); si ya está inactivo, el mensaje y "Cerrar". Con el
 * 200, al listado: los listados se vacían y el detalle sale de la caché al llegar.
 */
export function DeleteServiceDialog({
  service,
  canInactivate,
  onClose,
  onInactivate,
}: DialogProps & { canInactivate: boolean; onInactivate: () => void }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const single = useSingleAction();
  const onGone = useServiceGone(service.id);
  const listSearch = useServiceListSearch();
  const [failure, setFailure] = useState<DialogFailure | null>(null);
  const [inUse, setInUse] = useState(false);
  const offerInactivate = inUse && canInactivate && service.status.value === 'active';

  const mutation = useMutation({
    mutationFn: () => deleteService(service.id),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      toast.show(response.message, 'success');
      const forgetDetail = afterServiceGone(queryClient, service.id);
      void navigate({ to: '/servicios', search: listSearch, replace: true }).then(forgetDetail);
    },
    onError: (error) => {
      if (isApiError(error) && error.errorCode === SERVICE_IN_USE) {
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
      title="Eliminar el servicio"
      confirmLabel={offerInactivate ? 'Inactivar' : `Eliminar ${service.name}`}
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
      {/* Con un error que no se arregla probando de nuevo (SERVICE_IN_USE), queda solo el mensaje de la API. */}
      {failure?.final === true ? null : (
        <>
          ¿Querés eliminar <strong>{service.name}</strong>? No se puede deshacer.
        </>
      )}
    </ConfirmDialog>
  );
}
