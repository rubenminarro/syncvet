import {
  IconAlertTriangle,
  IconChevronLeft,
  IconChevronRight,
  IconClock,
  IconLock,
} from '@tabler/icons-react';
import { Link } from '@tanstack/react-router';
import { useEffect, useRef, type KeyboardEvent, type ReactNode, type RefObject } from 'react';
import { Alert, Button, Card, CheckboxField } from '../../components/ui';
import { isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { formatTime } from '../../lib/format/dates';
import { dayTitle, wallDay, weekEndOf, weekStartOf } from './dates';
import { appointmentWho } from './grid';
import type { AgendaSearch } from './search';
import { STATUS_ICONS } from './status-icons';
import { MISSING_APPOINTMENTS_MESSAGE } from './agenda-shared';
import type { Appointment } from './types';

/**
 * Los componentes que comparten el día (3.2), la semana y la lista (3.3): el título con el anuncio, el cambio de vista,
 * anterior y siguiente, los filtros, la falla de la carga, el aviso sin agendas con el foco, el aviso de los turnos que faltan,
 * las tarjetas y el panel de solo lectura del turno. Lo que no es un componente, en `agenda-shared.ts`.
 */

/** El router marca `active` todo enlace a /agenda (el día y la semana son la misma ruta): la vista la marca `current`. */
const NOT_ACTIVE = { className: '' };

export type AgendaViewName = 'dia' | 'semana' | 'lista';

/**
 * "Día", "Semana" y "Lista": pasar de una a otra conserva la fecha, la agenda y el veterinario (la sucursal es la actual de la
 * barra). A la lista va el día, o la semana de lunes a domingo; de la lista, el día `desde` (o hoy).
 */
export function AgendaViewSwitch({
  current,
  day,
  agenda,
  veterinario,
  extra,
}: {
  current: AgendaViewName;
  day: string;
  agenda: number | undefined;
  veterinario: number | undefined;
  /** Lo que el día y la semana conservan entre ellas (cancelados, todos). */
  extra?: Pick<AgendaSearch, 'cancelados' | 'todos'>;
}) {
  const kept = { agenda, veterinario, cancelados: extra?.cancelados, todos: extra?.todos };
  const range =
    current === 'semana'
      ? { desde: weekStartOf(day), hasta: weekEndOf(day) }
      : { desde: day, hasta: day };
  const className = (view: AgendaViewName) =>
    `btn ${view === current ? 'btn-primary' : 'btn-outline-primary'} agenda-view-${view}`;
  const ariaCurrent = (view: AgendaViewName) => (view === current ? ('page' as const) : undefined);

  return (
    <div className="btn-group w-100 agenda-views" role="group" aria-label="Vista">
      <Link
        to="/agenda"
        search={{ ...kept, vista: undefined, fecha: day }}
        className={className('dia')}
        aria-current={ariaCurrent('dia')}
        activeProps={NOT_ACTIVE}
      >
        Día
      </Link>
      <Link
        to="/agenda"
        search={{ ...kept, vista: 'semana', fecha: day }}
        className={className('semana')}
        aria-current={ariaCurrent('semana')}
        activeProps={NOT_ACTIVE}
      >
        Semana
      </Link>
      <Link
        to="/turnos"
        search={{ ...range, agenda, veterinario }}
        className={className('lista')}
        aria-current={ariaCurrent('lista')}
        activeProps={NOT_ACTIVE}
      >
        Lista
      </Link>
    </div>
  );
}

/** El título de la página y la región que anuncia lo que cambió (`role="status"`, siempre en el DOM). */
export function AgendaHeader({
  title,
  announcement,
  titleClassName = 'agenda-title',
}: {
  title: string;
  announcement: string;
  titleClassName?: string;
}) {
  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">Agenda</div>
            <h2 className={`page-title ${titleClassName}`.trim()}>{title}</h2>
          </div>
        </div>
      </div>
      <div className="visually-hidden" role="status" aria-live="polite">
        {announcement}
      </div>
    </>
  );
}

