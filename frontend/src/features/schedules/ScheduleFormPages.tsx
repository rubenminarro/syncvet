import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import {
  afterScheduleChanged,
  createSchedule,
  scheduleDetailQueryOptions,
  scheduleKeys,
  updateSchedule,
} from './api';
import {
  EMPTY_SCHEDULE_FORM,
  SCHEDULE_FORM_FIELDS,
  scheduleFormToPayload,
  scheduleToForm,
  type ScheduleFormValues,
} from './form';
import { useScheduleListSearch, withScheduleList } from './navigation';
import { ScheduleForm } from './ScheduleForm';
import type { ScheduleDetail } from './types';
import { useScheduleGone } from './use-schedule-gone';

interface SaveVariables {
  values: ScheduleFormValues;
  form: UseFormReturn<ScheduleFormValues>;
}

interface SaveErrorContext {
  form: UseFormReturn<ScheduleFormValues>;
  setAlert: (alert: string | null) => void;
  onGone?: (message: string) => void;
  onConflict?: () => void;
}

/**
 * Qué hace el formulario con cada error de la API (frontend/CLAUDE.md §6): un 422 a sus campos (`hours.1.ends_at`, al
 * tramo; la clave `hours` de un día, a la alerta); `SCHEDULE_NOT_EDITABLE` (la agenda con turnos no cambia de sucursal ni
 * de veterinario) y los demás códigos de negocio, a la alerta de arriba con el `message` de la API.
 */
function handleSaveError(error: unknown, context: SaveErrorContext) {
  if (!isApiError(error)) {
    context.setAlert(MESSAGES.programming);

    return;
  }

  switch (errorTreatment(error.errorCode)) {
    case 'validation': {
      const others =
        applyValidationErrors(error, context.form.setError, SCHEDULE_FORM_FIELDS) ?? [];
      context.setAlert(others.length > 0 ? others.join(' ') : null);
      break;
    }
    case 'not-found':
      if (context.onGone !== undefined) {
        context.onGone(error.message);
      } else {
        context.setAlert(userMessage(error));
      }
      break;
    case 'conflict':
      context.setAlert(userMessage(error));
      context.onConflict?.();
      break;
    default:
      context.setAlert(userMessage(error));
  }
}

function Breadcrumb({ schedule }: { schedule?: ScheduleDetail }) {
  const listSearch = useScheduleListSearch();

  return (
    <div className="page-pretitle">
      <Link to="/configuracion">Configuración</Link>
      {' / '}
      <Link to="/configuracion/agendas" search={listSearch}>
        Agendas
      </Link>
      {schedule !== undefined ? (
        <>
          {' / '}
          <Link
            to="/configuracion/agendas/$id"
            params={{ id: String(schedule.id) }}
            state={withScheduleList(listSearch)}
          >
            {schedule.name}
          </Link>
        </>
      ) : null}
    </div>
  );
}

/** /configuracion/agendas/nueva (3.1a): con el 201, al detalle (la agenda queda activa). */
export function ScheduleCreatePage() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useScheduleListSearch();
  const single = useSingleAction();
  const [alert, setAlert] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) => createSchedule(scheduleFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, { form, setAlert });
    },
    onSuccess: (response) => {
      afterScheduleChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/configuracion/agendas/$id',
        params: { id: String(response.data.id) },
        replace: true,
        state: withScheduleList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb />
        <h2 className="page-title">Nueva agenda</h2>
      </div>
      <ScheduleForm
        defaultValues={EMPTY_SCHEDULE_FORM}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (single.tryStart()) {
            mutation.mutate({ values, form });
          }
        }}
        cancel={
          <Link
            to="/configuracion/agendas"
            search={listSearch}
            replace
            className="btn btn-link schedule-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}

/**
 * /configuracion/agendas/$id/editar: el formulario completo, con el horario entero (reemplaza al anterior; sin tramos,
 * `hours: []`); con el 200, de vuelta al detalle.
 */
export function ScheduleEditPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useScheduleListSearch();
  const single = useSingleAction();
  const onGone = useScheduleGone(id);
  const { data: schedule } = useSuspenseQuery(scheduleDetailQueryOptions(id));
  const [alert, setAlert] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: ({ values }: SaveVariables) => updateSchedule(id, scheduleFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onError: (error, { form }) => {
      handleSaveError(error, {
        form,
        setAlert,
        onGone,
        onConflict: () => {
          void queryClient.invalidateQueries({ queryKey: scheduleKeys.detail(id) });
        },
      });
    },
    onSuccess: (response) => {
      afterScheduleChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/configuracion/agendas/$id',
        params: { id: String(id) },
        replace: true,
        state: withScheduleList(listSearch),
      });
    },
    onSettled: () => {
      single.release();
    },
  });

  return (
    <>
      <div className="page-header mb-3">
        <Breadcrumb schedule={schedule} />
        <h2 className="page-title">Editar la agenda</h2>
      </div>
      <ScheduleForm
        defaultValues={scheduleToForm(schedule)}
        current={schedule}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (single.tryStart()) {
            mutation.mutate({ values, form });
          }
        }}
        cancel={
          <Link
            to="/configuracion/agendas/$id"
            params={{ id: String(id) }}
            state={withScheduleList(listSearch)}
            replace
            className="btn btn-link schedule-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}
