import { IconAlertTriangle, IconLock } from '@tabler/icons-react';
import { useQuery, useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, type ReactNode } from 'react';
import { Alert, Card, ErrorPage } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime, formatTime } from '../../lib/format/dates';
import { useCan } from '../auth/permissions';
import { appointmentDetailQueryOptions, rangeBlocksQueryOptions } from './api';
import { useCanEditAppointment } from './agenda-shared';
import { AppointmentEditLinks } from './AppointmentLinks';
import { dayTitle, wallDay } from './dates';
import { appointmentWho, isInBlock } from './grid';
import { durationText, minutesBetween } from './times';
import type { AppointmentDetail } from './types';

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

/** "17/11/2026 09:12, por Ana Gómez" (las horas de los estados). */
function whenBy(at: string | null, by?: { name: string } | null): string {
  const when = formatDateTime(at);

  if (when === '') {
    return '';
  }

  return by === null || by === undefined ? when : `${when}, por ${by.name}`;
}

/**
 * /turnos/$id (3.4, los tres roles): la fecha, la hora y la duración; la agenda, la sucursal y el veterinario; el paciente y el
 * cliente (enlaces) o el contacto; el servicio, el origen, el motivo y las notas; el estado, "Sobreturno" y las horas de cada
 * estado; quién lo creó. "Editar" y "Reprogramar", con `appointments.update` en un turno a confirmar o confirmado. Las
 * acciones de estado llegan en la 3.5. Un turno dentro de un bloqueo de su agenda (la marca de la 3.2) lo avisa, con
 * "Reprogramar" a la mano.
 */
export function AppointmentDetailPage({ id }: { id: number }) {
  const { data: appointment } = useSuspenseQuery(appointmentDetailQueryOptions(id));
  const can = useCan();
  const canEdit = useCanEditAppointment();
  const day = wallDay(appointment.starts_at);
  const blocksQuery = useQuery({
    ...rangeBlocksQueryOptions(appointment.schedule.id, { from: day, to: day }, false),
    enabled: can(PERMISSIONS.scheduleBlocksIndex),
  });
  const inBlock = blocksQuery.data !== undefined && isInBlock(appointment, blocksQuery.data);
  const editable = canEdit(appointment);

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/agenda" search={{ fecha: day, agenda: appointment.schedule.id }}>
                Agenda
              </Link>
              {' / '}
              <Link to="/turnos" search={{ desde: day, hasta: day }}>
                Turnos
              </Link>
            </div>
            <h2 className="page-title appointment-title">
              {dayTitle(day)}, {formatTime(appointment.starts_at)}
            </h2>
          </div>
          <div className="col-12 col-md-auto d-flex gap-2 flex-wrap appointment-actions">
            <AppointmentEditLinks appointment={appointment} />
          </div>
        </div>
      </div>
      {inBlock ? (
        <div className="mb-3 appointment-in-block">
          <Alert tone="warning" title="Dentro de un bloqueo">
            <IconLock size={16} className="me-1" aria-hidden="true" />
            El turno quedó dentro de un bloqueo de la agenda:{' '}
            {editable ? 'reprogramalo o cancelalo.' : 'hay que reprogramarlo o cancelarlo.'}
          </Alert>
        </div>
      ) : null}
      {appointment.patient === null ? (
        <div className="mb-3 appointment-no-patient">
          <Alert tone="info" title="Para fichar, cargá el paciente.">
            {appointment.client === null
              ? 'El turno es de un contacto, sin cliente: cargá el cliente y su paciente, y después editá el turno.'
              : 'El turno es del cliente, sin paciente: cargá el paciente y después editá el turno.'}
            <div className="d-flex gap-2 flex-wrap mt-2">
              {appointment.client === null && can(PERMISSIONS.clientsStore) ? (
                <Link to="/clientes/nuevo" className="btn btn-sm btn-outline-primary">
                  Nuevo cliente
                </Link>
              ) : null}
              {appointment.client !== null && can(PERMISSIONS.patientsStore) ? (
                <Link
                  to="/clientes/$id/pacientes/nuevo"
                  params={{ id: String(appointment.client.id) }}
                  className="btn btn-sm btn-outline-primary"
                >
                  Nuevo paciente
                </Link>
              ) : null}
              {editable ? (
                <Link
                  to="/turnos/$id/editar"
                  params={{ id: String(appointment.id) }}
                  search={{ reprogramar: undefined }}
                  className="btn btn-sm btn-outline-primary"
                >
                  Editar el turno
                </Link>
              ) : null}
            </div>
          </Alert>
        </div>
      ) : null}
      <div className="row row-cards">
        <div className="col-lg-7">
          <AppointmentData appointment={appointment} />
        </div>
        <div className="col-lg-5">
          <AppointmentStatusCard appointment={appointment} />
        </div>
      </div>
    </>
  );
}

