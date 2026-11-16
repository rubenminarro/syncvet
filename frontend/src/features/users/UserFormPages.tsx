import { useMutation, useQuery, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useState, type ReactNode } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { ConfirmDialog, useToast, type CheckboxOption } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { meQueryOptions } from '../auth/api';
import { branchOptionsQueryOptions } from '../branches/api';
import type { Branch } from '../branches/types';
import {
  afterUserChanged,
  createUser,
  rolesQueryOptions,
  updateUser,
  userDetailQueryOptions,
  userKeys,
} from './api';
import {
  EMPTY_USER_FORM,
  USER_FORM_FIELDS,
  userFormToCreatePayload,
  userFormToPayload,
  userToForm,
  type UserFormMode,
  type UserFormValues,
} from './form';
import { useUserListSearch, withUserList } from './navigation';
import { ADMIN_ROLE, type UserDetail } from './types';
import { UserForm } from './UserForm';
import { useUserGone } from './use-user-gone';

/** Quitarle el rol veterinario a quien tiene ficha (backend/CLAUDE.md §6.6): la alerta lleva el código de la ficha. */
export const USER_IS_VETERINARIAN = 'USER_IS_VETERINARIAN';

type FormAlert = { title: string; detail?: ReactNode } | null;

/** Lo que recibe la mutación: los valores (con la contraseña, si la hay) y el formulario, para volcar sus errores. */
interface SaveVariables {
  values: UserFormValues;
  form: UseFormReturn<UserFormValues>;
}

interface SaveErrorContext {
  form: UseFormReturn<UserFormValues>;
  /** Los campos que se dibujan: un error de otro campo va a la alerta (no a un campo que no está). */
  fields: readonly (keyof UserFormValues)[];
  setAlert: (alert: FormAlert) => void;
  /** La ficha del usuario editado, para la alerta de USER_IS_VETERINARIAN. */
  veterinarian?: UserDetail['veterinarian'];
  onGone?: (message: string) => void;
  onConflict?: () => void;
}

/** Qué hace el formulario con cada error de la API (frontend/CLAUDE.md §6 y el diseño de la 2.2a). */
function handleSaveError(error: unknown, context: SaveErrorContext) {
  if (!isApiError(error)) {
    context.setAlert({ title: MESSAGES.programming });

    return;
  }

  if (error.errorCode === USER_IS_VETERINARIAN) {
    const code = context.veterinarian?.code;
    context.setAlert({
      title: error.message,
      ...(code === undefined ? {} : { detail: `Ficha de veterinario: ${code}.` }),
    });

    return;
  }

  switch (errorTreatment(error.errorCode)) {
    case 'validation': {
      const others = applyValidationErrors(error, context.form.setError, context.fields) ?? [];
      context.setAlert(others.length > 0 ? { title: others.join(' ') } : null);
      break;
    }
    case 'not-found':
      if (context.onGone !== undefined) {
        context.onGone(error.message);
      } else {
        context.setAlert({ title: userMessage(error) });
      }
      break;
    case 'conflict':
      context.setAlert({ title: userMessage(error) });
      context.onConflict?.();
      break;
    default:
      // USER_LAST_ADMIN y los demás de negocio: el mensaje de la API, arriba del formulario.
      context.setAlert({ title: userMessage(error) });
  }
}

function branchLabel(branch: Pick<Branch, 'code' | 'name'>, active: boolean): string {
  const label = `${branch.code} · ${branch.name}`;

  return active ? label : `${label} (inactivada)`;
}

/**
 * Las sucursales que se pueden elegir: las activas y, al editar, también las inactivas que el usuario ya tiene (la API las
 * acepta; el formulario manda la lista completa), marcadas "(inactivada)".
 */
function useBranchOptions(user?: UserDetail): CheckboxOption<number>[] {
  const { data: branches } = useSuspenseQuery(branchOptionsQueryOptions());
  const kept = new Set(user?.branches.map((branch) => branch.id) ?? []);

  return branches
    .filter((branch) => branch.is_active || kept.has(branch.id))
    .map((branch) => ({ value: branch.id, label: branchLabel(branch, branch.is_active) }));
}

function Breadcrumb({ user }: { user?: UserDetail }) {
  const listSearch = useUserListSearch();

  return (
    <div className="page-pretitle">
      <Link to="/configuracion">Configuración</Link>
      {' / '}
      <Link to="/configuracion/usuarios" search={listSearch}>
        Usuarios
      </Link>
      {user !== undefined ? (
        <>
          {' / '}
          <Link
            to="/configuracion/usuarios/$id"
            params={{ id: String(user.id) }}
            state={withUserList(listSearch)}
          >
            {user.name}
          </Link>
        </>
      ) : null}
    </div>
  );
}

