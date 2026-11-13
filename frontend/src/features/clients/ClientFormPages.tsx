import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useRef, useState } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import {
  afterClientGone,
  afterClientSaved,
  clientDetailQueryOptions,
  clientKeys,
  createClient,
  updateClient,
} from './api';
import { ClientForm } from './ClientForm';
import {
  CLIENT_FORM_FIELDS,
  clientFormToPayload,
  clientToForm,
  EMPTY_CLIENT_FORM,
  type ClientFormValues,
} from './form';
import { useClientListSearch, withClientList } from './navigation';
import type { ClientKind } from './types';

/** El tipo no cambia si el cliente tiene facturas (backend/CLAUDE.md §6.1). */
const KIND_NOT_EDITABLE = 'CLIENT_KIND_NOT_EDITABLE';

interface SaveErrorContext {
  form: UseFormReturn<ClientFormValues>;
  setAlert: (message: string | null) => void;
  /** En la edición: el tipo guardado, al que vuelve el formulario con CLIENT_KIND_NOT_EDITABLE. */
  savedKind?: ClientKind;
  /** Un 404 en la acción: el cliente ya no está. */
  onGone?: (message: string) => void;
  onConflict?: () => void;
}

/** Qué hace el formulario con cada error de la API (frontend/CLAUDE.md §6). */
function handleSaveError(error: unknown, context: SaveErrorContext) {
  if (!isApiError(error)) {
    context.setAlert(MESSAGES.programming);

    return;
  }

  if (error.errorCode === KIND_NOT_EDITABLE) {
    context.setAlert(error.message);

    if (context.savedKind !== undefined) {
      context.form.setValue('kind', context.savedKind);
    }

    return;
  }

  switch (errorTreatment(error.errorCode)) {
    case 'validation': {
      const others = applyValidationErrors(error, context.form.setError, CLIENT_FORM_FIELDS) ?? [];
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
      // FORBIDDEN, los de negocio, el servidor y la red: el mensaje arriba y el formulario como estaba.
      context.setAlert(userMessage(error));
  }
}

/** /clientes/nuevo: el alta; con el 201, al detalle del cliente nuevo. */
export function ClientCreatePage() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useClientListSearch();
  const [alert, setAlert] = useState<string | null>(null);
  const inFlight = useRef(false);

  const mutation = useMutation({
    mutationFn: (values: ClientFormValues) => createClient(clientFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      inFlight.current = false;
    },
    onSuccess: (response) => {
      afterClientSaved(queryClient, response.data, { edited: false });
      toast.show(response.message, 'success');
      void navigate({
        to: '/clientes/$id',
        params: { id: String(response.data.id) },
        replace: true,
        state: withClientList(listSearch),
      });
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/clientes" search={listSearch}>
            Clientes
          </Link>
        </div>
        <h2 className="page-title">Nuevo cliente</h2>
      </div>
      <ClientForm
        title="Datos del cliente"
        defaultValues={EMPTY_CLIENT_FORM}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          // Un solo envío a la vez: dos handleSubmit seguidos llegan acá antes de que el botón se deshabilite.
          if (inFlight.current) {
            return;
          }

          inFlight.current = true;
          mutation.mutate(values, {
            onError: (error) => {
              handleSaveError(error, { form, setAlert });
            },
          });
        }}
        cancel={
          <Link to="/clientes" search={listSearch} replace className="btn btn-link client-cancel">
            Cancelar
          </Link>
        }
      />
    </>
  );
}

/** /clientes/$id/editar: la edición, con el formulario completo; con el 200, de vuelta al detalle. */
export function ClientEditPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useClientListSearch();
  const { data: client } = useSuspenseQuery(clientDetailQueryOptions(id));
  const [alert, setAlert] = useState<string | null>(null);
  const inFlight = useRef(false);

  const mutation = useMutation({
    mutationFn: (values: ClientFormValues) => updateClient(id, clientFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      inFlight.current = false;
    },
    onSuccess: (response) => {
      afterClientSaved(queryClient, response.data, { edited: true });
      toast.show(response.message, 'success');
      void navigate({
        to: '/clientes/$id',
        params: { id: String(id) },
        replace: true,
        state: withClientList(listSearch),
      });
    },
  });

  const onGone = (message: string) => {
    toast.show(message);
    const forgetDetail = afterClientGone(queryClient, id);
    void navigate({ to: '/clientes', search: listSearch, replace: true }).then(forgetDetail);
  };

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/clientes" search={listSearch}>
            Clientes
          </Link>
          {' / '}
          <Link to="/clientes/$id" params={{ id: String(id) }} state={withClientList(listSearch)}>
            {client.display_name}
          </Link>
        </div>
        <h2 className="page-title">Editar el cliente</h2>
      </div>
      <ClientForm
        title="Datos del cliente"
        defaultValues={clientToForm(client)}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          // Un solo envío a la vez: dos handleSubmit seguidos llegan acá antes de que el botón se deshabilite.
          if (inFlight.current) {
            return;
          }

          inFlight.current = true;
          mutation.mutate(values, {
            onError: (error) => {
              handleSaveError(error, {
                form,
                setAlert,
                savedKind: client.kind.value,
                onGone,
                onConflict: () => {
                  void queryClient.invalidateQueries({ queryKey: clientKeys.detail(id) });
                },
              });
            },
          });
        }}
        cancel={
          <Link
            to="/clientes/$id"
            params={{ id: String(id) }}
            state={withClientList(listSearch)}
            replace
            className="btn btn-link client-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}
