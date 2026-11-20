import { IconPlus } from '@tabler/icons-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { format } from 'date-fns';
import { useRef, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  CheckboxField,
  ConfirmDialog,
  DataTable,
  DateField,
  Loading,
  TextareaField,
  TimeField,
  useToast,
  type Column,
} from '../../components/ui';
import { dialogError, type DialogFailure } from '../../lib/api/dialog-error';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatTime, inAppZone, todayInAppZone } from '../../lib/format/dates';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { StatusLabel } from '../appointments/StatusLabel';
import { useCan } from '../auth/permissions';
import {
  afterBlocksChanged,
  createScheduleBlock,
  deleteScheduleBlock,
  scheduleBlocksQueryOptions,
} from './api';
import {
  blockFormErrors,
  blockFormToPayload,
  emptyBlockForm,
  formatBlockPeriod,
  type BlockFormErrors,
  type BlockFormField,
  type BlockFormValues,
} from './blocks';
import {
  WEEKDAYS,
  type AffectedAppointment,
  type ScheduleBlock,
  type ScheduleDetail,
  type StoredScheduleBlock,
} from './types';
import { useScheduleGone } from './use-schedule-gone';

type BlockDialog = { type: 'new' } | { type: 'delete'; block: ScheduleBlock };

/**
 * La tarjeta "Bloqueos" del detalle de la agenda (3.1b; `schedule-blocks.index`, los tres roles): los que terminan desde
 * hoy, por página, con el período y el motivo; "Nuevo bloqueo" (`.store`) y "Eliminar" (`.destroy`), en diálogos.
 */
export function ScheduleBlocks({ schedule }: { schedule: ScheduleDetail }) {
  const can = useCan();
  const queryClient = useQueryClient();
  const toast = useToast();
  const [page, setPage] = useState(1);
  const [dialog, setDialog] = useState<BlockDialog | null>(null);
  const blocks = useQuery(scheduleBlocksQueryOptions(schedule.id, page));
  const canStore = can(PERMISSIONS.scheduleBlocksStore);
  const canDestroy = can(PERMISSIONS.scheduleBlocksDestroy);

  const close = () => {
    setDialog(null);
  };

  // Eliminado el único bloqueo de una página que no es la primera, vuelve a la anterior (si no, "Esta página no existe.").
  const onBlockDeleted = () => {
    if (page > 1 && blocks.data?.items.length === 1) {
      setPage(page - 1);
    }

    close();
  };

  // El bloqueo ya no está (otra pestaña lo eliminó): aviso y la lista pedida de nuevo.
  const onBlockGone = (message: string) => {
    toast.show(message);
    afterBlocksChanged(queryClient, schedule.id);
    close();
  };

  const columns: Column<ScheduleBlock>[] = [
    {
      header: 'Período',
      cell: (block) => <span className="block-period">{formatBlockPeriod(block)}</span>,
    },
    {
      header: 'Motivo',
      cell: (block) => (
        <span className="text-break block-reason">
          {block.reason ?? <span className="text-secondary">—</span>}
        </span>
      ),
    },
  ];

  if (canDestroy) {
    columns.push({
      header: 'Acciones',
      cell: (block) => (
        <Button
          variant="link"
          className="p-0 block-delete"
          aria-label={`Eliminar el bloqueo ${formatBlockPeriod(block)}`}
          onClick={() => {
            setDialog({ type: 'delete', block });
          }}
        >
          Eliminar
        </Button>
      ),
    });
  }

  const actions = canStore ? (
    <Button
      variant="secondary"
      className="block-new"
      icon={<IconPlus size={18} className="me-2" aria-hidden="true" />}
      onClick={() => {
        setDialog({ type: 'new' });
      }}
    >
      Nuevo bloqueo
    </Button>
  ) : undefined;

  return (
    <Card title="Bloqueos" actions={actions} flush>
      <p className="text-secondary px-3 pt-3 mb-2 schedule-blocks-hint">
        Los que terminan desde hoy. En un bloqueo la agenda no recibe turnos (hora de Asunción).
      </p>
      {blocks.isPending ? (
        <Loading />
      ) : blocks.isError ? (
        <div className="px-3 pb-3">
          <Alert
            title={isApiError(blocks.error) ? userMessage(blocks.error) : MESSAGES.programming}
          />
        </div>
      ) : (
        <DataTable
          columns={columns}
          rows={blocks.data.items}
          rowKey={(block) => block.id}
          {...(blocks.data.pagination.last_page > 1 || page > 1
            ? { pagination: blocks.data.pagination, onPageChange: setPage }
            : {})}
          empty="La agenda no tiene bloqueos desde hoy."
          firstPageLink={
            <Button
              variant="link"
              className="p-0 align-baseline first-page-link"
              onClick={() => {
                setPage(1);
              }}
            >
              Ir a la página 1
            </Button>
          }
        />
      )}
      {dialog?.type === 'new' ? <NewBlockDialog schedule={schedule} onClose={close} /> : null}
      {dialog?.type === 'delete' ? (
        <DeleteBlockDialog
          schedule={schedule}
          block={dialog.block}
          onGone={onBlockGone}
          onDeleted={onBlockDeleted}
          onClose={close}
        />
      ) : null}
    </Card>
  );
}

