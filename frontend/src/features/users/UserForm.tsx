import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import { Link } from '@tanstack/react-router';
import { useMemo, type ReactNode } from 'react';
import { Controller, useForm, useWatch, type UseFormReturn } from 'react-hook-form';
import {
  Alert,
  Button,
  Card,
  CheckboxField,
  CheckboxGroupField,
  PasswordField,
  TextField,
  type CheckboxOption,
} from '../../components/ui';
import { EMAIL_MAX, userFormSchema, type UserFormMode, type UserFormValues } from './form';
import type { Role, RoleName } from './types';

/** La pista de la contraseña: las reglas de `Password::defaults()` (la de filtraciones la controla la API). */
const PASSWORD_HINT = 'Al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos.';

interface UserFormProps {
  mode: UserFormMode;
  defaultValues: UserFormValues;
  /** GET roles: las casillas de los roles, con su etiqueta. */
  roles: readonly Role[];
  /** Las sucursales que se pueden elegir: las activas y, al editar, también las inactivas que el usuario ya tiene. */
  branchOptions: readonly CheckboxOption<number>[];
  /** El error de la acción que no es de un campo (la alerta de arriba), con su detalle si lo tiene. */
  alert: { title: string; detail?: ReactNode } | null;
  saving: boolean;
  onSubmit: (values: UserFormValues, form: UseFormReturn<UserFormValues>) => void;
  cancel: ReactNode;
}

/**
 * El formulario del usuario (2.2a). Los nombres no llevan `maxLength` (admiten letras con acento: el largo lo controla Zod
 * con el mensaje de la API); el email, sí (solo ASCII). La contraseña depende de `mode`: en el alta, las dos obligatorias;
 * al editar a otro, detrás de "Cambiar la contraseña" (pregunta 6 del diseño); al editarse a sí mismo, no está (se cambia en
 * /perfil, H3).
 */
export function UserForm({
  mode,
  defaultValues,
  roles,
  branchOptions,
  alert,
  saving,
  onSubmit,
  cancel,
}: UserFormProps) {
  const resolver = useMemo(() => zodResolver(userFormSchema(mode)), [mode]);
  const form = useForm<UserFormValues>({ resolver, defaultValues });
  const {
    register,
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = form;
  const changePassword = useWatch({ control, name: 'change_password' });

  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  const roleOptions = roles.map((role) => ({ value: role.name, label: role.label }));
  const showPassword = mode === 'create' || (mode === 'edit-other' && changePassword);

  return (
    <Card title="Datos del usuario">
      {alert !== null ? <Alert title={alert.title}>{alert.detail}</Alert> : null}
      <form className="user-form" onSubmit={(event) => void submit(event)} noValidate>
        <div className="row">
          <div className="col-md-6">
            <TextField
              label="Nombre"
              required
              autoComplete="off"
              error={errors.first_name?.message}
              {...register('first_name')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Apellido"
              required
              autoComplete="off"
              error={errors.last_name?.message}
              {...register('last_name')}
            />
          </div>
          <div className="col-12">
            <TextField
              label="Email"
              type="email"
              required
              maxLength={EMAIL_MAX}
              autoComplete="off"
              hint="Es el usuario para ingresar."
              error={errors.email?.message}
              {...register('email')}
            />
          </div>
          <div className="col-md-4">
            <Controller
              control={control}
              name="roles"
              render={({ field }) => (
                <CheckboxGroupField<RoleName>
                  label="Roles"
                  required
                  className="user-roles"
                  options={roleOptions}
                  value={field.value}
                  onChange={field.onChange}
                  onBlur={field.onBlur}
                  ref={field.ref}
                  error={errors.roles?.message}
                />
              )}
            />
          </div>
          <div className="col-md-8">
            <Controller
              control={control}
              name="branch_ids"
              render={({ field }) => (
                <CheckboxGroupField<number>
                  label="Sucursales"
                  required
                  className="user-branches-field"
                  options={branchOptions}
                  value={field.value}
                  onChange={field.onChange}
                  onBlur={field.onBlur}
                  ref={field.ref}
                  error={errors.branch_ids?.message}
                />
              )}
            />
          </div>
        </div>
        <fieldset className="user-password-fields">
          <legend className="h4">Contraseña</legend>
          {mode === 'edit-self' ? (
            <p className="text-secondary user-own-password">
              Tu contraseña se cambia en tu <Link to="/perfil">perfil</Link>.
            </p>
          ) : null}
          {mode === 'edit-other' ? (
            <CheckboxField label="Cambiar la contraseña" {...register('change_password')} />
          ) : null}
          {mode === 'edit-other' && changePassword ? (
            <Alert tone="warning" title="Se van a cerrar todas sus sesiones." />
          ) : null}
          {showPassword ? (
            <div className="row">
              <div className="col-md-6">
                <PasswordField
                  label={mode === 'create' ? 'Contraseña' : 'Contraseña nueva'}
                  required
                  autoComplete="new-password"
                  hint={PASSWORD_HINT}
                  error={errors.password?.message}
                  {...register('password')}
                />
              </div>
              <div className="col-md-6">
                <PasswordField
                  label="Repetí la contraseña"
                  required
                  autoComplete="new-password"
                  error={errors.password_confirmation?.message}
                  {...register('password_confirmation')}
                />
              </div>
            </div>
          ) : null}
        </fieldset>
        <div className="d-flex gap-2 align-items-center">
          <Button
            type="submit"
            loading={saving || isSubmitting}
            icon={<IconDeviceFloppy size={18} className="me-2" aria-hidden="true" />}
          >
            Guardar
          </Button>
          {cancel}
        </div>
      </form>
    </Card>
  );
}
