import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import {
  afterBranchChanged,
  branchDetailQueryOptions,
  branchKeys,
  createBranch,
  updateBranch,
} from './api';
import { BranchForm } from './BranchForm';
import {
  BRANCH_FORM_FIELDS,
  branchFormToCreatePayload,
  branchFormToPayload,
  branchToForm,
  EMPTY_BRANCH_FORM,
  type BranchFormValues,
} from './form';
import { useBranchListSearch, withBranchList } from './navigation';
import { useBranchGone } from './use-branch-gone';

/** El establecimiento no cambia si la sucursal ya tiene facturas (backend/CLAUDE.md §6.4): el error va al campo. */
export const ESTABLISHMENT_NOT_EDITABLE = 'BRANCH_ESTABLISHMENT_NOT_EDITABLE';

interface SaveErrorContext {
  form: UseFormReturn<BranchFormValues>;
  setAlert: (message: string | null) => void;
  onGone?: (message: string) => void;
  onConflict?: () => void;
}

/** Qué hace el formulario con cada error de la API (frontend/CLAUDE.md §6 y el diseño de la 2.1). */
function handleSaveError(error: unknown, context: SaveErrorContext) {
  if (!isApiError(error)) {
    context.setAlert(MESSAGES.programming);

    return;
  }

  if (error.errorCode === ESTABLISHMENT_NOT_EDITABLE) {
    context.form.setError('establishment', { message: error.message }, { shouldFocus: true });

    return;
  }

  switch (errorTreatment(error.errorCode)) {
    case 'validation': {
      const others = applyValidationErrors(error, context.form.setError, BRANCH_FORM_FIELDS) ?? [];
      context.setAlert(others.length > 0 ? others.join(' ') : null);
      break;
    }
    case 'not-found':
      if (context.onGone !== undefined) {
        context.onGone(error.message);
      } else {
        context.setAlert(userMessage(error));
      }
      break;
    case 'conflict':
      context.setAlert(userMessage(error));
      context.onConflict?.();
      break;
    default:
      context.setAlert(userMessage(error));
  }
}

/** /configuracion/sucursales/nueva: el alta con su depósito por defecto; con el 201, al detalle de la sucursal nueva. */
export function BranchCreatePage() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useBranchListSearch();
  const single = useSingleAction();
  const [alert, setAlert] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: (values: BranchFormValues) => createBranch(branchFormToCreatePayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      void afterBranchChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/configuracion/sucursales/$id',
        params: { id: String(response.data.id) },
        replace: true,
        state: withBranchList(listSearch),
      });
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/configuracion">Configuración</Link>
          {' / '}
          <Link to="/configuracion/sucursales" search={listSearch}>
            Sucursales
          </Link>
        </div>
        <h2 className="page-title">Nueva sucursal</h2>
      </div>
      <BranchForm
        title="Datos de la sucursal"
        defaultValues={EMPTY_BRANCH_FORM}
        creating
        wasDefault={false}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (!single.tryStart()) {
            return;
          }

          mutation.mutate(values, {
            onError: (error) => {
              handleSaveError(error, { form, setAlert });
            },
          });
        }}
        cancel={
          <Link
            to="/configuracion/sucursales"
            search={listSearch}
            replace
            className="btn btn-link branch-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}

/** /configuracion/sucursales/$id/editar: el formulario completo, sin el depósito; con el 200, de vuelta al detalle. */
export function BranchEditPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useBranchListSearch();
  const single = useSingleAction();
  const onGone = useBranchGone(id);
  const { data: branch } = useSuspenseQuery(branchDetailQueryOptions(id));
  const [alert, setAlert] = useState<string | null>(null);
  // La por defecto al abrir el formulario: manda siempre `is_default: true` (H4).
  const [wasDefault] = useState(branch.is_default);

  const mutation = useMutation({
    mutationFn: (values: BranchFormValues) =>
      updateBranch(id, branchFormToPayload(values, { wasDefault })),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      void afterBranchChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/configuracion/sucursales/$id',
        params: { id: String(id) },
        replace: true,
        state: withBranchList(listSearch),
      });
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/configuracion">Configuración</Link>
          {' / '}
          <Link to="/configuracion/sucursales" search={listSearch}>
            Sucursales
          </Link>
          {' / '}
          <Link
            to="/configuracion/sucursales/$id"
            params={{ id: String(id) }}
            state={withBranchList(listSearch)}
          >
            {branch.name}
          </Link>
        </div>
        <h2 className="page-title">Editar la sucursal</h2>
      </div>
      <BranchForm
        title="Datos de la sucursal"
        defaultValues={branchToForm(branch)}
        creating={false}
        wasDefault={wasDefault}
        inactive={!branch.is_active}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (!single.tryStart()) {
            return;
          }

          mutation.mutate(values, {
            onError: (error) => {
              handleSaveError(error, {
                form,
                setAlert,
                onGone,
                onConflict: () => {
                  void queryClient.invalidateQueries({ queryKey: branchKeys.detail(id) });
                },
              });
            },
          });
        }}
        cancel={
          <Link
            to="/configuracion/sucursales/$id"
            params={{ id: String(id) }}
            state={withBranchList(listSearch)}
            replace
            className="btn btn-link branch-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}
