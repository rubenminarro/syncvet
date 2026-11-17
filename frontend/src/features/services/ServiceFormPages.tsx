import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import {
  afterServiceChanged,
  createService,
  serviceDetailQueryOptions,
  serviceKeys,
  updateService,
} from './api';
import {
  EMPTY_SERVICE_FORM,
  SERVICE_FORM_FIELDS,
  serviceFormToPayload,
  serviceToForm,
  type ServiceFormValues,
} from './form';
import { useServiceListSearch, withServiceList } from './navigation';
import { ServiceForm } from './ServiceForm';
import type { ServiceDetail } from './types';
import { useServiceGone } from './use-service-gone';

/** Un servicio ya usado no cambia de nombre (backend/CLAUDE.md §6.7): el mensaje de la API va al campo. */
export const SERVICE_NAME_NOT_EDITABLE = 'SERVICE_NAME_NOT_EDITABLE';

interface SaveVariables {
  values: ServiceFormValues;
  form: UseFormReturn<ServiceFormValues>;
}

interface SaveErrorContext {
  form: UseFormReturn<ServiceFormValues>;
  setAlert: (alert: string | null) => void;
  onGone?: (message: string) => void;
  onConflict?: () => void;
}

/** Qué hace el formulario con cada error de la API (frontend/CLAUDE.md §6). */
function handleSaveError(error: unknown, context: SaveErrorContext) {
  if (!isApiError(error)) {
    context.setAlert(MESSAGES.programming);

    return;
  }

  if (error.errorCode === SERVICE_NAME_NOT_EDITABLE) {
    context.form.setError('name', { message: error.message }, { shouldFocus: true });

    return;
  }

  switch (errorTreatment(error.errorCode)) {
    case 'validation': {
      const others = applyValidationErrors(error, context.form.setError, SERVICE_FORM_FIELDS) ?? [];
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

function Breadcrumb({ service }: { service?: ServiceDetail }) {
  const listSearch = useServiceListSearch();

  return (
    <div className="page-pretitle">
      <Link to="/servicios" search={listSearch}>
        Servicios
      </Link>
      {service !== undefined ? (
        <>
          {' / '}
          <Link
            to="/servicios/$id"
            params={{ id: String(service.id) }}
            state={withServiceList(listSearch)}
          >
            {service.code}
          </Link>
        </>
      ) : null}
    </div>
  );
}

/** /servicios/nuevo (2.4): con el 201, al detalle (el código `SRV-` lo pone la API). */
export function ServiceCreatePage() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useServiceListSearch();
  const single = useSingleAction();
  const [alert, setAlert] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) => createService(serviceFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, { form, setAlert });
    },
    onSuccess: (response) => {
      afterServiceChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/servicios/$id',
        params: { id: String(response.data.id) },
        replace: true,
        state: withServiceList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb />
        <h2 className="page-title">Nuevo servicio</h2>
      </div>
      <ServiceForm
        defaultValues={EMPTY_SERVICE_FORM}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (single.tryStart()) {
            mutation.mutate({ values, form });
          }
        }}
        cancel={
          <Link to="/servicios" search={listSearch} replace className="btn btn-link service-cancel">
            Cancelar
          </Link>
        }
      />
    </>
  );
}

/**
 * /servicios/$id/editar: el formulario completo, sin `code` (`prohibited`) ni el estado; con el 200, de vuelta al detalle.
 * Un servicio usado no cambia de nombre: `SERVICE_NAME_NOT_EDITABLE` va al campo.
 */
export function ServiceEditPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useServiceListSearch();
  const single = useSingleAction();
  const onGone = useServiceGone(id);
  const { data: service } = useSuspenseQuery(serviceDetailQueryOptions(id));
  const [alert, setAlert] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) => updateService(id, serviceFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, {
        form,
        setAlert,
        onGone,
        onConflict: () => {
          void queryClient.invalidateQueries({ queryKey: serviceKeys.detail(id) });
        },
      });
    },
    onSuccess: (response) => {
      afterServiceChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/servicios/$id',
        params: { id: String(id) },
        replace: true,
        state: withServiceList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb service={service} />
        <h2 className="page-title">Editar el servicio</h2>
      </div>
      <ServiceForm
        defaultValues={serviceToForm(service)}
        code={service.code}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (single.tryStart()) {
            mutation.mutate({ values, form });
          }
        }}
        cancel={
          <Link
            to="/servicios/$id"
            params={{ id: String(id) }}
            state={withServiceList(listSearch)}
            replace
            className="btn btn-link service-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}
