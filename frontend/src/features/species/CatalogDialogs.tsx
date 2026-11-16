import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRef, useState, type ReactNode } from 'react';
import { ConfirmDialog, TextField, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import type { ApiSuccess } from '../../lib/api/types';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import type { Breed, Species } from '../patients/types';
import { afterCatalogChanged } from './api';
import { NAME_MAX, parseCatalogName, type CatalogKind } from './name';

/** Un registro del catálogo: una especie o una raza, con su tipo (los textos y los pedidos dependen de él). */
export type CatalogItem = { kind: 'species'; item: Species } | { kind: 'breed'; item: Breed };

/**
 * Qué hace un diálogo con el error de su acción: un 404 (otra persona lo borró), aviso, el catálogo pedido de nuevo y el
 * diálogo cerrado; lo demás (CATALOG_GLOBAL_NOT_EDITABLE, que no es FORBIDDEN, el servidor, la red), el mensaje adentro.
 * Devuelve el texto para el diálogo, o null si ya se trató.
 */
function useCatalogError(onClose: () => void) {
  const queryClient = useQueryClient();
  const toast = useToast();

  return (error: unknown): string | null => {
    if (!isApiError(error)) {
      return MESSAGES.programming;
    }

    if (errorTreatment(error.errorCode) === 'not-found') {
      toast.show(error.message);
      // Como después de un cambio: las listas que no están a la vista (los selectores del paciente) se vacían.
      afterCatalogChanged(queryClient);
      onClose();

      return null;
    }

    return userMessage(error);
  };
}

const NOUN: Record<CatalogKind, string> = { species: 'la especie', breed: 'la raza' };

interface NameDialogProps {
  kind: CatalogKind;
  title: string;
  /** El nombre guardado, al renombrar; vacío, en el alta. */
  initialName?: string;
  confirmLabel: string;
  /** Un texto arriba del campo (la especie de la raza nueva). */
  intro?: ReactNode;
  save: (name: string) => Promise<ApiSuccess<unknown>>;
  onClose: () => void;
}

/**
 * Alta o cambio de nombre de una especie o una raza (un solo campo): el nombre se controla en el navegador con los mensajes
 * de la API y el repetido lo dice la API (422 en `name`), en el campo. Con el 201 o el 200, aviso y el catálogo de nuevo.
 */
export function NameDialog({
  kind,
  title,
  initialName = '',
  confirmLabel,
  intro,
  save,
  onClose,
}: NameDialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const onError = useCatalogError(onClose);
  const [name, setName] = useState(initialName);
  const [fieldError, setFieldError] = useState<string | undefined>(undefined);
  const [alert, setAlert] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const mutation = useMutation({
    mutationFn: save,
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterCatalogChanged(queryClient);
      toast.show(response.message, 'success');
      onClose();
    },
    onError: (error) => {
      // El nombre repetido o inválido para la API va al campo; el foco lo lleva ConfirmDialog al terminar la espera.
      const nameError = isApiError(error) ? error.fieldErrors().name?.join(' ') : undefined;

      if (nameError !== undefined) {
        setFieldError(nameError);

        return;
      }

      setAlert(onError(error));
    },
  });

  const submit = () => {
    const parsed = parseCatalogName(kind, name);

    if (!parsed.ok) {
      setFieldError(parsed.error);
      inputRef.current?.focus();

      return;
    }

    setFieldError(undefined);

    if (single.tryStart()) {
      mutation.mutate(parsed.name);
    }
  };

  return (
    <ConfirmDialog
      open
      size="md"
      tone="primary"
      title={title}
      confirmLabel={confirmLabel}
      busy={mutation.isPending}
      error={alert}
      onConfirm={submit}
      onClose={onClose}
    >
      {intro ? <p>{intro}</p> : null}
      <form
        className="catalog-name-form"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          submit();
        }}
      >
        <TextField
          ref={inputRef}
          name="name"
          label="Nombre"
          required
          autoComplete="off"
          hint={`De 2 a ${String(NAME_MAX[kind])} caracteres.`}
          value={name}
          error={fieldError}
          onChange={(event) => {
            setName(event.target.value);
          }}
        />
      </form>
    </ConfirmDialog>
  );
}

const IN_USE = new Set(['SPECIES_IN_USE', 'BREED_IN_USE']);

interface DeleteDialogProps {
  target: CatalogItem;
  /** Con `species.update`: con *_IN_USE se ofrece inactivar (si está activa). */
  canInactivate: boolean;
  remove: () => Promise<ApiSuccess<null>>;
  inactivate: () => Promise<ApiSuccess<unknown>>;
  onClose: () => void;
}

/**
 * La baja de una especie o una raza propia, con confirmación. Con SPECIES_IN_USE o BREED_IN_USE (409: la usa algún paciente,
 * también uno eliminado), el mensaje de la API y el botón "Inactivar" (si está activa y se puede editar), que la inactiva
 * desde el mismo diálogo; si no, solo "Cerrar".
 */
export function DeleteDialog({
  target,
  canInactivate,
  remove,
  inactivate,
  onClose,
}: DeleteDialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const onError = useCatalogError(onClose);
  const [alert, setAlert] = useState<string | null>(null);
  const [inUse, setInUse] = useState(false);
  const offerInactivate = inUse && canInactivate && target.item.is_active;
  const noun = NOUN[target.kind];

  const done = (message: string) => {
    afterCatalogChanged(queryClient);
    toast.show(message, 'success');
    onClose();
  };

  const deletion = useMutation({
    mutationFn: remove,
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      done(response.message);
    },
    onError: (error) => {
      if (isApiError(error) && IN_USE.has(error.errorCode)) {
        setInUse(true);
        setAlert(error.message);

        return;
      }

      setAlert(onError(error));
    },
  });

  const inactivation = useMutation({
    mutationFn: inactivate,
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      done(response.message);
    },
    onError: (error) => {
      setAlert(onError(error));
    },
  });

  let title = `Eliminar ${noun}`;
  let confirmLabel = `Eliminar ${target.item.name}`;

  if (offerInactivate) {
    title = `Inactivar ${noun}`;
    confirmLabel = `Inactivar ${target.item.name}`;
  }

  return (
    <ConfirmDialog
      open
      tone={offerInactivate ? 'primary' : 'danger'}
      title={title}
      confirmLabel={confirmLabel}
      busy={deletion.isPending || inactivation.isPending}
      error={alert}
      canConfirm={!inUse || offerInactivate}
      onConfirm={() => {
        if (!single.tryStart()) {
          return;
        }

        if (offerInactivate) {
          inactivation.mutate();
        } else {
          deletion.mutate();
        }
      }}
      onClose={onClose}
    >
      {/* Con *_IN_USE ya no se elimina: queda solo el mensaje de la API (en la alerta), que ofrece inactivarla. */}
      {inUse ? null : (
        <>
          ¿Querés eliminar {noun} <strong>{target.item.name}</strong>?
          {target.kind === 'species' ? ' También se eliminan sus razas propias.' : ''} No se puede
          deshacer.
        </>
      )}
    </ConfirmDialog>
  );
}
