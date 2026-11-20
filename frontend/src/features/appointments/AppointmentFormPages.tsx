import { useMutation, useQuery, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { Alert, Button, ConfirmDialog, Loading, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { formatTime } from '../../lib/format/dates';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { meQueryOptions } from '../auth/api';
import { useCan } from '../auth/permissions';
import { serviceOptionsQueryOptions } from '../schedules/api';
import {
  afterAppointmentSaved,
  afterSeriesCreated,
  appointmentDetailKeys,
  appointmentDetailQueryOptions,
  branchScheduleOptionsQueryOptions,
  createAppointment,
  createAppointmentSeries,
  updateAppointment,
} from './api';
import { AppointmentForm } from './AppointmentForm';
import { dayTitle, wallDay } from './dates';
import {
  appointmentFormToPayload,
  appointmentSeriesPayload,
  appointmentToForm,
  defaultDuration,
  EMPTY_APPOINTMENT_FORM,
  mapAppointmentErrors,
  SLOT_ERROR_CODES,
  type AppointmentFormValues,
} from './form';
import type { NewAppointmentSearch } from './search';
import { parseInicio } from './times';
import {
  onlyOverlaps,
  SERIES_CONFLICT_HINTS,
  seriesConflicts,
  type SeriesConflict,
} from './series';
import { EDITABLE_STATUSES, type Appointment, type AppointmentDetail } from './types';

/** El texto del diálogo del sobreturno (pregunta 8 del diseño de la F3). */
export const OVERBOOK_QUESTION = 'El horario ya está ocupado. ¿Querés darlo igual como sobreturno?';

/** El sobreturno de una serie (3.6): vale para todas las fechas y saltea solo la superposición. */
export const SERIES_OVERBOOK_QUESTION =
  'Las fechas de la lista tienen el horario ocupado. ¿Querés dar la serie igual, con esas fechas como sobreturnos?';

interface SaveVariables {
  values: AppointmentFormValues;
  form: UseFormReturn<AppointmentFormValues>;
  overbook: boolean;
}

/**
 * Lo que muestra arriba del formulario: el texto, si el 409 de la superposición ofrece el sobreturno y si el 409 de un
 * cambio concurrente ofrece cargar la versión actual (`reloaded`: ya se cargó).
 */
interface SaveAlert {
  message: string;
  overlap: boolean;
  conflict?: boolean;
  reloaded?: boolean;
  /** El 409 de la serie (3.6): cada fecha que no entra, con su motivo. */
  seriesConflicts?: SeriesConflict[];
  /** Si "Dar la serie con sobreturnos" sirve: todas son superposiciones y todavía no se pidió. */
  seriesOverlap?: boolean;
}

/** El aviso después de "Cargar la versión actual". */
export const RELOADED_MESSAGE =
  'Se cargó la versión actual del turno: revisala y, si hace falta, volvé a hacer tus cambios.';

/**
 * El guardado del alta y de la edición: un solo envío (`useSingleAction`), cada error de la API según su `error_code`
 * (frontend/CLAUDE.md §6 y el diseño de la 3.4) y el sobreturno después del 409 `APPOINTMENT_OVERLAP`, solo con
 * `appointments.overbook` (sin él, `overbook` nunca va: la API respondería 403). El formulario nunca se vacía con un error.
 */
function useAppointmentSave(options: {
  save: (
    values: AppointmentFormValues,
    overbook: boolean,
  ) => Promise<{ data: AppointmentDetail | Appointment[]; message: string }>;
  previousScheduleId?: number;
  detailId?: number;
  /** La edición: después del 409 de un cambio concurrente, "Cargar la versión actual" (pide el turno y rearma el formulario). */
  reload?: () => Promise<void>;
}) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const can = useCan();
  const single = useSingleAction();
  const canOverbook = can(PERMISSIONS.appointmentsOverbook);
  const [alert, setAlert] = useState<SaveAlert | null>(null);
  // El diálogo del sobreturno: el de un turno o el de la serie (el texto no depende de la alerta, que se vacía al reenviar).
  const [confirmOverbook, setConfirmOverbook] = useState<false | 'single' | 'series'>(false);
  const alertRef = useRef<HTMLDivElement>(null);

  // La lista de conflictos de la serie: el foco, a la alerta (el lector de pantalla la lee; nada del formulario cambió).
  // Después del render que la dibuja, no con un setTimeout (en Edge corría antes de ese render y el foco no llegaba: control
  // 3 de la 3.6, como el desvío de la 3.5).
  useEffect(() => {
    if (alert?.seriesConflicts !== undefined) {
      alertRef.current?.focus();
    }
  }, [alert]);
  const last = useRef<{
    values: AppointmentFormValues;
    form: UseFormReturn<AppointmentFormValues>;
  } | null>(null);

  const refreshAvailability = (values: AppointmentFormValues) => {
    void queryClient.invalidateQueries({
      queryKey: ['schedules', Number(values.schedule_id), 'availability'],
    });
  };

  const mutation = useMutation({
    mutationFn: ({ values, overbook }: SaveVariables) =>
      options.save(values, overbook && canOverbook),
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { values, form, overbook }) => {
      setConfirmOverbook(false);

      if (!isApiError(error)) {
        setAlert({ message: MESSAGES.programming, overlap: false });

        return;
      }

      const conflicts = seriesConflicts(error);

      if (conflicts !== null) {
        // La serie no entra: la lista por fecha (H8), sin crear ninguno; los horarios libres, de nuevo.
        setAlert({
          message: error.message,
          overlap: false,
          seriesConflicts: conflicts,
          seriesOverlap: onlyOverlaps(conflicts) && !overbook,
        });
        refreshAvailability(values);

        return;
      }

      const mapped = mapAppointmentErrors(error, values.who);

      if (mapped !== null) {
        mapped.fields.forEach(([field, message], index) => {
          form.setError(field, { type: 'server', message }, { shouldFocus: index === 0 });
        });
        setAlert(
          mapped.others.length > 0 ? { message: mapped.others.join(' '), overlap: false } : null,
        );

        // La agenda o el día pudieron cambiar: los horarios libres, de nuevo.
        refreshAvailability(values);

        return;
      }

      if (SLOT_ERROR_CODES.has(error.errorCode)) {
        // Al horario, con la disponibilidad vuelta a pedir (la grilla de horarios libres se actualiza sola).
        form.setError('time', { type: 'server', message: error.message }, { shouldFocus: true });
        refreshAvailability(values);
        setAlert({
          message: error.message,
          overlap: error.errorCode === 'APPOINTMENT_OVERLAP' && !overbook,
        });

        return;
      }

      switch (errorTreatment(error.errorCode)) {
        case 'not-found':
          if (options.detailId !== undefined) {
            toast.show(error.message);
            queryClient.removeQueries({ queryKey: appointmentDetailKeys.detail(options.detailId) });
            void navigate({ to: '/agenda', replace: true });

            return;
          }
          setAlert({ message: error.message, overlap: false });
          break;
        case 'conflict':
          // Otra PC cambió el turno o la agenda en el medio: el aviso y lo cargado sigue (guardar de nuevo manda solo lo
          // que se cambió acá, sin pisar lo del otro); los horarios libres, de nuevo; en la edición, "Cargar la versión
          // actual" para verla antes de guardar.
          setAlert({
            message: userMessage(error),
            overlap: false,
            conflict: options.reload !== undefined,
          });
          refreshAvailability(values);

          if (options.detailId !== undefined) {
            // Si el otro lo fichó o lo canceló, la página pasa a "Este turno ya no se modifica".
            void queryClient.invalidateQueries({
              queryKey: appointmentDetailKeys.detail(options.detailId),
            });
          }
          break;
        default:
          setAlert({ message: userMessage(error), overlap: false });

          if (error.errorCode === 'APPOINTMENT_NOT_EDITABLE' && options.detailId !== undefined) {
            void queryClient.invalidateQueries({
              queryKey: appointmentDetailKeys.detail(options.detailId),
            });
          }
      }
    },
    onSuccess: (response, { values }) => {
      const { data } = response;
      let firstId: number | undefined;

      if (Array.isArray(data)) {
        // La serie: al primer turno, con "Serie de N turnos creada correctamente." (el message de la API).
        afterSeriesCreated(queryClient, Number(values.schedule_id));
        firstId = data[0]?.id;
      } else {
        afterAppointmentSaved(queryClient, data, options.previousScheduleId);
        firstId = data.id;
      }

      toast.show(response.message, 'success');

      if (firstId !== undefined) {
        void navigate({ to: '/turnos/$id', params: { id: String(firstId) }, replace: true });
      }
    },
    onSettled: () => {
      single.release();
    },
  });

  const submit = (values: AppointmentFormValues, form: UseFormReturn<AppointmentFormValues>) => {
    if (single.tryStart()) {
      last.current = { values, form };
      mutation.mutate({ values, form, overbook: false });
    }
  };

  const overbook = () => {
    const pending = last.current;

    if (pending !== null && canOverbook && single.tryStart()) {
      mutation.mutate({ ...pending, overbook: true });
    }
  };

  const reload = async () => {
    if (options.reload === undefined || !single.tryStart()) {
      return;
    }

    try {
      await options.reload();
      setAlert({ message: RELOADED_MESSAGE, overlap: false, reloaded: true });
      // El botón desaparece con el formulario rearmado: el foco, al aviso.
      window.setTimeout(() => alertRef.current?.focus(), 0);
    } catch (error) {
      setAlert({
        message: isApiError(error) ? userMessage(error) : MESSAGES.programming,
        overlap: false,
        conflict: true,
      });
    } finally {
      single.release();
    }
  };

  const alertNode: ReactNode =
    alert === null ? null : (
      <div className="mb-3 appointment-alert" ref={alertRef} tabIndex={-1}>
        <Alert
          tone={alert.reloaded === true ? 'info' : 'danger'}
          {...(alert.overlap ? { title: 'El horario ya está ocupado' } : {})}
        >
          {alert.message}
          {alert.seriesConflicts !== undefined ? (
            <SeriesConflictList
              conflicts={alert.seriesConflicts}
              overlap={alert.seriesOverlap === true}
              canOverbook={canOverbook}
              onOverbook={() => {
                setConfirmOverbook('series');
              }}
            />
          ) : null}
          {alert.conflict === true ? (
            <div className="mt-2">
              <Button
                variant="secondary"
                className="appointment-reload"
                onClick={() => {
                  void reload();
                }}
              >
                Cargar la versión actual
              </Button>
            </div>
          ) : null}
          {alert.overlap && canOverbook ? (
            <div className="mt-2">
              <Button
                variant="secondary"
                className="appointment-overbook"
                onClick={() => {
                  setConfirmOverbook('single');
                }}
              >
                Dar como sobreturno
              </Button>
            </div>
          ) : null}
          {alert.overlap && !canOverbook ? (
            <div className="mt-1 small">
              Elegí otro horario: no tenés permiso para dar sobreturnos.
            </div>
          ) : null}
        </Alert>
      </div>
    );

  const forSeries = confirmOverbook === 'series';
  const dialog = (
    <ConfirmDialog
      open={confirmOverbook !== false}
      tone="primary"
      title={forSeries ? 'Dar la serie con sobreturnos' : 'Dar como sobreturno'}
      confirmLabel={forSeries ? 'Dar la serie con sobreturnos' : 'Dar como sobreturno'}
      busy={mutation.isPending}
      onConfirm={overbook}
      onClose={() => {
        setConfirmOverbook(false);
      }}
    >
      <p className="mb-0">{forSeries ? SERIES_OVERBOOK_QUESTION : OVERBOOK_QUESTION}</p>
    </ConfirmDialog>
  );

  return {
    submit,
    alertNode,
    dialog,
    saving: mutation.isPending,
    seriesConflicts: alert?.seriesConflicts,
  };
}

