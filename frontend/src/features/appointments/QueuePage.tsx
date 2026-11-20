import { useQueries, useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { useEffect, useRef, useState, type FocusEvent, type RefObject } from 'react';
import { Alert, Card, CheckboxField, DateField, Loading } from '../../components/ui';
import { isApiError } from '../../lib/api/errors';
import { formatTime, todayInAppZone } from '../../lib/format/dates';
import { useAutoRefreshPaused } from '../../lib/hooks/auto-refresh';
import { useNow } from '../../lib/hooks/use-now';
import { meQueryOptions } from '../auth/api';
import { AppointmentActions } from './AppointmentActions';
import {
  AgendaHeader,
  AgendaStepper,
  AgendaViewSwitch,
  AppointmentFlags,
  RangeLoadError,
} from './AgendaShared';
import {
  appointmentQueueQueryOptions,
  rangeBlocksQueryOptions,
  type AppointmentQueue,
} from './api';
import { addDaysToDay, dayTitle, isAgendaDay } from './dates';
import {
  buildQueue,
  hasOwnVeterinarian,
  patientKind,
  QUEUE_ACTIONS,
  QUEUE_EMPTY_MESSAGE,
  QUEUE_NO_BRANCH_MESSAGE,
  QUEUE_TRUNCATED_MESSAGE,
  QUEUE_UNASSIGNED_NOTE,
  queueChanges,
  queueFilters,
  queueTitleAnnouncement,
  type QueueRow,
} from './queue';
import type { QueueSearch } from './search';
import { statusLook } from './status-icons';
import { StatusLabel } from './StatusLabel';
import type { AppointmentDetail, QueueAppointment } from './types';

/**
 * /agenda/cola (3.7, pregunta 11): la cola del día de la sucursal actual de la barra, en el orden de la API (la hora del
 * turno), sin los cancelados. Quien tiene ficha de veterinario ve por defecto sus turnos y los de las agendas sin veterinario
 * (lo decide la API: `meta.includes_unassigned`) y, con "Ver toda la sucursal", `all=1`; la recepción y el administrador, toda
 * la sucursal. En cada fila, fichar, atendido y ausente (las de `transitions.ts` para el estado y el rol) y "Ver el turno".
 * Se actualiza cada 60 s con la pausa y al volver a la pestaña; lo que cambió se anuncia sin leer la cola entera.
 */
export function QueuePage() {
  const search: QueueSearch = useSearch({ from: '/_sesion/agenda/cola' });
  const navigate = useNavigate({ from: '/agenda/cola' });
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branchId = me?.current_branch?.id ?? null;
  const now = useNow();
  const filters = queueFilters(search, me, now);
  const day = filters.date;
  const paused = useAutoRefreshPaused();
  const isVeterinarian = hasOwnVeterinarian(me);

  const queueQuery = useQuery({
    ...appointmentQueueQueryOptions(branchId ?? 0, filters, paused),
    enabled: branchId !== null,
  });
  const data = queueQuery.data;
  const items = data?.items ?? [];
  const scheduleIds = [...new Set(items.map((item) => item.schedule.id))].sort((a, b) => a - b);
  // Los bloqueos del día de cada agenda de la cola (la marca "Dentro de un bloqueo"), con la clave del día: si la agenda del
  // día ya los pidió, no se vuelven a pedir.
  const blocks = useQueries({
    queries: scheduleIds.map((scheduleId) =>
      rangeBlocksQueryOptions(scheduleId, { from: day, to: day }, paused),
    ),
  });
  const blocksBySchedule = new Map(
    scheduleIds.map((scheduleId, index) => [scheduleId, blocks[index]?.data ?? []]),
  );
  const rows = buildQueue(items, blocksBySchedule, day, now);

  // Los cambios propios (el id y el estado que devolvió la acción): su aviso ya lo leyó el toast.
  const [own, setOwn] = useState<ReadonlyMap<number, string>>(() => new Map());
  // El anuncio (role="status"): al entrar o cambiar de día o de vista, el día y la cuenta; en una actualización, solo lo que
  // cambió (nada si no cambió nada: la misma respuesta conserva su objeto y no se compara).
  const announceKey = [day, filters.all, branchId].join('|');
  const [seen, setSeen] = useState<{ key: string; data: AppointmentQueue; text: string } | null>(
    null,
  );
  const ready = queueQuery.isSuccess && !queueQuery.isPlaceholderData;

  if (ready && data !== undefined && seen?.data !== data) {
    const text =
      seen?.key === announceKey
        ? (queueChanges(seen.data.items, data.items, own) ?? seen.text)
        : queueTitleAnnouncement(day, data.items.length);
    setSeen({ key: announceKey, data, text });
  }

  // El foco: si la fila que lo tenía sale de la cola (otra PC la canceló, o la acción respondió 404), pasa a la fila que
  // quedó en su lugar (o a la anterior, o al aviso sin turnos); nunca queda en el body.
  const listRef = useRef<HTMLOListElement>(null);
  const emptyRef = useRef<HTMLParagraphElement>(null);
  const lastFocus = useRef<{ id: number; index: number } | null>(null);

  useEffect(() => {
    const last = lastFocus.current;

    if (last === null || data === undefined || data.items.some((item) => item.id === last.id)) {
      return;
    }

    lastFocus.current = null;
    const active = document.activeElement;

    if (active !== null && active !== document.body) {
      return;
    }

    const titles = listRef.current?.querySelectorAll<HTMLElement>('.queue-row-title') ?? [];
    const next = titles[Math.min(last.index, titles.length - 1)];
    (next ?? emptyRef.current)?.focus();
  }, [data]);

  const onRowFocus = (event: FocusEvent<HTMLOListElement>) => {
    const row = (event.target as HTMLElement).closest<HTMLElement>('[data-queue-id]');

    if (row !== null) {
      lastFocus.current = {
        id: Number(row.dataset.queueId),
        index: Number(row.dataset.queueIndex),
      };
    }
  };

  const goToDay = (next: string) => {
    void navigate({ search: (previous) => ({ ...previous, fecha: next }) });
  };

  let content;

  if (branchId === null) {
    content = <QueueNotice text={QUEUE_NO_BRANCH_MESSAGE} />;
  } else if (queueQuery.isError && data === undefined) {
    const error = queueQuery.error;
    content =
      isApiError(error) && error.errorCode === 'APPOINTMENT_QUEUE_NO_BRANCH' ? (
        <QueueNotice text={error.message} />
      ) : (
        <RangeLoadError
          error={error}
          onRetry={() => {
            void queueQuery.refetch();
          }}
        />
      );
  } else if (data === undefined) {
    content = <Loading label="Cargando la cola…" />;
  } else if (rows.length === 0) {
    content = <QueueNotice text={QUEUE_EMPTY_MESSAGE} textRef={emptyRef} />;
  } else {
    content = (
      <ol
        ref={listRef}
        className="list-unstyled mb-0 queue-list"
        aria-label={`Cola del ${dayTitle(day).toLowerCase()}`}
        aria-busy={queueQuery.isPlaceholderData}
        onFocus={onRowFocus}
      >
        {rows.map((row, index) => (
          <QueueItem
            key={row.appointment.id}
            row={row}
            index={index}
            onChanged={(saved) => {
              setOwn((previous) => new Map(previous).set(saved.id, saved.status.value));
            }}
          />
        ))}
      </ol>
    );
  }

  return (
    <>
      <AgendaHeader
        title={`Cola del día: ${dayTitle(day)}`}
        announcement={seen?.text ?? ''}
        titleClassName="agenda-title queue-title"
      />
      <Card>
        <div className="row g-2 align-items-end agenda-toolbar">
          <div className="col-12 col-md-auto">
            <AgendaViewSwitch
              current="cola"
              day={day}
              agenda={undefined}
              veterinario={undefined}
              extra={{ cancelados: undefined, todos: search.todos }}
            />
          </div>
          <div className="col-12 col-md-auto">
            <AgendaStepper
              label="Día"
              previousLabel="Día anterior"
              nextLabel="Día siguiente"
              todayLabel="Hoy"
              onStep={(direction) => {
                goToDay(addDaysToDay(day, direction));
              }}
              onToday={() => {
                goToDay(todayInAppZone());
              }}
            />
          </div>
          <div className="col-12 col-sm-6 col-md-auto agenda-date">
            <DateField
              label="Fecha"
              value={day}
              onChange={(event) => {
                if (isAgendaDay(event.target.value)) {
                  goToDay(event.target.value);
                }
              }}
            />
          </div>
          {isVeterinarian ? (
            <div className="col-12 col-sm-6 col-md-auto">
              <CheckboxField
                label="Ver toda la sucursal"
                hint="Sin marcar, tus turnos y los de las agendas sin veterinario."
                checked={search.todos === 1}
                onChange={(event) => {
                  void navigate({
                    search: (previous) => ({
                      ...previous,
                      todos: event.target.checked ? 1 : undefined,
                    }),
                  });
                }}
              />
            </div>
          ) : null}
        </div>
        {data?.meta.includes_unassigned === true ? (
          <p className="text-secondary small mb-0 mt-2 queue-unassigned">{QUEUE_UNASSIGNED_NOTE}</p>
        ) : null}
      </Card>
      {data?.meta.truncated === true ? (
        <div className="mt-3 queue-truncated">
          <Alert tone="warning" title={QUEUE_TRUNCATED_MESSAGE}>
            Para ver los demás, usá la lista de turnos.
          </Alert>
        </div>
      ) : null}
      <div className="mt-3">{content}</div>
    </>
  );
}

/** Un aviso en lugar de la cola (sin sucursal, sin turnos): enfocable, para que el foco no se pierda. */
function QueueNotice({
  text,
  textRef,
}: {
  text: string;
  textRef?: RefObject<HTMLParagraphElement | null>;
}) {
  return (
    <Card>
      <p className="text-secondary mb-0 queue-empty" ref={textRef} tabIndex={-1}>
        {text}
      </p>
    </Card>
  );
}

/** Quién viene: el paciente, el cliente o el contacto. */
function queueWho(appointment: QueueAppointment): string {
  return (
    appointment.patient?.name ?? appointment.client?.display_name ?? appointment.contact_name ?? ''
  );
}

/**
 * Una fila de la cola: la hora y quién (el título, que recibe el foco cuando la acción saca el botón), el paciente con su
 * especie y raza, el cliente, el servicio, el veterinario y la agenda, el estado con la espera, las marcas y las acciones.
 * Con el 200 de una acción, la fila muestra el turno nuevo hasta que llega la cola actualizada.
 */
function QueueItem({
  row,
  index,
  onChanged,
}: {
  row: QueueRow;
  index: number;
  onChanged: (saved: AppointmentDetail) => void;
}) {
  const titleRef = useRef<HTMLHeadingElement>(null);
  const [changed, setChanged] = useState<{
    from: QueueAppointment;
    value: QueueAppointment;
  } | null>(null);
  const appointment =
    changed !== null && changed.from === row.appointment ? changed.value : row.appointment;
  const look = statusLook(appointment.status.value);
  const kind = patientKind(appointment);
  const who = queueWho(appointment);
  const titleId = `queue-row-${String(appointment.id)}`;
  const details = [
    appointment.patient !== null && appointment.client !== null
      ? `de ${appointment.client.display_name}`
      : '',
    appointment.patient === null && appointment.client === null
      ? (appointment.contact_phone ?? '')
      : '',
  ].filter((part) => part !== '');
  const where = [
    appointment.service?.name ?? '',
    appointment.veterinarian?.name ?? 'Sin veterinario',
    appointment.schedule.name,
  ].filter((part) => part !== '');

  return (
    <li
      className={`card mb-2 queue-row${look === null ? '' : ` queue-row-${look}`}`}
      data-queue-id={appointment.id}
      data-queue-index={index}
      aria-labelledby={titleId}
    >
      <div className="card-body p-3 d-flex flex-column flex-md-row gap-2 gap-md-3">
        <div className="queue-row-main">
          <h3 className="card-title mb-1 queue-row-title" id={titleId} tabIndex={-1} ref={titleRef}>
            <span className="queue-row-time">{formatTime(appointment.starts_at)}</span>{' '}
            <span className="queue-row-who">{who}</span>
          </h3>
          {kind !== '' || details.length > 0 ? (
            <div className="text-secondary queue-row-patient">
              {[kind, ...details].filter((part) => part !== '').join(' · ')}
            </div>
          ) : null}
          <div className="queue-row-where">{where.join(' · ')}</div>
          <div className="queue-row-status">
            <StatusLabel status={appointment.status} />
            {row.waiting !== null && changed?.from !== row.appointment ? (
              <span className="ms-2 queue-row-waiting">{row.waiting}</span>
            ) : null}
          </div>
          <AppointmentFlags appointment={appointment} inBlock={row.inBlock} />
        </div>
        <div className="queue-row-actions d-flex flex-column gap-2">
          <AppointmentActions
            appointment={appointment}
            only={QUEUE_ACTIONS}
            focusAfter={titleRef}
            size="sm"
            withPatientLinks
            onChanged={(saved) => {
              setChanged({
                from: row.appointment,
                value: {
                  ...row.appointment,
                  status: saved.status,
                  checked_in_at: saved.checked_in_at,
                },
              });
              onChanged(saved);
            }}
          />
          <div>
            <Link
              to="/turnos/$id"
              params={{ id: String(appointment.id) }}
              className="btn btn-sm btn-outline-primary queue-row-detail"
            >
              Ver el turno
            </Link>
          </div>
        </div>
      </div>
    </li>
  );
}