/** Anterior, el de hoy y siguiente (el día de a uno, la semana de a siete). */
export function AgendaStepper({
  label,
  previousLabel,
  nextLabel,
  todayLabel,
  onStep,
  onToday,
}: {
  /** "Día" o "Semana". */
  label: string;
  previousLabel: string;
  nextLabel: string;
  todayLabel: string;
  onStep: (direction: -1 | 1) => void;
  onToday: () => void;
}) {
  return (
    <div className="btn-group w-100" role="group" aria-label={label}>
      <Button
        variant="secondary"
        className="agenda-previous"
        aria-label={previousLabel}
        onClick={() => {
          onStep(-1);
        }}
      >
        <IconChevronLeft size={18} aria-hidden="true" />
      </Button>
      <Button variant="secondary" className="agenda-today" onClick={onToday}>
        {todayLabel}
      </Button>
      <Button
        variant="secondary"
        className="agenda-next"
        aria-label={nextLabel}
        onClick={() => {
          onStep(1);
        }}
      >
        <IconChevronRight size={18} aria-hidden="true" />
      </Button>
    </div>
  );
}

/** La falla del pedido de los turnos (sin datos anteriores): el mensaje y "Probá de nuevo". */
export function RangeLoadError({ error, onRetry }: { error: unknown; onRetry: () => void }) {
  return (
    <Alert title={isApiError(error) ? userMessage(error) : MESSAGES.programming}>
      <Button variant="secondary" onClick={onRetry}>
        Probá de nuevo
      </Button>
    </Alert>
  );
}

/** El aviso de un día o una semana sin agendas que mostrar: enfocable, para que el teclado no se pierda. */
export function AgendaEmpty({
  emptyRef,
  text,
}: {
  emptyRef: RefObject<HTMLParagraphElement | null>;
  text: string;
}) {
  return (
    <Card>
      <p className="text-secondary mb-0 agenda-empty" ref={emptyRef} tabIndex={-1}>
        {text}
      </p>
    </Card>
  );
}

/**
 * Lo que va debajo de los filtros (la grilla, la lista o el aviso). Sin agendas que mostrar no hay grilla: si el foco venía
 * de ella (Av Pág), pasa al aviso, que sigue respondiendo a Re Pág y Av Pág; nunca se pierde en el body.
 */
export function AgendaContent({
  emptyShown,
  emptyRef,
  onStep,
  children,
}: {
  emptyShown: boolean;
  emptyRef: RefObject<HTMLParagraphElement | null>;
  onStep: (direction: -1 | 1) => void;
  children: ReactNode;
}) {
  // ¿El foco estaba en la grilla (o en el aviso)? Para no perderlo al cambiar de día o de semana con el teclado.
  const keyboardInContent = useRef(false);

  useEffect(() => {
    if (emptyShown && keyboardInContent.current && document.activeElement === document.body) {
      emptyRef.current?.focus();
    }
  }, [emptyShown, emptyRef]);

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.defaultPrevented || event.target !== emptyRef.current) {
      return;
    }

    if (event.key === 'PageUp' || event.key === 'PageDown') {
      event.preventDefault();
      onStep(event.key === 'PageUp' ? -1 : 1);
    }
  };

  return (
    <div
      className="mt-3"
      onKeyDown={onKeyDown}
      onFocus={() => {
        keyboardInContent.current = true;
      }}
      onBlur={(event) => {
        const next = event.relatedTarget;

        if (next !== null && !event.currentTarget.contains(next)) {
          keyboardInContent.current = false;
        }
      }}
    >
      {children}
    </div>
  );
}

/** "Mostrar los cancelados" y, para el veterinario, "Ver toda la sucursal". */
export function AgendaFlags({
  showCancelled,
  todos,
  isVeterinarian,
  onCancelled,
  onTodos,
}: {
  showCancelled: boolean;
  todos: boolean;
  isVeterinarian: boolean;
  onCancelled: (checked: boolean) => void;
  onTodos: (checked: boolean) => void;
}) {
  return (
    <>
      <CheckboxField
        label="Mostrar los cancelados"
        checked={showCancelled}
        onChange={(event) => {
          onCancelled(event.target.checked);
        }}
      />
      {isVeterinarian ? (
        <CheckboxField
          label="Ver toda la sucursal"
          hint="Sin marcar, tus agendas y las que no tienen veterinario."
          checked={todos}
          onChange={(event) => {
            onTodos(event.target.checked);
          }}
        />
      ) : null}
    </>
  );
}