/**
 * La lista de `errors.conflicts` (H8): cada fecha y hora en Asunción con el `message` de la API, y lo que se puede hacer: con
 * todas superposiciones y `appointments.overbook`, "Dar la serie con sobreturnos"; si no, corregir o cancelar (pregunta 17).
 */
function SeriesConflictList({
  conflicts,
  overlap,
  canOverbook,
  onOverbook,
}: {
  conflicts: readonly SeriesConflict[];
  overlap: boolean;
  canOverbook: boolean;
  onOverbook: () => void;
}) {
  let hint: ReactNode = SERIES_CONFLICT_HINTS.fix;

  if (overlap && canOverbook) {
    hint = (
      <Button variant="secondary" className="appointment-series-overbook" onClick={onOverbook}>
        Dar la serie con sobreturnos
      </Button>
    );
  } else if (overlap) {
    hint = SERIES_CONFLICT_HINTS.noPermission;
  }

  return (
    <>
      <ul
        className="mt-2 appointment-series-conflicts"
        aria-label="Las fechas que no se pueden dar"
      >
        {conflicts.map((conflict) => (
          <li key={conflict.starts_at}>
            <strong>
              {dayTitle(wallDay(conflict.starts_at))}, {formatTime(conflict.starts_at)}
            </strong>
            : {conflict.message}
          </li>
        ))}
      </ul>
      <div className="mt-2 small appointment-series-conflict-hint">{hint}</div>
    </>
  );
}