/** Las claves del 422 que van a un campo del diálogo; las demás, a la alerta. */
const API_FIELDS: Record<string, BlockFormField> = {
  starts_at: 'fromDate',
  ends_at: 'toDate',
  reason: 'reason',
};

/** El orden de los campos en pantalla: el foco va al primero con error. */
const FIELD_ORDER: BlockFormField[] = ['fromDate', 'fromTime', 'toDate', 'toTime', 'reason'];

/**
 * "Nuevo bloqueo": desde y hasta (fecha y hora de Asunción, o "Todo el día") y el motivo, con las reglas de la API. La API
 * crea el bloqueo aunque haya turnos en ese horario (no lo rechaza ni pide confirmación): con el 201, si quedaron turnos
 * adentro, el diálogo pasa a mostrarlos para que se reprogramen o se cancelen.
 */
export function NewBlockDialog({
  schedule,
  onClose,
}: {
  schedule: ScheduleDetail;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const onScheduleGone = useScheduleGone(schedule.id);
  const [values, setValues] = useState<BlockFormValues>(() => emptyBlockForm(todayInAppZone()));
  const [errors, setErrors] = useState<BlockFormErrors>({});
  const [alert, setAlert] = useState<string | null>(null);
  const [created, setCreated] = useState<StoredScheduleBlock | null>(null);
  const fromDateRef = useRef<HTMLInputElement>(null);
  const fromTimeRef = useRef<HTMLInputElement>(null);
  const toDateRef = useRef<HTMLInputElement>(null);
  const toTimeRef = useRef<HTMLInputElement>(null);
  const reasonRef = useRef<HTMLTextAreaElement>(null);

  const focusField = (field: BlockFormField) => {
    const target = {
      fromDate: fromDateRef,
      fromTime: fromTimeRef,
      toDate: toDateRef,
      toTime: toTimeRef,
      reason: reasonRef,
    }[field];
    target.current?.focus();
  };

  const mutation = useMutation({
    mutationFn: (payload: ReturnType<typeof blockFormToPayload>) =>
      createScheduleBlock(schedule.id, payload),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterBlocksChanged(queryClient, schedule.id);
      toast.show(response.message, 'success');

      if (response.data.affected_appointments.length > 0) {
        setCreated(response.data);

        return;
      }

      onClose();
    },
    onError: (error) => {
      if (isApiError(error) && errorTreatment(error.errorCode) === 'validation') {
        const found: BlockFormErrors = {};
        const others: string[] = [];

        for (const [key, messages] of Object.entries(error.fieldErrors())) {
          const field = API_FIELDS[key];

          if (field === undefined) {
            others.push(messages.join(' '));
          } else {
            found[field] = [found[field], ...messages].filter(Boolean).join(' ');
          }
        }

        // El foco lo lleva ConfirmDialog al terminar la espera (al primer campo con error).
        setErrors(found);
        setAlert(others.length > 0 ? others.join(' ') : null);

        return;
      }

      // Un 404: la agenda se eliminó en otra pestaña (al listado). Lo demás (un 409, el servidor, la red), en la alerta,
      // con el formulario como estaba para probar de nuevo.
      setAlert(dialogError(error, onScheduleGone)?.message ?? null);
    },
  });

  const submit = () => {
    const found = blockFormErrors(values);
    setErrors(found);
    const first = FIELD_ORDER.find((field) => found[field] !== undefined);

    if (first !== undefined) {
      focusField(first);

      return;
    }

    if (single.tryStart()) {
      mutation.mutate(blockFormToPayload(values));
    }
  };

  const set = (field: BlockFormField) => (event: { target: { value: string } }) => {
    const value = event.target.value;
    setValues((current) => ({ ...current, [field]: value }));
  };

  if (created !== null) {
    return <AffectedAppointmentsDialog block={created} onClose={onClose} />;
  }

  return (
    <ConfirmDialog
      open
      size="md"
      tone="primary"
      title="Nuevo bloqueo"
      confirmLabel="Crear el bloqueo"
      busy={mutation.isPending}
      error={alert}
      onConfirm={submit}
      onClose={onClose}
    >
      <p>
        Agenda <strong>{schedule.name}</strong>. Hora de Asunción. Si ya hay turnos en ese horario,
        el bloqueo se crea igual y te mostramos cuáles quedaron adentro para que los reprogrames o
        los canceles.
      </p>
      <form
        className="row block-form"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          submit();
        }}
      >
        <div className="col-12">
          <CheckboxField
            name="all_day"
            label="Todo el día"
            checked={values.allDay}
            hint="Desde las 00:00 de la fecha desde hasta el final de la fecha hasta."
            onChange={(event) => {
              const checked = event.target.checked;
              setValues((current) => ({ ...current, allDay: checked }));
              // Las horas se ocultan: sus errores no quedan.
              setErrors((current) => {
                const rest = { ...current };
                delete rest.fromTime;
                delete rest.toTime;

                return rest;
              });
            }}
          />
        </div>
        <div className={values.allDay ? 'col-12' : 'col-7'}>
          <DateField
            ref={fromDateRef}
            name="block_from_date"
            label="Fecha desde"
            required
            value={values.fromDate}
            error={errors.fromDate}
            onChange={set('fromDate')}
          />
        </div>
        {values.allDay ? null : (
          <div className="col-5">
            <TimeField
              ref={fromTimeRef}
              name="block_from_time"
              label="Hora desde"
              required
              step={300}
              value={values.fromTime}
              error={errors.fromTime}
              onChange={set('fromTime')}
            />
          </div>
        )}
        <div className={values.allDay ? 'col-12' : 'col-7'}>
          <DateField
            ref={toDateRef}
            name="block_to_date"
            label="Fecha hasta"
            required
            value={values.toDate}
            error={errors.toDate}
            onChange={set('toDate')}
          />
        </div>
        {values.allDay ? null : (
          <div className="col-5">
            <TimeField
              ref={toTimeRef}
              name="block_to_time"
              label="Hora hasta"
              required
              step={300}
              value={values.toTime}
              error={errors.toTime}
              onChange={set('toTime')}
            />
          </div>
        )}
        <div className="col-12">
          <TextareaField
            ref={reasonRef}
            name="reason"
            label="Motivo"
            rows={2}
            hint="Opcional, hasta 255 caracteres."
            value={values.reason}
            error={errors.reason}
            onChange={set('reason')}
          />
        </div>
      </form>
    </ConfirmDialog>
  );
}