/** El aviso de los turnos que faltan, si `total` supera lo traído. */
export function MissingAppointments({
  range,
  what,
}: {
  range: { items: readonly unknown[]; total: number } | undefined;
  /** "del día" o "de la semana". */
  what: string;
}) {
  if (range === undefined || range.total <= range.items.length) {
    return null;
  }

  return (
    <div className="mt-3 agenda-missing">
      <Alert tone="warning" title={MISSING_APPOINTMENTS_MESSAGE}>
        Se muestran {range.items.length} de los {range.total} turnos {what}.
      </Alert>
    </div>
  );
}

/** La hora, quién, el estado (ícono y texto), el servicio y las marcas: las tarjetas de la semana en el celular y de la lista. */
export function AppointmentSummary({
  appointment,
  inBlock,
}: {
  appointment: Appointment;
  inBlock: boolean;
}) {
  const StatusIcon = STATUS_ICONS[appointment.status.value] ?? IconClock;

  return (
    <>
      <span className="agenda-item-line">
        <span className="agenda-item-time">
          {formatTime(appointment.starts_at)}–{formatTime(appointment.ends_at)}
        </span>{' '}
        <strong>{appointmentWho(appointment)}</strong>
      </span>
      <span className="agenda-item-line">
        <StatusIcon size={14} className="me-1" aria-hidden="true" />
        {appointment.status.label}
        {appointment.service === null ? '' : ` · ${appointment.service.name}`}
      </span>
      <AppointmentFlags appointment={appointment} inBlock={inBlock} />
    </>
  );
}

/** "Dentro de un bloqueo" y "Sobreturno", con texto e ícono (no solo color). */
export function AppointmentFlags({
  appointment,
  inBlock,
}: {
  appointment: Appointment;
  inBlock: boolean;
}) {
  if (!appointment.is_overbooked && !inBlock) {
    return null;
  }

  return (
    <span className="agenda-item-line agenda-item-flag">
      {inBlock ? (
        <span className="agenda-item-block-flag me-2">
          <IconLock size={14} className="me-1" aria-hidden="true" />
          Dentro de un bloqueo
        </span>
      ) : null}
      {appointment.is_overbooked ? (
        <span>
          <IconAlertTriangle size={14} className="me-1" aria-hidden="true" />
          Sobreturno
        </span>
      ) : null}
    </span>
  );
}

/** Los datos del turno, de solo lectura (desde la 3.2; el detalle con las acciones llega en la 3.4). */
export function AppointmentPanel({
  appointment,
  inBlock,
  headingRef,
  onClose,
}: {
  appointment: Appointment;
  inBlock: boolean;
  headingRef: RefObject<HTMLHeadingElement | null>;
  onClose: () => void;
}) {
  const rows: [string, string][] = [
    ['Fecha', dayTitle(wallDay(appointment.starts_at))],
    ['Horario', `${formatTime(appointment.starts_at)} a ${formatTime(appointment.ends_at)}`],
    ['Agenda', appointment.schedule.name],
    ['Quién', appointmentWho(appointment)],
    ['Teléfono de contacto', appointment.contact_phone ?? ''],
    ['Servicio', appointment.service?.name ?? ''],
    ['Veterinario', appointment.veterinarian?.name ?? ''],
    ['Estado', appointment.status.label],
    ['Origen', appointment.source.label],
    ['Motivo', appointment.reason ?? ''],
  ];

  return (
    <section className="card agenda-panel" aria-labelledby="agenda-panel-title">
      <div className="card-header">
        <h3 className="card-title" id="agenda-panel-title" tabIndex={-1} ref={headingRef}>
          Turno de las {formatTime(appointment.starts_at)}
        </h3>
        <div className="card-actions">
          <Button variant="secondary" onClick={onClose}>
            Cerrar
          </Button>
        </div>
      </div>
      <div className="card-body">
        {inBlock ? (
          <Alert tone="warning" title="Dentro de un bloqueo">
            El turno quedó dentro de un bloqueo de la agenda: reprogramalo o cancelalo.
          </Alert>
        ) : null}
        {appointment.is_overbooked ? <p className="mb-2 fw-bold">Sobreturno</p> : null}
        <dl className="row mb-0">
          {rows
            .filter(([, value]) => value !== '')
            .map(([label, value]) => (
              <div key={label} className="col-12 col-md-6 d-flex gap-2">
                <dt className="text-secondary">{label}:</dt>
                <dd className="mb-1">{value}</dd>
              </div>
            ))}
        </dl>
      </div>
    </section>
  );
}