function Breadcrumb({ appointment }: { appointment?: AppointmentDetail }) {
  return (
    <div className="page-pretitle">
      <Link to="/agenda">Agenda</Link>
      {appointment === undefined ? null : (
        <>
          {' / '}
          <Link to="/turnos/$id" params={{ id: String(appointment.id) }}>
            Turno del {dayTitle(wallDay(appointment.starts_at)).toLowerCase()}
          </Link>
        </>
      )}
    </div>
  );
}

/**
 * /turnos/nuevo?agenda=&inicio= (3.4, administrador y recepción): desde la grilla, la celda elegida llena la agenda y el
 * inicio; la agenda trae su servicio y la duración. Con el 201, al detalle del turno.
 */
export function AppointmentCreatePage({ search }: { search: NewAppointmentSearch }) {
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branchId = me?.current_branch?.id ?? null;
  const schedulesQuery = useQuery({
    ...branchScheduleOptionsQueryOptions(branchId ?? 0),
    enabled: branchId !== null,
  });
  const servicesQuery = useQuery(serviceOptionsQueryOptions());
  const save = useAppointmentSave({
    save: (values, overbook) =>
      values.repeat
        ? createAppointmentSeries(appointmentSeriesPayload(values, { overbook }))
        : createAppointment(appointmentFormToPayload(values, { overbook })),
  });
  const waiting = (branchId !== null && schedulesQuery.isPending) || servicesQuery.isPending;

  if (waiting) {
    return <Loading />;
  }

  const schedule = schedulesQuery.data?.find((item) => item.id === search.agenda);
  const serviceId = schedule?.service?.id;
  const service = servicesQuery.data?.find((item) => item.id === serviceId);
  const inicio = search.inicio === undefined ? null : parseInicio(search.inicio);
  const defaults: AppointmentFormValues = {
    ...EMPTY_APPOINTMENT_FORM,
    schedule_id: schedule === undefined ? '' : String(schedule.id),
    service_id: serviceId === undefined ? '' : String(serviceId),
    duration_minutes: defaultDuration(service?.duration_minutes, schedule?.slot_minutes),
    date: inicio?.day ?? '',
    time: inicio?.clock ?? '',
  };

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb />
        <h2 className="page-title">Nuevo turno</h2>
      </div>
      <AppointmentForm
        defaultValues={defaults}
        alert={save.alertNode}
        saving={save.saving}
        submitLabel="Dar el turno"
        seriesConflicts={save.seriesConflicts}
        onSubmit={save.submit}
        cancel={
          <Link
            to="/agenda"
            search={{ fecha: inicio?.day, agenda: search.agenda }}
            className="btn btn-link appointment-cancel"
          >
            Cancelar
          </Link>
        }
      />
      {save.dialog}
    </>
  );
}

