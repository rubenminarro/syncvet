import { IconAlertTriangle, IconLock, IconRepeat } from '@tabler/icons-react';
import { useQuery, useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, useRef, type ReactNode } from 'react';
import { Alert, Card, ErrorPage } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime, formatTime } from '../../lib/format/dates';
import { useCan } from '../auth/permissions';
import {
  appointmentDetailQueryOptions,
  appointmentSeriesQueryOptions,
  rangeBlocksQueryOptions,
} from './api';
import { SeriesLink } from './AgendaShared';
import { seriesPosition } from './series';
import { useCanEditAppointment } from './agenda-shared';
import { AppointmentActions } from './AppointmentActions';
import { AppointmentEditLinks, PatientRequiredLinks } from './AppointmentLinks';
import { StatusLabel } from './StatusLabel';
import { statusLook } from './status-icons';
import { TRANSITIONS } from './transitions';
import { dayTitle, wallDay } from './dates';
import { appointmentWho, isInBlock } from './grid';
import { durationText, minutesBetween } from './times';
import type { AppointmentDetail } from './types';

/** El título como las tarjetas (3.5): el ausente, atenuado; el cancelado, atenuado y tachado (el estado, al lado, sin tachar). */
function lookClass(status: string): string | undefined {
  const look = statusLook(status);

  return look === null ? undefined : `agenda-item-${look}`;
}

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
  const titleRef = useRef<HTMLHeadingElement>(null);
  // El aviso del paciente, solo mientras se puede fichar (a confirmar o confirmado).
  const checkInFrom = TRANSITIONS.find((item) => item.name === 'check_in')?.from ?? [];
  const needsPatient =
    appointment.patient === null && checkInFrom.includes(appointment.status.value);
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
            <h2 className="page-title appointment-title" ref={titleRef} tabIndex={-1}>
              <span className={lookClass(appointment.status.value)}>
                {dayTitle(day)}, {formatTime(appointment.starts_at)}
              </span>{' '}
              <StatusLabel status={appointment.status} size={20} />
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
      {needsPatient ? (
        <div className="mb-3 appointment-no-patient">
          <Alert tone="info" title="Para fichar, cargá el paciente.">
            {appointment.client === null
              ? 'El turno es de un contacto, sin cliente: cargá el cliente y su paciente, y después editá el turno.'
              : 'El turno es del cliente, sin paciente: cargá el paciente y después editá el turno.'}
            <PatientRequiredLinks appointment={appointment} />
          </Alert>
        </div>
      ) : null}
      <div className="mb-3 appointment-detail-transitions">
        <AppointmentActions appointment={appointment} focusAfter={titleRef} />
      </div>
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

/**
 * "Turno 3 de 8" y "Ver los turnos de la serie" (3.6): la serie entera en un pedido (front-04), solo si el turno tiene serie.
 * Mientras llega, "Turno de una serie".
 */
function SeriesRow({ appointment }: { appointment: AppointmentDetail }) {
  const seriesId = appointment.series_id;
  const query = useQuery({
    ...appointmentSeriesQueryOptions(seriesId ?? ''),
    enabled: seriesId !== null,
  });

  if (seriesId === null) {
    return null;
  }

  const position = query.data === undefined ? null : seriesPosition(appointment, query.data);

  return (
    <p className="mb-2 appointment-series">
      <IconRepeat size={16} className="me-1" aria-hidden="true" />
      {position === null
        ? 'Turno de una serie.'
        : `Turno ${String(position.index)} de ${String(position.total)} de una serie.`}{' '}
      <SeriesLink seriesId={seriesId} />
    </p>
  );
}

function AppointmentStatusCard({ appointment }: { appointment: AppointmentDetail }) {
  const rows: [string, ReactNode][] = [
    ['Estado', <StatusLabel key="status" status={appointment.status} />],
  ];
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
      <SeriesRow appointment={appointment} />
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
