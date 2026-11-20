import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { useCallback, useEffect, useId, useMemo, useRef, useState, type ReactNode } from 'react';
import { Controller, useForm, useWatch, type UseFormReturn } from 'react-hook-form';
import {
  Alert,
  Button,
  Card,
  CheckboxField,
  DateField,
  RadioGroup,
  SearchField,
  SelectField,
  TextareaField,
  TextField,
  TimeField,
  type SelectOption,
} from '../../components/ui';
import { isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { formatTime, todayInAppZone } from '../../lib/format/dates';
import { SEARCH_DEBOUNCE_MS } from '../../lib/hooks/use-search-text';
import { useDebouncedValue } from '../../lib/hooks/use-debounced-value';
import { normalizeSearchText } from '../../lib/search-text';
import { meQueryOptions } from '../auth/api';
import { clientOptionsQueryOptions } from '../clients/api';
import { serviceOptionsQueryOptions } from '../schedules/api';
import {
  availabilityQueryOptions,
  branchScheduleOptionsQueryOptions,
  patientOptionsQueryOptions,
} from './api';
import { scheduleOptionLabel } from './agenda-shared';
import { dayTitle } from './dates';
import {
  appointmentFormSchema,
  defaultDuration,
  GRID_MINUTES,
  MAX_MINUTES,
  seriesInputOf,
  SOURCE_OPTIONS,
  WHO_OPTIONS,
  type AppointmentFormValues,
  type ClientChoice,
  type PatientChoice,
} from './form';
import {
  SERIES_MAX_COUNT,
  SERIES_MAX_EVERY_WEEKS,
  SERIES_MIN_COUNT,
  seriesOccurrences,
  seriesSummary,
} from './series';
import { appointmentTimes, instantToWall } from './times';
import type { AppointmentDetail } from './types';

/** El valor del radio "Otra hora" (nunca una hora `HH:mm`). */
const OTHER_TIME = 'otra';

interface AppointmentFormProps {
  defaultValues: AppointmentFormValues;
  /** El turno que se edita (la edición y "Reprogramar"): su agenda se ofrece aunque ya no esté entre las activas. */
  current?: AppointmentDetail;
  /** "Reprogramar": el foco entra en la fecha del horario. */
  focusTime?: boolean;
  /** Lo de arriba del formulario: la alerta de la API, con el sobreturno si corresponde. */
  alert: ReactNode;
  saving: boolean;
  submitLabel: string;
  onSubmit: (values: AppointmentFormValues, form: UseFormReturn<AppointmentFormValues>) => void;
  cancel: ReactNode;
}

/**
 * El formulario del turno (3.4; diseño de la F3, preguntas 6 a 8 y 10): quién (paciente, cliente sin paciente o primera vez
 * con un contacto, sin alta rápida), la agenda de la sucursal actual, el servicio (el de la agenda por defecto) y la
 * duración (la del servicio o `slot_minutes`), la fecha con los horarios libres de la disponibilidad como radios y "Otra
 * hora" (la API decide), el origen, el motivo y las notas. Lo que la API rechaza (el horario semanal, los bloqueos, la
 * superposición) llega a la hora; el formulario nunca se vacía con un error.
 */
export function AppointmentForm({
  defaultValues,
  current,
  focusTime = false,
  alert,
  saving,
  submitLabel,
  onSubmit,
  cancel,
}: AppointmentFormProps) {
  const today = todayInAppZone();
  // En la edición, el turno como se cargó: uno que no se mueve no se rechaza por su día (la API tampoco).
  const original = current === undefined ? undefined : defaultValues;
  const resolver = useMemo(
    () => zodResolver(appointmentFormSchema(today, original)),
    [today, original],
  );
  const form = useForm<AppointmentFormValues>({ resolver, defaultValues });
  const {
    control,
    register,
    handleSubmit,
    setValue,
    setFocus,
    formState: { errors },
  } = form;
  const [who, scheduleValue, serviceValue, durationValue, date, time] = useWatch({
    control,
    name: ['who', 'schedule_id', 'service_id', 'duration_minutes', 'date', 'time'],
  });

  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branchId = me?.current_branch?.id ?? null;
  const schedulesQuery = useQuery({
    ...branchScheduleOptionsQueryOptions(branchId ?? 0),
    enabled: branchId !== null,
  });
  const servicesQuery = useQuery(serviceOptionsQueryOptions());
  const schedules = schedulesQuery.data ?? [];
  const services = servicesQuery.data ?? [];

  useEffect(() => {
    if (focusTime) {
      setFocus('date');
    }
  }, [focusTime, setFocus]);

  // La agenda del turno que se edita se ofrece aunque no esté entre las activas de la sucursal actual (otra sucursal o
  // inactivada): la API no lo mueve si no cambia (pregunta 7 del backend).
  const scheduleOptions: SelectOption[] = [{ value: '', label: 'Elegí la agenda' }];

  for (const schedule of schedules) {
    scheduleOptions.push({ value: String(schedule.id), label: scheduleOptionLabel(schedule) });
  }

  if (current !== undefined && !schedules.some((item) => item.id === current.schedule.id)) {
    scheduleOptions.push({
      value: String(current.schedule.id),
      // La marca, solo cuando llegaron las agendas activas (mientras tanto, el nombre solo).
      label:
        schedulesQuery.data === undefined
          ? current.schedule.name
          : `${current.schedule.name} (no está entre las activas de tu sucursal)`,
    });
  }

  const serviceOptions: SelectOption[] = [{ value: '', label: 'Sin servicio' }];

  for (const service of services) {
    serviceOptions.push({ value: String(service.id), label: service.name });
  }

  if (
    current?.service !== null &&
    current?.service !== undefined &&
    !services.some((item) => item.id === current.service?.id)
  ) {
    serviceOptions.push({ value: String(current.service.id), label: current.service.name });
  }

  const durationOf = (scheduleId: string, serviceId: string) =>
    defaultDuration(
      services.find((item) => String(item.id) === serviceId)?.duration_minutes,
      schedules.find((item) => String(item.id) === scheduleId)?.slot_minutes,
    );

  const scheduleField = register('schedule_id', {
    onChange: (event: { target: { value: string } }) => {
      // Otra agenda: su servicio por defecto y su duración (si el usuario no eligió otro servicio antes).
      const schedule = schedules.find((item) => String(item.id) === event.target.value);
      const serviceId =
        schedule?.service === null || schedule?.service === undefined
          ? serviceValue
          : String(schedule.service.id);

      setValue('service_id', serviceId);
      setValue('duration_minutes', durationOf(event.target.value, serviceId));
    },
  });
  const serviceField = register('service_id', {
    onChange: (event: { target: { value: string } }) => {
      setValue('duration_minutes', durationOf(scheduleValue, event.target.value));
    },
  });

  const duration = Number(durationValue);
  const times = appointmentTimes(date, time, duration);
  const submit = handleSubmit((values) => {
    onSubmit(values, form);
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="appointment-form">
      {alert}
      {current?.series_id !== null && current?.series_id !== undefined ? (
        <div className="mb-3 appointment-series-single">
          <Alert tone="info">{SERIES_EDIT_NOTICE}</Alert>
        </div>
      ) : null}
      <div className="row row-cards">
        <div className="col-lg-6">
          <Card title="Quién">
            <RadioGroup label="El turno es de" options={WHO_OPTIONS} {...register('who')} />
            {who === 'patient' ? (
              <Controller
                control={control}
                name="patient"
                render={({ field, fieldState }) => (
                  <PatientPicker
                    value={field.value}
                    onChange={field.onChange}
                    error={fieldState.error?.message}
                    inputRef={field.ref}
                  />
                )}
              />
            ) : null}
            {who === 'client' ? (
              <Controller
                control={control}
                name="client"
                render={({ field, fieldState }) => (
                  <ClientPicker
                    value={field.value}
                    onChange={field.onChange}
                    error={fieldState.error?.message}
                    inputRef={field.ref}
                  />
                )}
              />
            ) : null}
            {who === 'contact' ? (
              <div className="row">
                <div className="col-md-7">
                  <TextField
                    label="Nombre de contacto"
                    required
                    maxLength={150}
                    autoComplete="off"
                    error={errors.contact_name?.message}
                    {...register('contact_name')}
                  />
                </div>
                <div className="col-md-5">
                  <TextField
                    label="Teléfono de contacto"
                    required
                    inputMode="tel"
                    autoComplete="off"
                    hint="Con 8 a 15 dígitos, sin el 0 inicial."
                    error={errors.contact_phone?.message}
                    {...register('contact_phone')}
                  />
                </div>
              </div>
            ) : null}
          </Card>
        </div>
        <div className="col-lg-6">
          <Card title="Agenda y servicio">
            <SelectField
              className="mb-3"
              label="Agenda"
              required
              options={scheduleOptions}
              error={errors.schedule_id?.message}
              hint={
                branchId === null
                  ? 'Elegí tu sucursal actual en la barra para ver sus agendas.'
                  : 'Las agendas activas de tu sucursal actual.'
              }
              {...scheduleField}
            />
            <SelectField
              className="mb-3"
              label="Servicio"
              options={serviceOptions}
              error={errors.service_id?.message}
              {...serviceField}
            />
            <TextField
              label="Duración (minutos)"
              type="number"
              required
              min={GRID_MINUTES}
              max={MAX_MINUTES}
              step={GRID_MINUTES}
              inputMode="numeric"
              hint={
                times === null ? 'De 5 a 720 minutos, de a 5.' : `Termina a las ${times.endClock}.`
              }
              error={errors.duration_minutes?.message}
              {...register('duration_minutes')}
            />
          </Card>
        </div>
        <div className="col-12">
          <Card title="Horario">
            <div className="row">
              <div className="col-md-4">
                <DateField
                  label="Fecha"
                  required
                  min={today}
                  error={errors.date?.message}
                  {...register('date')}
                />
              </div>
              <div className="col-md-8">
                <Controller
                  control={control}
                  name="time"
                  render={({ field, fieldState }) => (
                    <SlotPicker
                      scheduleId={scheduleValue === '' ? null : Number(scheduleValue)}
                      serviceId={serviceValue === '' ? null : Number(serviceValue)}
                      duration={duration}
                      date={date}
                      today={today}
                      value={field.value}
                      onChange={field.onChange}
                      inputRef={field.ref}
                      error={fieldState.error?.message}
                      current={current}
                    />
                  )}
                />
              </div>
            </div>
          </Card>
        </div>
        {current === undefined ? (
          <div className="col-12">
            <SeriesFields form={form} time={time} duration={duration} />
          </div>
        ) : null}
        <div className="col-12">
          <Card title="Más datos">
            <div className="row">
              <div className="col-md-4">
                <RadioGroup label="Origen" options={SOURCE_OPTIONS} {...register('source')} />
              </div>
              <div className="col-md-8">
                <TextField
                  label="Motivo"
                  maxLength={255}
                  error={errors.reason?.message}
                  {...register('reason')}
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
          </Card>
        </div>
      </div>
      <div className="d-flex gap-2 flex-wrap mt-3 appointment-form-buttons">
        <Button type="submit" loading={saving}>
          {submitLabel}
        </Button>
        {cancel}
      </div>
    </form>
  );
}

/** El aviso de la edición y la reprogramación de un turno de una serie (H14: la API modifica cada turno por separado). */
export const SERIES_EDIT_NOTICE = 'Es un turno de una serie: el cambio vale solo para este turno.';

const SERIES_END_OPTIONS = [
  { value: 'count', label: 'Después de una cantidad de turnos' },
  { value: 'until', label: 'En una fecha' },
];

/**
 * "Repetir" (3.6, solo en el alta): cada 1 a 12 semanas, hasta una cantidad de turnos (2 a 52) o una fecha, con las reglas de
 * la API (Zod, `seriesError`) y la vista previa de las fechas en Asunción (la misma hora de pared, también del otro lado de un
 * cambio de horario) antes de guardar. La API vuelve a revisar todo y decide el horario, los bloqueos y la superposición de
 * cada fecha.
 */
function SeriesFields({
  form,
  time,
  duration,
}: {
  form: UseFormReturn<AppointmentFormValues>;
  time: string;
  duration: number;
}) {
  const {
    control,
    register,
    formState: { errors },
  } = form;
  const [repeat, date, everyWeeks, end, count, until] = useWatch({
    control,
    name: ['repeat', 'date', 'every_weeks', 'series_end', 'series_count', 'series_until'],
  });
  const input = seriesInputOf({
    repeat,
    date,
    every_weeks: everyWeeks,
    series_end: end,
    series_count: count,
    series_until: until,
  });
  const occurrences =
    input === null || appointmentTimes(date, time, duration) === null
      ? []
      : seriesOccurrences(input, time, duration);
  const summary = seriesSummary(occurrences, input?.everyWeeks ?? 1);
  const previewId = useId();

  let status = '';

  if (repeat && occurrences.length === 0) {
    status =
      input === null
        ? 'Completá la fecha del primer turno y el fin de la serie para ver las fechas.'
        : 'Completá la hora y la duración para ver las fechas.';
  }

  return (
    <Card title="Repetir">
      <CheckboxField
        label="Repetir el turno (una serie)"
        hint="Los turnos de la serie se dan todos juntos: si alguna fecha no se puede dar, no se da ninguno."
        {...register('repeat')}
      />
      {repeat ? (
        <div className="appointment-series">
          <div className="row">
            <div className="col-sm-6 col-md-3">
              <TextField
                label="Cada cuántas semanas"
                type="number"
                required
                min={1}
                max={SERIES_MAX_EVERY_WEEKS}
                inputMode="numeric"
                hint={`De 1 a ${String(SERIES_MAX_EVERY_WEEKS)}.`}
                error={errors.every_weeks?.message}
                {...register('every_weeks')}
              />
            </div>
            <div className="col-sm-6 col-md-4">
              <RadioGroup
                label="Termina"
                options={SERIES_END_OPTIONS}
                {...register('series_end')}
              />
            </div>
            <div className="col-md-5">
              {end === 'count' ? (
                <TextField
                  label="Cantidad de turnos"
                  type="number"
                  required
                  min={SERIES_MIN_COUNT}
                  max={SERIES_MAX_COUNT}
                  inputMode="numeric"
                  hint={`De ${String(SERIES_MIN_COUNT)} a ${String(SERIES_MAX_COUNT)}, como mucho un año.`}
                  error={errors.series_count?.message}
                  {...register('series_count')}
                />
              ) : (
                <DateField
                  label="Hasta"
                  required
                  min={date === '' ? undefined : date}
                  hint="Inclusive; como mucho un año después del primer turno."
                  error={errors.series_until?.message}
                  {...register('series_until')}
                />
              )}
            </div>
          </div>
          <p className="mb-2 appointment-series-summary" role="status" aria-live="polite">
            {summary === '' ? status : `${summary}.`}
          </p>
          {occurrences.length > 0 ? (
            <ol
              id={previewId}
              className="appointment-series-preview"
              aria-label="Las fechas de la serie"
              tabIndex={0}
            >
              {occurrences.map((occurrence) => (
                <li key={occurrence.day}>
                  {dayTitle(occurrence.day)}, {formatTime(occurrence.starts_at)} a{' '}
                  {formatTime(occurrence.ends_at)}
                </li>
              ))}
            </ol>
          ) : null}
        </div>
      ) : null}
    </Card>
  );
}

/**
 * Los horarios libres del día (`GET schedules/{id}/availability`) como un grupo de radios y "Otra hora" para escribirla (la
 * del que llegó sin turno, o la que la disponibilidad no ofrece: la API decide, H7). Un horario que no está entre los libres
 * (el del turno que se reprograma, que se ocupa a sí mismo, o el de la celda elegida) se muestra en "Otra hora".
 */
function SlotPicker({
  scheduleId,
  serviceId,
  duration,
  date,
  today,
  value,
  onChange,
  inputRef,
  error,
  current,
}: {
  scheduleId: number | null;
  serviceId: number | null;
  duration: number;
  date: string;
  today: string;
  value: string;
  onChange: (value: string) => void;
  inputRef: (element: HTMLElement | null) => void;
  error: string | undefined;
  current: AppointmentDetail | undefined;
}) {
  const groupName = useId();
  const errorId = `${groupName}-error`;
  const [manual, setManual] = useState(false);
  const validDuration =
    Number.isInteger(duration) &&
    duration >= GRID_MINUTES &&
    duration <= MAX_MINUTES &&
    duration % GRID_MINUTES === 0;
  const ready =
    scheduleId !== null && /^\d{4}-\d{2}-\d{2}$/.test(date) && date >= today && validDuration;
  const query = useQuery({
    ...availabilityQueryOptions(scheduleId ?? 0, date, serviceId, validDuration ? duration : 0),
    enabled: ready,
  });
  const slots = ready
    ? (query.data?.days.find((day) => day.date === date)?.slots ?? []).map(
        (slot) => instantToWall(slot.starts_at).clock,
      )
    : [];
  const loaded = ready && query.data !== undefined;
  const choice =
    manual || (value !== '' && (!ready || (loaded && !slots.includes(value)))) ? OTHER_TIME : value;
  const otherRef = useRef<HTMLInputElement | null>(null);
  const currentClock = current === undefined ? null : instantToWall(current.starts_at);

  let status = '';

  if (!ready) {
    status = 'Elegí la agenda, la fecha y la duración para ver los horarios libres.';
  } else if (query.isError) {
    status = isApiError(query.error)
      ? Object.values(query.error.fieldErrors()).flat().join(' ') || userMessage(query.error)
      : MESSAGES.programming;
  } else if (!loaded) {
    status = 'Buscando los horarios libres…';
  } else if (slots.length === 0) {
    status = 'No quedan horarios libres ese día: elegí otra fecha u "Otra hora".';
  }

  return (
    <fieldset className="mb-3 appointment-slots" aria-describedby={error ? errorId : undefined}>
      <legend className="form-label required">Hora</legend>
      <p
        className="text-secondary small mb-2 appointment-slots-status"
        role="status"
        aria-live="polite"
      >
        {status}
      </p>
      <div className="form-selectgroup appointment-slot-options">
        {slots.map((clock, index) => (
          <label key={clock} className="form-selectgroup-item">
            <input
              ref={index === 0 ? inputRef : undefined}
              type="radio"
              className="form-selectgroup-input"
              name={groupName}
              value={clock}
              checked={choice === clock}
              onChange={() => {
                setManual(false);
                onChange(clock);
              }}
            />
            <span className="form-selectgroup-label">{clock}</span>
          </label>
        ))}
        <label className="form-selectgroup-item">
          <input
            ref={slots.length === 0 ? inputRef : undefined}
            type="radio"
            className="form-selectgroup-input appointment-other-time"
            name={groupName}
            value={OTHER_TIME}
            checked={choice === OTHER_TIME}
            onChange={() => {
              setManual(true);
              window.setTimeout(() => otherRef.current?.focus(), 0);
            }}
          />
          <span className="form-selectgroup-label">Otra hora</span>
        </label>
      </div>
      {choice === OTHER_TIME ? (
        <div className="mt-2 appointment-time-field">
          <TimeField
            label="Escribí la hora"
            step={GRID_MINUTES * 60}
            value={value}
            ref={otherRef}
            hint={
              currentClock !== null &&
              current !== undefined &&
              value === currentClock.clock &&
              date === currentClock.day
                ? 'La hora actual del turno.'
                : 'La agenda la acepta si está dentro de su horario y libre.'
            }
            onChange={(event) => {
              onChange(event.target.value);
            }}
          />
        </div>
      ) : null}
      {error ? (
        <div id={errorId} className="invalid-feedback d-block appointment-time-error">
          {error}
        </div>
      ) : null}
    </fieldset>
  );
}

/**
 * Lo común de los dos buscadores: el texto con la espera y el foco. Al elegir uno o al tocar "Cambiar", el control que se
 * ve desaparece: el foco pasa al que aparece ("Cambiar" o el buscador), en lugar de perderse en el cuerpo de la página.
 */
function useOptionsSearch(value: unknown, inputRef: (element: HTMLElement | null) => void) {
  const [text, setText] = useState('');
  const search = useDebouncedValue(normalizeSearchText(text), SEARCH_DEBOUNCE_MS);
  const element = useRef<HTMLElement | null>(null);
  const focusNext = useRef(false);

  useEffect(() => {
    if (focusNext.current) {
      focusNext.current = false;
      element.current?.focus();
    }
  }, [value]);

  const ref = useCallback(
    (node: HTMLElement | null) => {
      element.current = node;
      inputRef(node);
    },
    [inputRef],
  );

  return {
    text,
    setText,
    search,
    ref,
    moveFocus: () => {
      focusNext.current = true;
    },
  };
}

function SelectedChoice({
  label,
  detail,
  onChange,
  error,
  inputRef,
  name,
}: {
  label: string;
  detail: string;
  onChange: () => void;
  error: string | undefined;
  inputRef: (element: HTMLElement | null) => void;
  name: string;
}) {
  return (
    <div className="mb-3 appointment-who-selected">
      <div className="form-label">{name}</div>
      <div className="d-flex gap-2 align-items-center flex-wrap">
        <span>
          <strong>{label}</strong>
          {detail === '' ? null : <span className="text-secondary"> · {detail}</span>}
        </span>
        <Button variant="secondary" className="btn-sm" ref={inputRef} onClick={onChange}>
          Cambiar
        </Button>
      </div>
      {error ? <div className="invalid-feedback d-block">{error}</div> : null}
    </div>
  );
}

/** El paciente: la búsqueda de `GET patients?search=` (también por el dueño); elegirlo fija el cliente, que se muestra. */
function PatientPicker({
  value,
  onChange,
  error,
  inputRef,
}: {
  value: PatientChoice | null;
  onChange: (value: PatientChoice | null) => void;
  error: string | undefined;
  inputRef: (element: HTMLElement | null) => void;
}) {
  const { text, setText, search, ref, moveFocus } = useOptionsSearch(value, inputRef);
  const query = useQuery({
    ...patientOptionsQueryOptions(search),
    enabled: search !== '' && value === null,
  });

  if (value !== null) {
    return (
      <SelectedChoice
        name="Paciente"
        label={`${value.name} (${value.code})`}
        detail={value.client === null ? '' : `de ${value.client.display_name}`}
        error={error}
        inputRef={ref}
        onChange={() => {
          moveFocus();
          onChange(null);
        }}
      />
    );
  }

  const options = search === '' ? [] : (query.data ?? []);

  return (
    <OptionsList
      name="Paciente"
      placeholder="Buscá por el nombre o el código del paciente, o por su dueño"
      text={text}
      setText={setText}
      search={search}
      query={query}
      error={error}
      inputRef={ref}
      empty="No hay pacientes con esa búsqueda."
      options={options.map((patient) => ({
        id: patient.id,
        label: `${patient.name} (${patient.code})`,
        detail: patient.client === null ? '' : `de ${patient.client.display_name}`,
        pick: () => {
          moveFocus();
          onChange({
            id: patient.id,
            code: patient.code,
            name: patient.name,
            client: patient.client,
          });
        },
      }))}
    />
  );
}

/** El cliente sin paciente: la búsqueda de `GET clients?search=`. */
function ClientPicker({
  value,
  onChange,
  error,
  inputRef,
}: {
  value: ClientChoice | null;
  onChange: (value: ClientChoice | null) => void;
  error: string | undefined;
  inputRef: (element: HTMLElement | null) => void;
}) {
  const { text, setText, search, ref, moveFocus } = useOptionsSearch(value, inputRef);
  const query = useQuery({
    ...clientOptionsQueryOptions(search),
    enabled: search !== '' && value === null,
  });

  if (value !== null) {
    return (
      <SelectedChoice
        name="Cliente"
        label={value.display_name}
        detail=""
        error={error}
        inputRef={ref}
        onChange={() => {
          moveFocus();
          onChange(null);
        }}
      />
    );
  }

  const options = search === '' ? [] : (query.data ?? []);

  return (
    <OptionsList
      name="Cliente"
      placeholder="Buscá por nombre, documento o teléfono"
      text={text}
      setText={setText}
      search={search}
      query={query}
      error={error}
      inputRef={ref}
      empty="No hay clientes con esa búsqueda."
      options={options.map((client) => ({
        id: client.id,
        label: client.display_name,
        detail: client.document_number,
        pick: () => {
          moveFocus();
          onChange({ id: client.id, display_name: client.display_name });
        },
      }))}
    />
  );
}

interface PickOption {
  id: number;
  label: string;
  detail: string;
  pick: () => void;
}

function OptionsList({
  name,
  placeholder,
  text,
  setText,
  search,
  query,
  error,
  inputRef,
  empty,
  options,
}: {
  name: string;
  placeholder: string;
  text: string;
  setText: (text: string) => void;
  search: string;
  query: { isError: boolean; error: unknown; data: unknown; isFetching: boolean };
  error: string | undefined;
  inputRef: (element: HTMLElement | null) => void;
  empty: string;
  options: PickOption[];
}) {
  const errorId = useId();
  let status = '';

  if (search === '') {
    status = 'Escribí para buscar.';
  } else if (query.isError) {
    status = isApiError(query.error) ? userMessage(query.error) : MESSAGES.programming;
  } else if (query.data === undefined) {
    status = 'Buscando…';
  } else if (options.length === 0) {
    status = empty;
  }

  return (
    <div className="mb-3 appointment-who-search">
      <SearchField
        label={`Buscar el ${name.toLowerCase()}`}
        placeholder={placeholder}
        value={text}
        busy={query.isFetching}
        ref={inputRef}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        onChange={setText}
      />
      <p className="text-secondary small mt-1 mb-1" role="status" aria-live="polite">
        {status}
      </p>
      {options.length > 0 ? (
        <div className="list-group appointment-who-options">
          {options.map((option) => (
            <button
              key={option.id}
              type="button"
              className="list-group-item list-group-item-action text-start"
              onClick={option.pick}
            >
              <span className="d-block">{option.label}</span>
              {option.detail === '' ? null : (
                <small className="text-secondary">{option.detail}</small>
              )}
            </button>
          ))}
        </div>
      ) : null}
      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : null}
    </div>
  );
}
