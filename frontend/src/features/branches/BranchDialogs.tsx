import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRef, useState, type ReactNode } from 'react';
import { CheckboxField, ConfirmDialog, TextField, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import type { ApiSuccess } from '../../lib/api/types';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { afterBranchChanged, createWarehouse, updateWarehouse } from './api';
import {
  CODE_MAX,
  EMPTY_WAREHOUSE_FORM,
  warehouseFormErrors,
  warehouseFormToPayload,
  warehouseToForm,
  type WarehouseFormValues,
} from './form';
import type { BranchDetail, Warehouse } from './types';

/**
 * Qué hace un diálogo con el error de su acción: un 404, `onGone` (aviso y lo que corresponda) y null; un 422 (la regla de
 * la por defecto, la sucursal inactiva) o un error de negocio (`*_DEFAULT_NOT_DEACTIVATABLE`), el mensaje de la API y solo
 * "Cerrar" (otro intento daría lo mismo); lo demás (el servidor, la red), el mensaje y el botón para probar de nuevo.
 */
function dialogError(
  error: unknown,
  onGone: (message: string) => void,
): { message: string; final: boolean } | null {
  if (!isApiError(error)) {
    return { message: MESSAGES.programming, final: false };
  }

  const treatment = errorTreatment(error.errorCode);

  if (treatment === 'not-found') {
    onGone(error.message);

    return null;
  }

  if (treatment === 'validation') {
    const messages = Object.values(error.fieldErrors()).flat();

    return { message: messages.length > 0 ? messages.join(' ') : error.message, final: true };
  }

  return { message: userMessage(error), final: treatment === 'business' };
}

interface StatusDialogProps<T> {
  title: string;
  confirmLabel: string;
  children: ReactNode;
  run: () => Promise<ApiSuccess<T>>;
  onDone: (response: ApiSuccess<T>) => void;
  onGone: (message: string) => void;
  onClose: () => void;
}

/**
 * Marcar por defecto, activar o inactivar una sucursal o un depósito (2.1): una confirmación (`tone="primary"`) y un solo
 * pedido. El 409 de la por defecto y los 422 quedan en el diálogo, con "Cerrar".
 */
export function StatusDialog<T>({
  title,
  confirmLabel,
  children,
  run,
  onDone,
  onGone,
  onClose,
}: StatusDialogProps<T>) {
  const toast = useToast();
  const single = useSingleAction();
  const [failure, setFailure] = useState<{ message: string; final: boolean } | null>(null);

  const mutation = useMutation({
    mutationFn: run,
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      onDone(response);
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
      title={title}
      confirmLabel={confirmLabel}
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
      {failure?.final === true ? null : children}
    </ConfirmDialog>
  );
}

type WarehouseErrors = Partial<Record<keyof WarehouseFormValues, string>>;

/** Las claves de `errors` del 422 que van a un campo del diálogo; las demás (`branch`: la sucursal inactiva), a la alerta. */
const WAREHOUSE_FIELDS = ['code', 'name', 'is_default'] as const;

interface WarehouseDialogProps {
  branch: BranchDetail;
  /** El depósito a editar; sin él, el alta. */
  warehouse?: Warehouse;
  onGone: (message: string) => void;
  onClose: () => void;
}

/**
 * El alta o la edición de un depósito, en un diálogo del detalle de la sucursal (pregunta 5 del diseño): código, nombre y
 * "Depósito por defecto" (en el por defecto, marcado y deshabilitado; H4). Los errores del 422 van a sus campos; el de la
 * sucursal inactiva (`branch`), a la alerta del diálogo.
 */
export function WarehouseDialog({ branch, warehouse, onGone, onClose }: WarehouseDialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const editing = warehouse !== undefined;
  const wasDefault = warehouse?.is_default ?? false;
  const [values, setValues] = useState<WarehouseFormValues>(() =>
    warehouse === undefined ? EMPTY_WAREHOUSE_FORM : warehouseToForm(warehouse),
  );
  const [errors, setErrors] = useState<WarehouseErrors>({});
  const [alert, setAlert] = useState<string | null>(null);
  const codeRef = useRef<HTMLInputElement>(null);
  const nameRef = useRef<HTMLInputElement>(null);

  const mutation = useMutation({
    mutationFn: (payload: ReturnType<typeof warehouseFormToPayload>) =>
      warehouse === undefined
        ? createWarehouse(branch.id, payload)
        : updateWarehouse(warehouse.id, payload),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      void afterBranchChanged(queryClient);
      toast.show(response.message, 'success');
      onClose();
    },
    onError: (error) => {
      if (isApiError(error) && errorTreatment(error.errorCode) === 'validation') {
        const found: WarehouseErrors = {};
        const others: string[] = [];

        for (const [key, messages] of Object.entries(error.fieldErrors())) {
          const field = WAREHOUSE_FIELDS.find((name) => name === key);

          if (field === undefined) {
            others.push(messages.join(' '));
          } else {
            found[field] = messages.join(' ');
          }
        }

        // El foco lo lleva ConfirmDialog al terminar la espera (al primer campo con error).
        setErrors(found);
        setAlert(others.length > 0 ? others.join(' ') : null);

        return;
      }

      const failure = dialogError(error, onGone);
      setAlert(failure?.message ?? null);
    },
  });

  const submit = () => {
    const found = warehouseFormErrors(values);
    setErrors(found);

    if (found.code !== undefined) {
      codeRef.current?.focus();

      return;
    }

    if (found.name !== undefined) {
      nameRef.current?.focus();

      return;
    }

    if (single.tryStart()) {
      mutation.mutate(warehouseFormToPayload(values, { wasDefault }));
    }
  };

  return (
    <ConfirmDialog
      open
      size="md"
      tone="primary"
      title={editing ? 'Editar el depósito' : 'Nuevo depósito'}
      confirmLabel={editing ? 'Guardar' : 'Crear el depósito'}
      busy={mutation.isPending}
      error={alert}
      onConfirm={submit}
      onClose={onClose}
    >
      <p>
        Sucursal <strong>{branch.name}</strong>.
      </p>
      <form
        className="row warehouse-form"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          submit();
        }}
      >
        <div className="col-sm-5">
          <TextField
            ref={codeRef}
            name="code"
            label="Código"
            required
            maxLength={CODE_MAX}
            autoComplete="off"
            value={values.code}
            error={errors.code}
            onChange={(event) => {
              setValues((current) => ({ ...current, code: event.target.value }));
            }}
          />
        </div>
        <div className="col-sm-7">
          <TextField
            ref={nameRef}
            name="name"
            label="Nombre"
            required
            autoComplete="off"
            value={values.name}
            error={errors.name}
            onChange={(event) => {
              setValues((current) => ({ ...current, name: event.target.value }));
            }}
          />
        </div>
        <div className="col-12">
          <CheckboxField
            name="is_default"
            label="Depósito por defecto"
            checked={wasDefault || values.is_default}
            disabled={wasDefault}
            hint={
              wasDefault
                ? 'Para cambiarlo, marcá otro depósito como el por defecto.'
                : 'El anterior deja de serlo.'
            }
            error={errors.is_default}
            onChange={(event) => {
              const checked = event.target.checked;
              setValues((current) => ({ ...current, is_default: checked }));
            }}
          />
        </div>
      </form>
    </ConfirmDialog>
  );
}
