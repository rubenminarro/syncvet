import { IconCalendarTime, IconEdit, IconEye } from '@tabler/icons-react';
import { Link } from '@tanstack/react-router';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useCan } from '../auth/permissions';
import { useCanEditAppointment } from './agenda-shared';
import type { Appointment } from './types';

/**
 * Cómo cargar el paciente de un turno sin él (3.4; desde la 3.5, también debajo de "Fichar" deshabilitado y en el error
 * `APPOINTMENT_PATIENT_REQUIRED`): sin cliente, "Nuevo cliente"; con cliente, "Nuevo paciente" de ese cliente; y "Editar el
 * turno" para elegirlo (sin alta rápida, pregunta 6). Cada enlace, con su permiso; sin ninguno, nada.
 */
export function PatientRequiredLinks({
  appointment,
}: {
  appointment: Pick<Appointment, 'id' | 'status' | 'client'>;
}) {
  const can = useCan();
  const canEdit = useCanEditAppointment();
  const links = [];

  if (appointment.client === null && can(PERMISSIONS.clientsStore)) {
    links.push(
      <Link key="client" to="/clientes/nuevo" className="btn btn-sm btn-outline-primary">
        Nuevo cliente
      </Link>,
    );
  }

  if (appointment.client !== null && can(PERMISSIONS.patientsStore)) {
    links.push(
      <Link
        key="patient"
        to="/clientes/$id/pacientes/nuevo"
        params={{ id: String(appointment.client.id) }}
        className="btn btn-sm btn-outline-primary"
      >
        Nuevo paciente
      </Link>,
    );
  }

  if (canEdit(appointment)) {
    links.push(
      <Link
        key="edit"
        to="/turnos/$id/editar"
        params={{ id: String(appointment.id) }}
        search={{ reprogramar: undefined }}
        className="btn btn-sm btn-outline-primary appointment-load-patient"
      >
        Editar el turno
      </Link>,
    );
  }

  return links.length === 0 ? null : (
    <div className="d-flex gap-2 flex-wrap mt-2 appointment-patient-links">{links}</div>
  );
}

/**
 * "Editar" y "Reprogramar" (3.4): el mismo formulario; "Reprogramar" entra con el foco en el horario. Solo con
 * `appointments.update` y en un turno a confirmar o confirmado. Con `withDetail`, también "Ver el turno" (el panel de la
 * agenda).
 */
export function AppointmentEditLinks({
  appointment,
  withDetail = false,
  size,
}: {
  appointment: Pick<Appointment, 'id' | 'status'>;
  withDetail?: boolean;
  size?: 'sm';
}) {
  const canEdit = useCanEditAppointment();
  const id = String(appointment.id);
  const sizeClass = size === 'sm' ? ' btn-sm' : '';

  return (
    <>
      {withDetail ? (
        <Link
          to="/turnos/$id"
          params={{ id }}
          className={`btn btn-outline-primary${sizeClass} appointment-view`}
        >
          <IconEye size={18} className="me-2" aria-hidden="true" />
          Ver el turno
        </Link>
      ) : null}
      {canEdit(appointment) ? (
        <>
          <Link
            to="/turnos/$id/editar"
            params={{ id }}
            search={{ reprogramar: 1 }}
            className={`btn btn-primary${sizeClass} appointment-reschedule`}
          >
            <IconCalendarTime size={18} className="me-2" aria-hidden="true" />
            Reprogramar
          </Link>
          <Link
            to="/turnos/$id/editar"
            params={{ id }}
            search={{ reprogramar: undefined }}
            className={`btn btn-outline-primary${sizeClass} appointment-edit`}
          >
            <IconEdit size={18} className="me-2" aria-hidden="true" />
            Editar
          </Link>
        </>
      ) : null}
    </>
  );
}
