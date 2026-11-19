import { IconCalendarTime, IconEdit, IconEye } from '@tabler/icons-react';
import { Link } from '@tanstack/react-router';
import { useCanEditAppointment } from './agenda-shared';
import type { Appointment } from './types';

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
