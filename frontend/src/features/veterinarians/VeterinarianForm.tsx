import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import { useMemo, type ReactNode } from 'react';
import { useForm, type UseFormReturn } from 'react-hook-form';
import {
  Alert,
  Button,
  Card,
  SelectField,
  TextareaField,
  TextField,
  type SelectOption,
} from '../../components/ui';
import { veterinarianFormSchema, type VeterinarianFormValues } from './form';

interface VeterinarianFormProps {
  defaultValues: VeterinarianFormValues;
  /**
   * En el alta, los usuarios que se pueden elegir (activos, con el rol veterinario y sin ficha); en la edición, null: el
   * usuario y el código se muestran en `readOnly`, solo para leer.
   */
  userOptions: readonly SelectOption[] | null;
  readOnly?: ReactNode;
  alert: string | null;
  saving: boolean;
  onSubmit: (values: VeterinarianFormValues, form: UseFormReturn<VeterinarianFormValues>) => void;
  cancel: ReactNode;
}

/**
 * El formulario de la ficha de veterinario (2.3). Sin el código (lo pone la API) ni el estado (se cambia con su botón en
 * el detalle). Los textos no llevan `maxLength` nativo (admiten cualquier letra: el largo lo controla Zod con el mensaje
 * de la API); la comisión se escribe con coma o punto.
 */
export function VeterinarianForm({
  defaultValues,
  userOptions,
  readOnly,
  alert,
  saving,
  onSubmit,
  cancel,
}: VeterinarianFormProps) {
  const withUser = userOptions !== null;
  const resolver = useMemo(() => zodResolver(veterinarianFormSchema(withUser)), [withUser]);
  const form = useForm<VeterinarianFormValues>({ resolver, defaultValues });
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = form;

  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  return (
    <Card title="Datos de la ficha">
      {alert !== null ? <Alert title={alert} /> : null}
      <form className="veterinarian-form" onSubmit={(event) => void submit(event)} noValidate>
        <div className="row">
          {userOptions !== null ? (
            <div className="col-12">
              <SelectField
                label="Usuario"
                className="mb-3"
                required
                options={[{ value: '', label: 'Elegí el usuario' }, ...userOptions]}
                hint="Un usuario activo con el rol veterinario y sin ficha."
                error={errors.user_id?.message}
                {...register('user_id')}
              />
            </div>
          ) : (
            <div className="col-12">{readOnly}</div>
          )}
          <div className="col-md-6">
            <TextField
              label="Matrícula"
              autoComplete="off"
              hint="Se guarda en mayúsculas."
              error={errors.license_number?.message}
              {...register('license_number')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Especialidad"
              autoComplete="off"
              error={errors.specialty?.message}
              {...register('specialty')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Comisión (%)"
              inputMode="decimal"
              autoComplete="off"
              hint="De 0 a 100, con hasta 2 decimales (12,5). Vacía es 0."
              error={errors.commission_percentage?.message}
              {...register('commission_percentage')}
            />
          </div>
          <div className="col-12">
            <TextareaField
              label="Notas"
              rows={3}
              error={errors.notes?.message}
              {...register('notes')}
            />
          </div>
        </div>
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
