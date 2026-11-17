import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { useState } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { Alert, Card, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { useCan } from '../auth/permissions';
import { userOptionsQueryOptions } from '../users/api';
import {
  afterVeterinarianChanged,
  createVeterinarian,
  updateVeterinarian,
  veterinarianDetailQueryOptions,
  veterinarianKeys,
} from './api';
import {
  EMPTY_VETERINARIAN_FORM,
  VETERINARIAN_EDIT_FIELDS,
  VETERINARIAN_FORM_FIELDS,
  veterinarianFormToCreatePayload,
  veterinarianFormToPayload,
  veterinarianToForm,
  type VeterinarianFormValues,
} from './form';
import { VETERINARIAN_USER_OPTIONS } from './loader';
import { useVeterinarianListSearch, withVeterinarianList } from './navigation';
import type { VeterinarianDetail } from './types';
import { useVeterinarianGone } from './use-veterinarian-gone';
import { VeterinarianForm } from './VeterinarianForm';

interface SaveVariables {
  values: VeterinarianFormValues;
  form: UseFormReturn<VeterinarianFormValues>;
}

interface SaveErrorContext {
  form: UseFormReturn<VeterinarianFormValues>;
  /** Los campos que se dibujan: un error de otro campo va a la alerta. */
  fields: readonly (keyof VeterinarianFormValues)[];
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

  switch (errorTreatment(error.errorCode)) {
    case 'validation': {
      const others = applyValidationErrors(error, context.form.setError, context.fields) ?? [];
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

function Breadcrumb({ veterinarian }: { veterinarian?: VeterinarianDetail }) {
  const listSearch = useVeterinarianListSearch();

  return (
    <div className="page-pretitle">
      <Link to="/veterinarios" search={listSearch}>
        Veterinarios
      </Link>
      {veterinarian !== undefined ? (
        <>
          {' / '}
          <Link
            to="/veterinarios/$id"
            params={{ id: String(veterinarian.id) }}
            state={withVeterinarianList(listSearch)}
          >
            {veterinarian.code}
          </Link>
        </>
      ) : null}
    </div>
  );
}

/**
 * /veterinarios/nuevo?usuario= (2.3): el alta de la ficha. El usuario se elige entre los activos con el rol veterinario y
 * sin ficha (con front-02, `veterinarian === null`); `?usuario=` lo deja elegido (el botón "Crear la ficha de veterinario"
 * del detalle del usuario). Con el 201, al detalle: el código `VET-` lo pone la API (o, si la ficha estaba eliminada, la
 * restaura con su código de siempre).
 */
export function VeterinarianCreatePage() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const can = useCan();
  const listSearch = useVeterinarianListSearch();
  const single = useSingleAction();
  const { usuario } = useSearch({ from: '/_sesion/veterinarios/nuevo' });
  const { data: users } = useSuspenseQuery(userOptionsQueryOptions(VETERINARIAN_USER_OPTIONS));
  const [alert, setAlert] = useState<string | null>(null);

  const available = users.filter((user) => user.is_active && user.veterinarian === null);
  const userOptions = available.map((user) => ({
    value: String(user.id),
    label: `${user.last_name}, ${user.first_name} (${user.email})`,
  }));
  // Uno de `?usuario=` que no se puede elegir (ya tiene ficha, no tiene el rol): no queda elegido.
  const preset =
    usuario !== undefined && available.some((user) => user.id === usuario) ? String(usuario) : '';

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) =>
      createVeterinarian(veterinarianFormToCreatePayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, { form, fields: VETERINARIAN_FORM_FIELDS, setAlert });
    },
    onSuccess: (response) => {
      afterVeterinarianChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/veterinarios/$id',
        params: { id: String(response.data.id) },
        replace: true,
        state: withVeterinarianList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
    },
  });

  // Se vuelve a donde se vino: el detalle del usuario (con `?usuario=`) o el listado.
  const cancel =
    usuario !== undefined && can(PERMISSIONS.usersShow) ? (
      <Link
        to="/configuracion/usuarios/$id"
        params={{ id: String(usuario) }}
        replace
        className="btn btn-link veterinarian-cancel"
      >
        Cancelar
      </Link>
    ) : (
      <Link
        to="/veterinarios"
        search={listSearch}
        replace
        className="btn btn-link veterinarian-cancel"
      >
        Cancelar
      </Link>
    );

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb />
        <h2 className="page-title">Nuevo veterinario</h2>
      </div>
      {userOptions.length === 0 ? (
        <Card title="Datos de la ficha">
          <Alert tone="info" title="No hay usuarios con el rol veterinario sin ficha.">
            {can(PERMISSIONS.usersStore) ? (
              <>
                Creá uno en{' '}
                <Link to="/configuracion/usuarios/nuevo" className="veterinarian-new-user">
                  Nuevo usuario
                </Link>{' '}
                o dale el rol veterinario a un usuario existente.
              </>
            ) : null}
          </Alert>
          {cancel}
        </Card>
      ) : (
        <VeterinarianForm
          defaultValues={{ ...EMPTY_VETERINARIAN_FORM, user_id: preset }}
          userOptions={userOptions}
          alert={alert}
          saving={mutation.isPending}
          onSubmit={(values, form) => {
            if (single.tryStart()) {
              mutation.mutate({ values, form });
            }
          }}
          cancel={cancel}
        />
      )}
    </>
  );
}

/**
 * /veterinarios/$id/editar: la matrícula, la especialidad, la comisión y las notas, sin `user_id` ni `code` (`prohibited`)
 * ni el estado; con el 200, de vuelta al detalle.
 */
export function VeterinarianEditPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useVeterinarianListSearch();
  const single = useSingleAction();
  const onGone = useVeterinarianGone(id);
  const { data: veterinarian } = useSuspenseQuery(veterinarianDetailQueryOptions(id));
  const [alert, setAlert] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) =>
      updateVeterinarian(id, veterinarianFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, {
        form,
        fields: VETERINARIAN_EDIT_FIELDS,
        setAlert,
        onGone,
        onConflict: () => {
          void queryClient.invalidateQueries({ queryKey: veterinarianKeys.detail(id) });
        },
      });
    },
    onSuccess: (response) => {
      afterVeterinarianChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/veterinarios/$id',
        params: { id: String(id) },
        replace: true,
        state: withVeterinarianList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb veterinarian={veterinarian} />
        <h2 className="page-title">Editar el veterinario</h2>
      </div>
      <VeterinarianForm
        defaultValues={veterinarianToForm(veterinarian)}
        userOptions={null}
        readOnly={
          <dl className="row veterinarian-fixed">
            <dt className="col-4 col-md-2">Código</dt>
            <dd className="col-8 col-md-4">{veterinarian.code}</dd>
            <dt className="col-4 col-md-2">Usuario</dt>
            <dd className="col-8 col-md-4">{veterinarian.user.name}</dd>
          </dl>
        }
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (single.tryStart()) {
            mutation.mutate({ values, form });
          }
        }}
        cancel={
          <Link
            to="/veterinarios/$id"
            params={{ id: String(id) }}
            state={withVeterinarianList(listSearch)}
            replace
            className="btn btn-link veterinarian-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}