/** Quién es el turno: el paciente (con su dueño), el cliente o el contacto con su teléfono. */
function appointmentWho(appointment: AffectedAppointment): string {
  if (appointment.patient !== null) {
    return appointment.client === null
      ? appointment.patient.name
      : `${appointment.patient.name}, de ${appointment.client.display_name}`;
  }

  if (appointment.client !== null) {
    return appointment.client.display_name;
  }

  return [appointment.contact_name, appointment.contact_phone].filter(Boolean).join(', ');
}

/** "Miércoles 25/11/2026, 09:00 a 09:30", en Asunción. */
function appointmentWhen(appointment: AffectedAppointment): string {
  const start = inAppZone(appointment.starts_at);
  // getDay(): 0 = domingo; WEEKDAYS empieza el lunes (weekday 1 de la API).
  const weekday = WEEKDAYS[(start.getDay() + 6) % 7]?.label ?? '';

  return `${weekday} ${format(start, 'dd/MM/yyyy')}, ${formatTime(appointment.starts_at)} a ${formatTime(appointment.ends_at)}`;
}

/**
 * El bloqueo ya se creó y quedaron turnos adentro: la lista, con "Cerrar". La API no los cancela ni los mueve (siguen
 * dados); se reprograman o se cancelan desde el turno (el enlace a cada uno, desde la 3.4).
 */
