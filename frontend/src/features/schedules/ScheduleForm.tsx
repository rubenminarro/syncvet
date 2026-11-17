import { zodResolver } from '@hookform/resolvers/zod';
import { IconCopy, IconDeviceFloppy, IconPlus, IconTrash } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useRef, type ReactNode } from 'react';
import { useFieldArray, useForm, useWatch, type UseFormReturn } from 'react-hook-form';
import {
  Alert,
  Button,
  Card,
  CheckboxField,
  SelectField,
  TextField,
  TimeField,
  type SelectOption,
} from '../../components/ui';
import { branchOptionsQueryOptions } from '../branches/api';
import { serviceOptionsQueryOptions, veterinarianOptionsQueryOptions } from './api';
import {
  copyMondayToWeekdays,
  hoursOfDay,
  MAX_HOURS_PER_DAY,
  scheduleFormSchema,
  type ScheduleFormValues,
} from './form';
import { WEEKDAYS, type ScheduleDetail } from './types';

const resolver = zodResolver(scheduleFormSchema);

interface ScheduleFormProps {
  defaultValues: ScheduleFormValues;
  /** En la edición, la agenda guardada: su sucursal, su veterinario y su servicio siguen en las listas aunque no estén activos. */
  current?: ScheduleDetail;
  alert: string | null;
  saving: boolean;
  onSubmit: (values: ScheduleFormValues, form: UseFormReturn<ScheduleFormValues>) => void;
  cancel: ReactNode;
}

/**
 * El formulario de la agenda (3.1a): los datos y el horario semanal. El horario va por día, de lunes a domingo, con sus
 * tramos "de … a …" (de a 5 minutos), "Sumar un tramo" (hasta 6 por día), "Quitar" y "Copiar el lunes a los días hábiles".
 * El veterinario se elige entre los activos de la sucursal elegida (con el usuario activo); cambiar la sucursal suelta el
 * veterinario que no trabaja en ella.
 */