function DataList({ rows, className }: { rows: [string, ReactNode][]; className: string }) {
  return (
    <dl className={`row mb-0 ${className}`}>
      {rows.map(([label, value]) => (
        <Fragment key={label}>
          <dt className="col-5">{label}</dt>
          <dd className="col-7 text-break">{value}</dd>
        </Fragment>
      ))}
    </dl>
  );
}

function AppointmentData({ appointment }: { appointment: AppointmentDetail }) {
  const can = useCan();
  const minutes = minutesBetween(appointment.starts_at, appointment.ends_at);
  const patient = appointment.patient;
  const client = appointment.client;
  const rows: [string, ReactNode][] = [
    ['Fecha', dayTitle(wallDay(appointment.starts_at))],
    [
      'Horario',
      `${formatTime(appointment.starts_at)} a ${formatTime(appointment.ends_at)} (${durationText(minutes)})`,
    ],
    ['Agenda', appointment.schedule.name],
    ['Sucursal', appointment.branch.name],
    ['Veterinario', orDash(appointment.veterinarian?.name)],
  ];

  if (patient !== null) {
    rows.push([
      'Paciente',
      can(PERMISSIONS.patientsShow) ? (
        <Link to="/pacientes/$id" params={{ id: String(patient.id) }}>
          {patient.name} ({patient.code})
        </Link>
      ) : (
        `${patient.name} (${patient.code})`
      ),
    ]);
  }

  if (client !== null) {
    rows.push([
      'Cliente',
      can(PERMISSIONS.clientsShow) ? (
        <Link to="/clientes/$id" params={{ id: String(client.id) }}>
          {client.display_name}
        </Link>
      ) : (
        client.display_name
      ),
    ]);
  }

  if (patient === null && client === null) {
    rows.push(['Contacto', appointmentWho(appointment)]);
    rows.push(['Teléfono de contacto', orDash(appointment.contact_phone)]);
  }

  rows.push(
    ['Servicio', orDash(appointment.service?.name)],
    ['Origen', appointment.source.label],
    ['Motivo', orDash(appointment.reason)],
    ['Notas', orDash(appointment.notes)],
  );

  return (
    <Card title="Datos del turno">
      <DataList rows={rows} className="appointment-data" />
    </Card>
  );
}

function AppointmentStatusCard({ appointment }: { appointment: AppointmentDetail }) {
  const rows: [string, ReactNode][] = [['Estado', appointment.status.label]];
  const optional: [string, string][] = [
    ['Confirmado', whenBy(appointment.confirmed_at)],
    ['Fichado', whenBy(appointment.checked_in_at)],
    ['Atendido', whenBy(appointment.attended_at)],
    ['Ausente', whenBy(appointment.no_show_at)],
    ['Cancelado', whenBy(appointment.cancelled_at, appointment.cancelled_by)],
    ['Motivo de la cancelación', appointment.cancellation_reason ?? ''],
  ];

  for (const [label, value] of optional) {
    if (value !== '') {
      rows.push([label, value]);
    }
  }

  rows.push(['Creado', orDash(whenBy(appointment.created_at, appointment.created_by))]);

  return (
    <Card title="Estado">
      {appointment.is_overbooked ? (
        <p className="fw-bold mb-2 appointment-overbooked">
          <IconAlertTriangle size={16} className="me-1" aria-hidden="true" />
          Sobreturno
        </p>
      ) : null}
      <DataList rows={rows} className="appointment-status" />
    </Card>
  );
}

export function AppointmentNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos este turno"
      message="El turno no existe. Buscalo en la agenda o en la lista de turnos."
      action={
        <Link to="/agenda" className="btn btn-primary">
          Volver a la agenda
        </Link>
      }
    />
  );
}
