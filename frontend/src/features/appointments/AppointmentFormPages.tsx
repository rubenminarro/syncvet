import { useMutation, useQuery, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useMemo, useRef, useState, type ReactNode } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { Alert, Button, ConfirmDialog, Loading, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { meQueryOptions } from '../auth/api';
import { useCan } from '../auth/permissions';
import { serviceOptionsQueryOptions } from '../schedules/api';
import {
  afterAppointmentSaved,
  appointmentDetailKeys,
  appointmentDetailQueryOptions,
  branchScheduleOptionsQueryOptions,
  createAppointment,
  updateAppointment,
} from './api';
import { AppointmentForm } from './AppointmentForm';
import { dayTitle, wallDay } from './dates';
import {
  appointmentFormToPayload,
  appointmentToForm,
  defaultDuration,
  EMPTY_APPOINTMENT_FORM,
  mapAppointmentErrors,
  SLOT_ERROR_CODES,
  type AppointmentFormValues,
} from './form';
import type { NewAppointmentSearch } from './search';
import { parseInicio } from './times';
import { EDITABLE_STATUSES, type AppointmentDetail } from './types';

/** El texto del diálogo del sobreturno (pregunta 8 del diseño de la F3). */
export const OVERBOOK_QUESTION = 'El horario ya está ocupado. ¿Querés darlo igual como sobreturno?';

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
  ) => Promise<{ data: AppointmentDetail; message: string }>;
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
  const [confirmOverbook, setConfirmOverbook] = useState(false);
  const alertRef = useRef<HTMLDivElement>(null);
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
    onSuccess: (response) => {
      afterAppointmentSaved(queryClient, response.data, options.previousScheduleId);
      toast.show(response.message, 'success');
      void navigate({
        to: '/turnos/$id',
        params: { id: String(response.data.id) },
        replace: true,
      });
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
                  setConfirmOverbook(true);
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

  const dialog = (
    <ConfirmDialog
      open={confirmOverbook}
      tone="primary"
      title="Dar como sobreturno"
      confirmLabel="Dar como sobreturno"
      busy={mutation.isPending}
      onConfirm={overbook}
      onClose={() => {
        setConfirmOverbook(false);
      }}
    >
      <p className="mb-0">{OVERBOOK_QUESTION}</p>
    </ConfirmDialog>
  );

  return { submit, alertNode, dialog, saving: mutation.isPending };
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
    save: (values, overbook) => createAppointment(appointmentFormToPayload(values, { overbook })),
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