export function ScheduleForm({
  defaultValues,
  current,
  alert,
  saving,
  onSubmit,
  cancel,
}: ScheduleFormProps) {
  const form = useForm<ScheduleFormValues>({ resolver, defaultValues });
  const {
    register,
    control,
    handleSubmit,
    getValues,
    setValue,
    setFocus,
    formState: { errors, isSubmitting },
  } = form;
  const hours = useFieldArray({ control, name: 'hours' });
  const branchId = useWatch({ control, name: 'branch_id' });
  const useColor = useWatch({ control, name: 'use_color' });
  const addButtons = useRef(new Map<number, HTMLButtonElement>());
  // El campo que recibe el foco cuando se dibuje el tramo recién sumado ("Desde").
  const pendingFocus = useRef<`hours.${number}.starts_at` | null>(null);

  useEffect(() => {
    if (pendingFocus.current !== null) {
      setFocus(pendingFocus.current);
      pendingFocus.current = null;
    }
  }, [hours.fields.length, setFocus]);

  const branches = useQuery(branchOptionsQueryOptions());
  const veterinarians = useQuery(veterinarianOptionsQueryOptions());
  const services = useQuery(serviceOptionsQueryOptions());

  const vetsOfBranch = (branch: string) =>
    (veterinarians.data ?? []).filter(
      (vet) =>
        vet.status.value === 'active' &&
        vet.user.is_active &&
        vet.branches.some((item) => String(item.id) === branch),
    );

  const branchOptions: SelectOption[] = [
    { value: '', label: 'Elegí la sucursal' },
    ...(branches.data ?? [])
      .filter((branch) => branch.is_active || branch.id === current?.branch.id)
      .map((branch) => ({
        value: String(branch.id),
        label: branch.is_active ? branch.name : `${branch.name} (inactivada)`,
      })),
  ];

  if (current !== undefined && !branchOptions.some((o) => o.value === String(current.branch.id))) {
    branchOptions.push({ value: String(current.branch.id), label: current.branch.name });
  }

  const veterinarianOptions: SelectOption[] = [
    { value: '', label: 'Sin veterinario' },
    ...vetsOfBranch(branchId).map((vet) => ({ value: String(vet.id), label: vet.user.name })),
  ];
  const currentVet = current?.veterinarian;

  if (
    currentVet != null &&
    String(current?.branch.id) === branchId &&
    !veterinarianOptions.some((o) => o.value === String(currentVet.id))
  ) {
    veterinarianOptions.push({
      value: String(currentVet.id),
      // "(actual)": ya no está entre los activos de la sucursal (mientras la lista carga, solo el nombre).
      label: veterinarians.data === undefined ? currentVet.name : `${currentVet.name} (actual)`,
    });
  }

  const serviceOptions: SelectOption[] = [
    { value: '', label: 'Sin servicio' },
    ...(services.data ?? []).map((service) => ({ value: String(service.id), label: service.name })),
  ];
  const currentService = current?.service;

  if (
    currentService != null &&
    !serviceOptions.some((o) => o.value === String(currentService.id))
  ) {
    serviceOptions.push({
      value: String(currentService.id),
      label: services.data === undefined ? currentService.name : `${currentService.name} (actual)`,
    });
  }

  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  // Por día, desde `fields` (el día de un tramo no cambia): se dibujan en el mismo render que el append, así el foco encuentra
  // el campo nuevo.
  const mondayCount = hours.fields.filter((hour) => hour.weekday === 1).length;

  return (
    <form className="schedule-form" onSubmit={(event) => void submit(event)} noValidate>
      <Card title="Datos de la agenda">
        {alert !== null ? <Alert title={alert} /> : null}
        <div className="row">
          <div className="col-md-6">
            <SelectField
              label="Sucursal"
              className="mb-3"
              required
              options={branchOptions}
              error={errors.branch_id?.message}
              {...register('branch_id', {
                onChange: (event: { target: { value: string } }) => {
                  const vet = getValues('veterinarian_id');
                  const keep =
                    vet === '' ||
                    vetsOfBranch(event.target.value).some((item) => String(item.id) === vet) ||
                    (String(current?.branch.id) === event.target.value &&
                      String(currentVet?.id) === vet);

                  if (!keep) {
                    setValue('veterinarian_id', '');
                  }
                },
              })}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Nombre"
              required
              autoComplete="off"
              hint="Por ejemplo, Consultorio 1."
              error={errors.name?.message}
              {...register('name')}
            />
          </div>
          <div className="col-md-6">
            <SelectField
              label="Veterinario"
              className="mb-3"
              options={veterinarianOptions}
              hint={branchId === '' ? 'Elegí primero la sucursal.' : 'Los activos de la sucursal.'}
              error={errors.veterinarian_id?.message}
              {...register('veterinarian_id')}
            />
          </div>
          <div className="col-md-6">
            <SelectField
              label="Servicio"
              className="mb-3"
              options={serviceOptions}
              hint="Elegí un veterinario, un servicio o los dos."
              error={errors.service_id?.message}
              {...register('service_id')}
            />
          </div>
          <div className="col-md-6">
            <TextField
              label="Duración del turno (minutos)"
              required
              inputMode="numeric"
              autoComplete="off"
              hint="De 5 a 240, de a 5 minutos."
              error={errors.slot_minutes?.message}
              {...register('slot_minutes')}
            />
          </div>
          <div className="col-md-6">
            <CheckboxField label="Con color en la agenda" {...register('use_color')} />
            <TextField
              label="Color"
              type="color"
              className="form-control-color schedule-color-input"
              disabled={!useColor}
              error={errors.color?.message}
              {...register('color')}
            />
          </div>
        </div>
      </Card>
      <div className="mt-3">
        <Card title="Horario semanal">
          <p className="text-secondary">
            Los tramos de cada día en que la agenda recibe turnos. Un día sin tramos no tiene
            atención.
            {current !== undefined
              ? ' El horario nuevo no cambia los turnos que ya están dados.'
              : null}
          </p>
          <div className="mb-3">
            <Button
              variant="secondary"
              className="schedule-copy-monday"
              disabled={mondayCount === 0}
              icon={<IconCopy size={18} className="me-2" aria-hidden="true" />}
              onClick={() => {
                hours.replace(copyMondayToWeekdays(getValues('hours')));
              }}
            >
              Copiar el lunes a los días hábiles
            </Button>
          </div>
          {WEEKDAYS.map((day) => {
            const entries = hoursOfDay(hours.fields, day.value);

            return (
              <fieldset
                key={day.value}
                className={`border-top pt-3 mb-2 schedule-day schedule-day-${String(day.value)}`}
              >
                <legend className="h4 mb-2 float-none w-auto">{day.label}</legend>
                {entries.length === 0 ? <p className="text-secondary mb-2">Sin atención.</p> : null}
                {entries.map(({ index }, position) => {
                  const field = hours.fields[index];
                  const error = errors.hours?.[index];

                  return (
                    <div
                      key={field?.id ?? index}
                      role="group"
                      aria-label={`${day.label}, tramo ${String(position + 1)}`}
                      className="row g-2 align-items-start schedule-hour"
                    >
                      <div className="col-5 col-md-3">
                        <TimeField
                          label="Desde"
                          step={300}
                          className="hour-start"
                          error={error?.starts_at?.message}
                          {...register(`hours.${index}.starts_at`)}
                        />
                      </div>
                      <div className="col-5 col-md-3">
                        <TimeField
                          label="Hasta"
                          step={300}
                          className="hour-end"
                          error={error?.ends_at?.message}
                          {...register(`hours.${index}.ends_at`)}
                        />
                      </div>
                      <div className="col-2 col-md-auto pt-4">
                        <Button
                          variant="secondary"
                          className="btn-icon mt-1 schedule-hour-remove"
                          aria-label={`Quitar el tramo ${String(position + 1)} del ${day.lower}`}
                          title="Quitar"
                          onClick={() => {
                            hours.remove(index);
                            addButtons.current.get(day.value)?.focus();
                          }}
                        >
                          <IconTrash size={18} aria-hidden="true" />
                        </Button>
                      </div>
                    </div>
                  );
                })}
                <Button
                  variant="link"
                  className="px-0 schedule-hour-add"
                  ref={(element: HTMLButtonElement | null) => {
                    if (element === null) {
                      addButtons.current.delete(day.value);
                    } else {
                      addButtons.current.set(day.value, element);
                    }
                  }}
                  disabled={entries.length >= MAX_HOURS_PER_DAY}
                  focusableWhenInactive
                  aria-label={`Sumar un tramo al ${day.lower}`}
                  icon={<IconPlus size={18} className="me-1" aria-hidden="true" />}
                  onClick={() => {
                    // El foco, en "Desde" del tramo nuevo (el último de la lista plana), cuando se dibuje.
                    pendingFocus.current = `hours.${hours.fields.length}.starts_at` as const;
                    hours.append(
                      { weekday: day.value, starts_at: '', ends_at: '' },
                      { shouldFocus: false },
                    );
                  }}
                >
                  Sumar un tramo
                </Button>
                {entries.length >= MAX_HOURS_PER_DAY ? (
                  <small className="form-hint d-block">
                    Hasta {MAX_HOURS_PER_DAY} tramos por día.
                  </small>
                ) : null}
              </fieldset>
            );
          })}
        </Card>
      </div>
      <div className="d-flex gap-2 align-items-center mt-3">
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
  );
}
