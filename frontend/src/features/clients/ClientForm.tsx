import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import type { ReactNode } from 'react';
import { useForm, useWatch, type UseFormReturn } from 'react-hook-form';
import {
  Alert,
  Button,
  Card,
  CheckboxField,
  RadioGroup,
  TextareaField,
  TextField,
} from '../../components/ui';
import { clientFormSchema, type ClientFormValues } from './form';

const KIND_OPTIONS = [
  { value: 'person', label: 'Persona' },
  { value: 'company', label: 'Empresa' },
] as const;

interface ClientFormProps {
  title: string;
  defaultValues: ClientFormValues;
  /** El error de la acción que no es de un campo (la alerta de arriba). */
  alert: string | null;
  saving: boolean;
  onSubmit: (values: ClientFormValues, form: UseFormReturn<ClientFormValues>) => void;
  /** El enlace "Cancelar": vuelve sin preguntar (pregunta 11 del diseño). */
  cancel: ReactNode;
}

/**
 * El formulario de alta y edición (persona o empresa). Al cambiar de tipo, los campos del otro se ocultan (sus valores
 * quedan en el formulario, por si se vuelve, pero se mandan en null: clientFormToPayload).
 */
export function ClientForm({
  title,
  defaultValues,
  alert,
  saving,
  onSubmit,
  cancel,
}: ClientFormProps) {
  const form = useForm<ClientFormValues>({
    resolver: zodResolver(clientFormSchema),
    defaultValues,
  });
  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = form;
  const isCompany = useWatch({ control, name: 'kind' }) === 'company';

  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  return (
    <Card title={title}>
      {alert !== null ? <Alert title={alert} /> : null}
      <form className="client-form" onSubmit={(event) => void submit(event)} noValidate>
        <RadioGroup
          label="Tipo de cliente"
          options={KIND_OPTIONS}
          error={errors.kind?.message}
          {...register('kind')}
        />
        <div className="row">
          {isCompany ? (
            <div className="col-12">
              <TextField
                label="Razón social"
                required
                maxLength={150}
                autoComplete="organization"
                error={errors.business_name?.message}
                {...register('business_name')}
              />
            </div>
          ) : (
            <>
              <div className="col-md-6">
                <TextField
                  label="Nombre"
                  required
                  maxLength={100}
                  autoComplete="given-name"
                  error={errors.first_name?.message}
                  {...register('first_name')}
                />
              </div>
              <div className="col-md-6">
                <TextField
                  label="Apellido"
                  required
                  maxLength={100}
                  autoComplete="family-name"
                  error={errors.last_name?.message}
                  {...register('last_name')}
                />
              </div>
            </>
          )}
          <div className="col-md-6">
            <TextField
              label={isCompany ? 'RUC' : 'Documento'}
              required
              maxLength={20}
              autoComplete="off"
              hint="Cédula sin puntos, o RUC con el guion y el dígito verificador."
              error={errors.document_number?.message}
              {...register('document_number')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Email"
              type="email"
              required
              maxLength={50}
              autoComplete="email"
              error={errors.email?.message}
              {...register('email')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Teléfono"
              type="tel"
              required
              autoComplete="tel"
              hint="Con el código del país y sin el 0 inicial: +595981123456."
              error={errors.phone?.message}
              {...register('phone')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Ciudad"
              maxLength={100}
              autoComplete="address-level2"
              error={errors.city?.message}
              {...register('city')}
            />
          </div>
          <div className="col-12">
            <TextField
              label="Dirección"
              maxLength={255}
              autoComplete="street-address"
              error={errors.address?.message}
              {...register('address')}
            />
          </div>
          <div className="col-12">
            <CheckboxField
              label="Recibe recordatorios"
              hint="Los avisos de vacunas y turnos de sus mascotas."
              error={errors.notifications_enabled?.message}
              {...register('notifications_enabled')}
            />
          </div>
          <div className="col-12">
            <TextareaField
              label="Notas"
              rows={3}
              maxLength={1000}
              error={errors.notes?.message}
              {...register('notes')}
            />
          </div>
        </div>
        <div className="d-flex gap-2 align-items-center">
          <Button
            type="submit"
            // También mientras valida: el segundo clic de un doble clic llega antes de que la mutación esté pendiente.
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
