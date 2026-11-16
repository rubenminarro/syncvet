import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import { useMemo, type ReactNode } from 'react';
import { useForm, type UseFormReturn } from 'react-hook-form';
import { Alert, Button, Card, CheckboxField, TextField } from '../../components/ui';
import { branchFormSchema, CODE_MAX, type BranchFormValues } from './form';

interface BranchFormProps {
  title: string;
  defaultValues: BranchFormValues;
  /** El alta: suma el código y el nombre del depósito por defecto que crea la API. */
  creating: boolean;
  /** La sucursal ya es la por defecto: la casilla, marcada y deshabilitada (H4 del diseño). */
  wasDefault: boolean;
  /** El error de la acción que no es de un campo (la alerta de arriba). */
  alert: string | null;
  saving: boolean;
  onSubmit: (values: BranchFormValues, form: UseFormReturn<BranchFormValues>) => void;
  cancel: ReactNode;
}

/**
 * El formulario de la sucursal (2.1). Los nombres y la dirección no llevan `maxLength` (admiten letras fuera del plano
 * básico: el largo lo controla Zod con el mensaje de la API); los códigos y el establecimiento, sí. Los códigos se pasan a
 * mayúsculas al mandar.
 */
export function BranchForm({
  title,
  defaultValues,
  creating,
  wasDefault,
  alert,
  saving,
  onSubmit,
  cancel,
}: BranchFormProps) {
  const resolver = useMemo(() => zodResolver(branchFormSchema({ creating })), [creating]);
  const form = useForm<BranchFormValues>({ resolver, defaultValues });
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = form;

  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  return (
    <Card title={title}>
      {alert !== null ? <Alert title={alert} /> : null}
      <form className="branch-form" onSubmit={(event) => void submit(event)} noValidate>
        <div className="row">
          <div className="col-md-4">
            <TextField
              label="Código"
              required
              maxLength={CODE_MAX}
              autoComplete="off"
              hint="Letras sin acentos, números y guiones."
              error={errors.code?.message}
              {...register('code')}
            />
          </div>
          <div className="col-md-8">
            <TextField
              label="Nombre"
              required
              autoComplete="off"
              error={errors.name?.message}
              {...register('name')}
            />
          </div>
          <div className="col-md-4">
            <TextField
              label="Establecimiento"
              required
              maxLength={3}
              inputMode="numeric"
              autoComplete="off"
              hint="Los tres dígitos de SIFEN: 001."
              error={errors.establishment?.message}
              {...register('establishment')}
            />
          </div>
          <div className="col-md-8">
            <TextField
              label="Teléfono"
              type="tel"
              autoComplete="off"
              hint="Con el código del país y sin el 0 inicial: +595211100001."
              error={errors.phone?.message}
              {...register('phone')}
            />
          </div>
          <div className="col-12">
            <TextField
              label="Dirección"
              autoComplete="off"
              error={errors.address?.message}
              {...register('address')}
            />
          </div>
          <div className="col-12">
            {wasDefault ? (
              // La por defecto no deja de serlo desmarcándola (la API responde 422): se marca otra. No se registra en el
              // formulario (un campo deshabilitado no tiene valor); el cuerpo manda `is_default: true`.
              <CheckboxField
                label="Sucursal por defecto"
                checked
                disabled
                readOnly
                hint="Para cambiarla, marcá otra sucursal como la por defecto."
              />
            ) : (
              <CheckboxField
                label="Sucursal por defecto"
                hint="La anterior deja de serlo."
                error={errors.is_default?.message}
                {...register('is_default')}
              />
            )}
          </div>
        </div>
        {creating ? (
          <fieldset className="branch-warehouse-fields">
            <legend className="h4">Depósito por defecto</legend>
            <div className="row">
              <div className="col-md-4">
                <TextField
                  label="Código del depósito"
                  required
                  maxLength={CODE_MAX}
                  autoComplete="off"
                  error={errors.warehouse_code?.message}
                  {...register('warehouse_code')}
                />
              </div>
              <div className="col-md-8">
                <TextField
                  label="Nombre del depósito"
                  autoComplete="off"
                  placeholder="Principal"
                  hint="Vacío, se llama Principal."
                  error={errors.warehouse_name?.message}
                  {...register('warehouse_name')}
                />
              </div>
            </div>
          </fieldset>
        ) : null}
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
