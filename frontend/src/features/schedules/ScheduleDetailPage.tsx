import { IconEdit } from '@tabler/icons-react';
import { useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, type ReactNode } from 'react';
import { Card, ErrorPage } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime } from '../../lib/format/dates';
import { useCan } from '../auth/permissions';
import { scheduleDetailQueryOptions } from './api';
import { formatDayHours, formatSlot } from './form';
import { useScheduleListSearch, withScheduleList } from './navigation';
import { ScheduleActions } from './ScheduleActions';
import { ScheduleColor, ScheduleInactiveBadge } from './ScheduleBadges';
import { WEEKDAYS, type ScheduleDetail } from './types';

/**
 * /configuracion/agendas/$id (3.1a, solo el administrador): los datos, el horario semanal (de lunes a domingo, con sus
 * tramos), el alta y la última modificación; "Editar", inactivar o activar y eliminar (`ScheduleActions`).
 */
export function ScheduleDetailPage({ id }: { id: number }) {
  const { data: schedule } = useSuspenseQuery(scheduleDetailQueryOptions(id));
  const can = useCan();
  const listSearch = useScheduleListSearch();

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/configuracion">Configuración</Link>
              {' / '}
              <Link to="/configuracion/agendas" search={listSearch}>
                Agendas
              </Link>
            </div>
            <h2 className="page-title d-flex gap-2 align-items-center flex-wrap">
              <ScheduleColor color={schedule.color} />
              <span className="schedule-title">{schedule.name}</span>
              <ScheduleInactiveBadge schedule={schedule} />
            </h2>
          </div>
          <div className="col-auto d-flex gap-2 flex-wrap schedule-actions">
            {can(PERMISSIONS.schedulesUpdate) ? (
              <Link
                to="/configuracion/agendas/$id/editar"
                params={{ id: String(schedule.id) }}
                state={withScheduleList(listSearch)}
                className="btn btn-primary schedule-edit"
              >
                <IconEdit size={18} className="me-2" aria-hidden="true" />
                Editar
              </Link>
            ) : null}
            <ScheduleActions schedule={schedule} />
          </div>
        </div>
      </div>
      <div className="row row-cards">
        <div className="col-lg-6">
          <ScheduleData schedule={schedule} />
        </div>
        <div className="col-lg-6">
          <ScheduleWeek schedule={schedule} />
        </div>
      </div>
    </>
  );
}

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

function ScheduleData({ schedule }: { schedule: ScheduleDetail }) {
  const rows: [string, ReactNode][] = [
    ['Nombre', schedule.name],
    ['Sucursal', schedule.branch.name],
    ['Veterinario', orDash(schedule.veterinarian?.name)],
    ['Servicio', orDash(schedule.service?.name)],
    ['Duración del turno', formatSlot(schedule.slot_minutes)],
    [
      'Color',
      schedule.color === null ? (
        '—'
      ) : (
        <span className="d-inline-flex align-items-center gap-2">
          <ScheduleColor color={schedule.color} />
          {schedule.color}
        </span>
      ),
    ],
    ['Estado', schedule.is_active ? 'Activa' : 'Inactivada'],
    ['Alta', orDash(formatDateTime(schedule.created_at))],
    ['Última modificación', orDash(formatDateTime(schedule.updated_at))],
  ];

  return (
    <Card title="Datos de la agenda">
      <dl className="row mb-0 schedule-data">
        {rows.map(([label, value]) => (
          <Fragment key={label}>
            <dt className="col-5">{label}</dt>
            <dd className="col-7 text-break">{value}</dd>
          </Fragment>
        ))}
      </dl>
    </Card>
  );
}

/** El horario semanal: una fila por día, de lunes a domingo, con sus tramos ("Sin atención" si no tiene). */
function ScheduleWeek({ schedule }: { schedule: ScheduleDetail }) {
  return (
    <Card title="Horario semanal">
      {schedule.hours.length === 0 ? (
        <p className="text-secondary schedule-no-hours">
          La agenda no tiene horario: no recibe turnos hasta que se le cargue uno.
        </p>
      ) : null}
      <dl className="row mb-0 schedule-week">
        {WEEKDAYS.map((day) => {
          const hours = formatDayHours(schedule.hours, day.value);

          return (
            <Fragment key={day.value}>
              <dt className="col-5">{day.label}</dt>
              <dd className={`col-7 schedule-day-${String(day.value)}`}>
                {hours ?? <span className="text-secondary">Sin atención</span>}
              </dd>
            </Fragment>
          );
        })}
      </dl>
    </Card>
  );
}

/** La página de no encontrado del detalle y la edición (un id inválido o el 404 de la API), dentro del layout. */
export function ScheduleNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos esta agenda"
      message="La agenda no existe. Buscala en el listado."
      action={
        <Link to="/configuracion/agendas" className="btn btn-primary">
          Volver a las agendas
        </Link>
      }
    />
  );
}