/**
 * /turnos/$id/editar (3.4, administrador y recepción): manda solo lo que cambió contra la versión que se cargó en el
 * formulario (`base`). "Reprogramar" (`?reprogramar=1`) es el mismo formulario con el foco en la fecha. Un turno que ya no es
 * editable (otro lo fichó o lo canceló) muestra el motivo en lugar del formulario. Después del 409 de un cambio concurrente,
 * lo cargado sigue y "Cargar la versión actual" pide el turno y rearma el formulario con él.
 */
export function AppointmentEditPage({ id, reschedule }: { id: number; reschedule: boolean }) {
  const queryClient = useQueryClient();
  const { data: appointment } = useSuspenseQuery(appointmentDetailQueryOptions(id));
  // La versión del formulario: se toma una sola vez (si el detalle se vuelve a pedir, lo cargado no se pierde) y cambia solo
  // con "Cargar la versión actual" (con `version`, el formulario se rearma).
  const [base, setBase] = useState(appointment);
  const [version, setVersion] = useState(0);
  const defaults = useMemo(() => appointmentToForm(base), [base]);
  const save = useAppointmentSave({
    save: (values, overbook) =>
      updateAppointment(id, appointmentFormToPayload(values, { original: base, overbook })),
    previousScheduleId: appointment.schedule.id,
    detailId: id,
    reload: async () => {
      const fresh = await queryClient.query({
        ...appointmentDetailQueryOptions(id),
        staleTime: 0,
      });
      setBase(fresh);
      setVersion((value) => value + 1);
    },
  });
  const title = reschedule ? 'Reprogramar el turno' : 'Editar el turno';
  const cancel = (
    <Link
      to="/turnos/$id"
      params={{ id: String(id) }}
      replace
      className="btn btn-link appointment-cancel"
    >
      Cancelar
    </Link>
  );

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb appointment={appointment} />
        <h2 className="page-title">{title}</h2>
      </div>
      {EDITABLE_STATUSES.has(appointment.status.value) ? (
        <AppointmentForm
          key={version}
          defaultValues={defaults}
          current={base}
          focusTime={reschedule}
          alert={save.alertNode}
          saving={save.saving}
          submitLabel={reschedule ? 'Reprogramar' : 'Guardar'}
          onSubmit={save.submit}
          cancel={cancel}
        />
      ) : (
        <div className="appointment-not-editable">
          <Alert tone="warning" title="Este turno ya no se modifica">
            Solo se modifican los turnos a confirmar o confirmados: este está «
            {appointment.status.label}».
          </Alert>
          {cancel}
        </div>
      )}
      {save.dialog}
    </>
  );
}
