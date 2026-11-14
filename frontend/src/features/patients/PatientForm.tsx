import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import type { ReactNode } from 'react';
import { useForm, useWatch, type UseFormReturn } from 'react-hook-form';
import {
  Alert,
  Button,
  Card,
  CheckboxField,
  DateField,
  RadioGroup,
  SelectField,
  TextareaField,
  TextField,
} from '../../components/ui';
import { todayInAppZone } from '../../lib/format/dates';
import { patientFormSchema, type PatientFormValues } from './form';
import { breedOptions, speciesOptions, type CurrentCatalog } from './species-options';
import type { Species } from './types';

const SEX_OPTIONS = [
  { value: 'male', label: 'Macho' },
  { value: 'female', label: 'Hembra' },
  { value: 'unknown', label: 'Sin dato' },
] as const;

interface PatientFormProps {
  title: string;
  defaultValues: PatientFormValues;
  /** Las especies activas con sus razas activas (GET species). */
  species: readonly Species[];
  /** En la edición: la especie y la raza que el paciente ya tiene, que se ofrecen aunque estén inactivas. */
  current?: CurrentCatalog;
  /** El error de la acción que no es de un campo (la alerta de arriba). */
  alert: string | null;
  saving: boolean;
  onSubmit: (values: PatientFormValues, form: UseFormReturn<PatientFormValues>) => void;
  /** El enlace "Cancelar": vuelve sin preguntar (pregunta 11 del diseño). */
  cancel: ReactNode;
}

/**
 * El formulario de alta y edición de un paciente. La raza se filtra por la especie elegida y se vacía al cambiar de especie
 * ("Sin raza" es null). La fecha de nacimiento es el texto `yyyy-MM-dd` del selector nativo, sin hora.
 */
export function PatientForm({
  title,
  defaultValues,
  species,
  current,
  alert,
  saving,
  onSubmit,
  cancel,
}: PatientFormProps) {
  const form = useForm<PatientFormValues>({
    resolver: zodResolver(patientFormSchema),
    defaultValues,
  });
  const {
    register,
    handleSubmit,
    control,
    setValue,
    formState: { errors, isSubmitting },
  } = form;
  const speciesId = useWatch({ control, name: 'species_id' });

  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  return (
    <Card title={title}>
      {alert !== null ? <Alert title={alert} /> : null}
      <form className="patient-form" onSubmit={(event) => void submit(event)} noValidate>
        <div className="row">
          <div className="col-md-6">
            <TextField
              label="Nombre"
              required
              maxLength={100}
              autoComplete="off"
              error={errors.name?.message}
              {...register('name')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Microchip"
              maxLength={30}
              autoComplete="off"
              hint="Solo letras y números, sin espacios."
              error={errors.microchip?.message}
              {...register('microchip')}
            />
          </div>
          <div className="col-md-6">
            <SelectField
              className="mb-3"
              label="Especie"
              required
              options={speciesOptions(species, current)}
              error={errors.species_id?.message}
              {...register('species_id', {
                // Otra especie: la raza elegida ya no corresponde.
                onChange: () => {
                  setValue('breed_id', '');
                },
              })}
            />
          </div>
          <div className="col-md-6">
            <SelectField
              className="mb-3"
              label="Raza"
              options={breedOptions(species, speciesId, current)}
              error={errors.breed_id?.message}
              {...register('breed_id')}
            />
          </div>
          <div className="col-md-6">
            <RadioGroup
              label="Sexo"
              options={SEX_OPTIONS}
              error={errors.sex?.message}
              {...register('sex')}
            />
          </div>
          <div className="col-md-6">
            <CheckboxField
              label="Castrado"
              error={errors.is_neutered?.message}
              {...register('is_neutered')}
            />
          </div>
          <div className="col-md-6">
            <DateField
              label="Fecha de nacimiento"
              max={todayInAppZone()}
              error={errors.birth_date?.message}
              {...register('birth_date')}
            />
            <CheckboxField
              label="Es aproximada"
              error={errors.birth_date_estimated?.message}
              {...register('birth_date_estimated')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Peso (kg)"
              inputMode="decimal"
              autoComplete="off"
              hint="Con coma decimal: 12,5."
              error={errors.weight_kg?.message}
              {...register('weight_kg')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Color"
              maxLength={80}
              autoComplete="off"
              error={errors.color?.message}
              {...register('color')}
            />
          </div>
          <div className="col-12">
            <TextareaField
              label="Alergias"
              rows={2}
              maxLength={1000}
              error={errors.allergies?.message}
              {...register('allergies')}
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
