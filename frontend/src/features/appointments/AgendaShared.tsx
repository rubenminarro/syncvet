import { IconAlertTriangle, IconClock, IconLock } from '@tabler/icons-react';
import { Link } from '@tanstack/react-router';
import type { RefObject } from 'react';
import { Alert, Button, CheckboxField } from '../../components/ui';
import { formatTime } from '../../lib/format/dates';
import { dayTitle, wallDay, weekEndOf, weekStartOf } from './dates';
import { appointmentWho } from './grid';
import type { AgendaSearch } from './search';
import { STATUS_ICONS } from './status-icons';
import { MISSING_APPOINTMENTS_MESSAGE } from './agenda-shared';
import type { Appointment } from './types';

/**
 * Lo que comparten el día (3.2), la semana y la lista (3.3): el cambio de vista, las agendas de la sucursal con la vista por
 * defecto del veterinario, los filtros, el aviso de los turnos que faltan y el panel de solo lectura del turno.
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
