import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import type { ReactNode, SyntheticEvent } from 'react';
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
import { PATIENT_MESSAGES, patientFormSchema, type PatientFormValues } from './form';
import { breedOptions, speciesOptions, type CurrentCatalog } from './species-options';
import type { Species } from './types';

const SEX_OPTIONS = [
  { value: 'male', label: 'Macho' },
  { value: 'female', label: 'Hembra' },
  // El mismo texto que la API (PatientSex::label()): el listado y el detalle muestran el de ella.
  { value: 'unknown', label: 'Sin determinar' },
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
  /**
   * Un paciente fallecido (1.3b): solo las notas quedan editables (PATIENT_DECEASED); los demás campos, deshabilitados. Quien
   * manda el formulario los toma del paciente guardado, no de los campos.
   */
  locked?: boolean;
}

/**
 * El formulario de alta y edición de un paciente. Los textos con letras (nombre, color, alergias, notas) no llevan `maxLength`:
 * el navegador cuenta unidades UTF-16 (un emoji es 2) y la API, caracteres (`mb_strlen`); el largo lo controla
 * patientFormErrors, con el mensaje de la API. La raza se filtra por la especie elegida y se vacía al cambiar de especie
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
  locked = false,
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

  // Una fecha a medio escribir: el <input type="date"> da '' (validity.badInput), que sería "sin fecha" y borraría la
  // guardada sin aviso. Se muestra el error de formato de la API y no se manda (se lee del campo al enviar).
  const submit = (event: SyntheticEvent<HTMLFormElement>) => {
    const input = event.currentTarget.elements.namedItem('birth_date');
    const badDate = input instanceof HTMLInputElement && input.validity.badInput;
    const markBadDate = () => {
      if (badDate) {
        form.setError(
          'birth_date',
          { type: 'badInput', message: PATIENT_MESSAGES.birth_date.date_format },
          { shouldFocus: true },
        );
      }
    };

    void handleSubmit((values) => {
      markBadDate();

      if (!badDate) {
        onSubmit(values, form);
      }
    }, markBadDate)(event);
  };

  return (
    <Card title={title}>
      {alert !== null ? <Alert title={alert} /> : null}
      {locked ? (
        <Alert
          tone="info"
          title="El paciente figura como fallecido: solo se pueden editar las notas. La fecha de fallecimiento se corrige desde el detalle."
        />
      ) : null}
      <form className="patient-form" onSubmit={submit} noValidate>
        <div className="row">
          <div className="col-md-6">
            <TextField
              label="Nombre"
              required
              autoComplete="off"
              error={errors.name?.message}
              disabled={locked}
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
              disabled={locked}
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
              disabled={locked}
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
              disabled={locked}
              {...register('breed_id')}
            />
          </div>
          <div className="col-md-6">
            <RadioGroup
              label="Sexo"
              options={SEX_OPTIONS}
              error={errors.sex?.message}
              disabled={locked}
              {...register('sex')}
            />
          </div>
          <div className="col-md-6">
            <CheckboxField
              label="Castrado"
              error={errors.is_neutered?.message}
              disabled={locked}
              {...register('is_neutered')}
            />
          </div>
          <div className="col-md-6">
            <DateField
              label="Fecha de nacimiento"
              max={todayInAppZone()}
              error={errors.birth_date?.message}
              disabled={locked}
              {...register('birth_date')}
            />
            <CheckboxField
              label="Es aproximada"
              error={errors.birth_date_estimated?.message}
              disabled={locked}
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
              disabled={locked}
              {...register('weight_kg')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Color"
              autoComplete="off"
              error={errors.color?.message}
              disabled={locked}
              {...register('color')}
            />
          </div>
          <div className="col-12">
            <TextareaField
              label="Alergias"
              rows={2}
              error={errors.allergies?.message}
              disabled={locked}
              {...register('allergies')}
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