/** /configuracion/usuarios/nuevo: el alta con su contraseña; con el 201, al detalle del usuario nuevo. */
export function UserCreatePage() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useUserListSearch();
  const single = useSingleAction();
  const { data: roles } = useSuspenseQuery(rolesQueryOptions());
  const branchOptions = useBranchOptions();
  const [alert, setAlert] = useState<FormAlert>(null);

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) => createUser(userFormToCreatePayload(values)),
    // La contraseña no queda en la caché de mutaciones (convención de la 0.2b): gcTime 0 y reset() al terminar. Por eso los
    // errores se tratan acá y no en las opciones de mutate(), que reset() descarta.
    gcTime: 0,
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, { form, fields: USER_FORM_FIELDS, setAlert });
    },
    onSuccess: (response) => {
      void afterUserChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/configuracion/usuarios/$id',
        params: { id: String(response.data.id) },
        replace: true,
        state: withUserList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
      mutation.reset();
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb />
        <h2 className="page-title">Nuevo usuario</h2>
      </div>
      <UserForm
        mode="create"
        defaultValues={EMPTY_USER_FORM}
        roles={roles}
        branchOptions={branchOptions}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (single.tryStart()) {
            mutation.mutate({ values, form });
          }
        }}
        cancel={
          <Link
            to="/configuracion/usuarios"
            search={listSearch}
            replace
            className="btn btn-link user-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}

/**
 * /configuracion/usuarios/$id/editar: el formulario completo (sin `password` si no se cambia); con el 200, de vuelta al
 * detalle. El propio usuario: sin contraseña y, si se quita el rol de administrador, primero la confirmación (pregunta 7
 * del diseño) y, con el 200, `me` de nuevo y a `/` (ya no puede ver esta pantalla).
 */
export function UserEditPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useUserListSearch();
  const single = useSingleAction();
  const onGone = useUserGone(id);
  const { data: user } = useSuspenseQuery(userDetailQueryOptions(id));
  const { data: roles } = useSuspenseQuery(rolesQueryOptions());
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branchOptions = useBranchOptions(user);
  const [alert, setAlert] = useState<FormAlert>(null);
  const [pendingSelfDemotion, setPendingSelfDemotion] = useState<SaveVariables | null>(null);
  // Quién es se decide al abrir el formulario: si `me` cambia mientras tanto, el formulario no cambia de forma.
  const [mode] = useState<UserFormMode>(me?.id === user.id ? 'edit-self' : 'edit-other');
  const wasAdmin = user.roles.some((role) => role.name === ADMIN_ROLE);

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) => updateUser(id, userFormToPayload(values, mode)),
    // Puede llevar la contraseña de otro usuario: fuera de la caché de mutaciones, como el alta.
    gcTime: 0,
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, {
        form,
        // Al editarse a sí mismo no está la contraseña: el 422 de `password` ("Para cambiar tu contraseña usá tu perfil.",
        // que no debería llegar porque no se manda) va a la alerta y no a un campo que no se dibuja.
        fields:
          mode === 'edit-self'
            ? USER_FORM_FIELDS.filter((field) => field !== 'password')
            : USER_FORM_FIELDS,
        setAlert,
        veterinarian: user.veterinarian,
        onGone,
        onConflict: () => {
          void queryClient.invalidateQueries({ queryKey: userKeys.detail(id) });
        },
      });
    },
    onSuccess: async (response) => {
      const lostAdmin =
        mode === 'edit-self' &&
        wasAdmin &&
        !response.data.roles.some((role) => role.name === ADMIN_ROLE);
      toast.show(response.message, 'success');

      if (lostAdmin) {
        // `me` sin el rol: el menú ya no muestra "Configuración"; la pantalla de usuarios ya no es suya.
        await afterUserChanged(queryClient, response.data);
        void navigate({ to: '/', replace: true });

        return;
      }

      void afterUserChanged(queryClient, response.data);
      void navigate({
        to: '/configuracion/usuarios/$id',
        params: { id: String(id) },
        replace: true,
        state: withUserList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
      mutation.reset();
    },
  });

  const save = (variables: SaveVariables) => {
    if (single.tryStart()) {
      mutation.mutate(variables);
    }
  };

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb user={user} />
        <h2 className="page-title">Editar el usuario</h2>
      </div>
      <UserForm
        mode={mode}
        defaultValues={userToForm(user)}
        roles={roles}
        branchOptions={branchOptions}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (mode === 'edit-self' && wasAdmin && !values.roles.includes(ADMIN_ROLE)) {
            setPendingSelfDemotion({ values, form });

            return;
          }

          save({ values, form });
        }}
        cancel={
          <Link
            to="/configuracion/usuarios/$id"
            params={{ id: String(id) }}
            state={withUserList(listSearch)}
            replace
            className="btn btn-link user-cancel"
          >
            Cancelar
          </Link>
        }
      />
      <ConfirmDialog
        open={pendingSelfDemotion !== null}
        title="Dejar de ser administrador"
        tone="primary"
        confirmLabel="Quitarme el rol de administrador"
        onConfirm={() => {
          const variables = pendingSelfDemotion;
          setPendingSelfDemotion(null);

          if (variables !== null) {
            save(variables);
          }
        }}
        onClose={() => {
          setPendingSelfDemotion(null);
        }}
      >
        Vas a dejar de ser administrador y no vas a poder volver a esta pantalla.
      </ConfirmDialog>
    </>
  );
}
