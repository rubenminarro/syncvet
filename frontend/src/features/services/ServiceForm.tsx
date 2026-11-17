import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import type { ReactNode } from 'react';
import { useForm, type UseFormReturn } from 'react-hook-form';
import {
  Alert,
  Button,
  Card,
  RadioGroup,
  SelectField,
  TextareaField,
  TextField,
} from '../../components/ui';
import { serviceFormSchema, type ServiceFormValues } from './form';
import { SERVICE_CATEGORIES, TAX_RATES } from './types';

const CATEGORY_OPTIONS = [
  { value: '', label: 'Elegí la categoría' },
  ...SERVICE_CATEGORIES.map((category) => ({ value: category.value, label: category.label })),
];

const TAX_OPTIONS = TAX_RATES.map((rate) => ({ value: String(rate), label: `${String(rate)} %` }));

const resolver = zodResolver(serviceFormSchema);

interface ServiceFormProps {
  defaultValues: ServiceFormValues;
  /** En la edición, el código (solo para leer); en el alta no hay: lo pone la API. */
  code?: string;
  alert: string | null;
  saving: boolean;
  onSubmit: (values: ServiceFormValues, form: UseFormReturn<ServiceFormValues>) => void;
  cancel: ReactNode;
}

/**
 * El formulario del servicio (2.4). Sin el código (lo pone la API) ni el estado (se cambia con su botón en el detalle). El
 * nombre y la descripción no llevan `maxLength` nativo (admiten cualquier letra: el largo lo controla Zod con el mensaje de
 * la API); el precio es texto (`inputMode="numeric"`, nunca `type="number"`) y acepta los puntos de miles ("150.000").
 */
export function ServiceForm({
  defaultValues,
  code,
  alert,
  saving,
  onSubmit,
  cancel,
}: ServiceFormProps) {
  const form = useForm<ServiceFormValues>({ resolver, defaultValues });
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = form;

  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  return (
    <Card title="Datos del servicio">
      {alert !== null ? <Alert title={alert} /> : null}
      <form className="service-form" onSubmit={(event) => void submit(event)} noValidate>
        {code !== undefined ? (
          <dl className="row service-fixed">
            <dt className="col-4 col-md-2">Código</dt>
            <dd className="col-8 col-md-10">{code}</dd>
          </dl>
        ) : null}
        <div className="row">
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
            <SelectField
              label="Categoría"
              className="mb-3"
              required
              options={CATEGORY_OPTIONS}
              error={errors.category?.message}
              {...register('category')}
            />
          </div>
          <div className="col-md-4">
            <TextField
              label="Precio (Gs.)"
              required
              inputMode="numeric"
              autoComplete="off"
              hint="Sin decimales; podés escribir los puntos de miles (150.000)."
              error={errors.standard_price?.message}
              {...register('standard_price')}
            />
          </div>
          <div className="col-md-4">
            <TextField
              label="Duración (minutos)"
              required
              inputMode="numeric"
              autoComplete="off"
              hint="De 5 a 720, de a 5 minutos."
              error={errors.duration_minutes?.message}
              {...register('duration_minutes')}
            />
          </div>
          <div className="col-md-4">
            <RadioGroup
              label="IVA"
              options={TAX_OPTIONS}
              error={errors.tax_rate?.message}
              {...register('tax_rate')}
            />
          </div>
          <div className="col-12">
            <TextareaField
              label="Descripción"
              rows={3}
              error={errors.description?.message}
              {...register('description')}
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