function AffectedAppointmentsDialog({
  block,
  onClose,
}: {
  block: StoredScheduleBlock;
  onClose: () => void;
}) {
  const count = block.affected_appointments.length;
  const can = useCan();

  return (
    <ConfirmDialog
      open
      size="md"
      tone="primary"
      title="Bloqueo creado, con turnos adentro"
      confirmLabel="Cerrar"
      canConfirm={false}
      onConfirm={onClose}
      onClose={onClose}
    >
      <p>
        El bloqueo {formatBlockPeriod(block)} quedó creado.{' '}
        {count === 1
          ? 'Este turno quedó dentro del bloqueo: reprogramalo o cancelalo.'
          : 'Estos turnos quedaron dentro del bloqueo: reprogramalos o cancelalos.'}
      </p>
      <ul className="list-unstyled mb-0 block-affected">
        {block.affected_appointments.map((appointment) => (
          <li key={appointment.id} className="mb-2">
            <div className="fw-bold text-body">{appointmentWhen(appointment)}</div>
            <div className="text-break">
              {appointmentWho(appointment)} · <StatusLabel status={appointment.status} />
            </div>
            {can(PERMISSIONS.appointmentsShow) ? (
              <Link
                to="/turnos/$id"
                params={{ id: String(appointment.id) }}
                className="block-affected-link"
              >
                Ver el turno
              </Link>
            ) : null}
          </li>
        ))}
      </ul>
    </ConfirmDialog>
  );
}

/** Eliminar un bloqueo: la agenda vuelve a recibir turnos en ese horario. Un 404 (ya no está), aviso y la lista de nuevo. */
export function DeleteBlockDialog({
  schedule,
  block,
  onGone,
  onDeleted,
  onClose,
}: {
  schedule: ScheduleDetail;
  block: ScheduleBlock;
  onGone: (message: string) => void;
  /** Con el 200 (si no se pasa, `onClose`). */
  onDeleted?: () => void;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const [failure, setFailure] = useState<DialogFailure | null>(null);

  const mutation = useMutation({
    mutationFn: () => deleteScheduleBlock(block.id),
    onMutate: () => {
      setFailure(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterBlocksChanged(queryClient, schedule.id);
      toast.show(response.message, 'success');
      (onDeleted ?? onClose)();
    },
    onError: (error) => {
      setFailure(dialogError(error, onGone));
    },
  });

  return (
    <ConfirmDialog
      open
      title="Eliminar el bloqueo"
      confirmLabel="Eliminar el bloqueo"
      busy={mutation.isPending}
      error={failure?.message ?? null}
      canConfirm={failure?.final !== true}
      onConfirm={() => {
        if (single.tryStart()) {
          mutation.mutate();
        }
      }}
      onClose={onClose}
    >
      {failure?.final === true ? null : (
        <>
          ¿Querés eliminar el bloqueo <strong>{formatBlockPeriod(block)}</strong>
          {block.reason === null ? '' : ` (${block.reason})`} de {schedule.name}? La agenda vuelve a
          recibir turnos en ese horario.
        </>
      )}
    </ConfirmDialog>
  );
}
