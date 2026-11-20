import { IconClock } from '@tabler/icons-react';
import { STATUS_ICONS } from './status-icons';
import type { Appointment } from './types';

/**
 * El estado de un turno con su ícono y su texto (no solo color), igual en el día, la semana, la lista, el panel y el detalle
 * (3.5). En un cancelado tachado, el estado sigue sin tachar (`.appointment-status-label`).
 */
export function StatusLabel({
  status,
  size = 14,
}: {
  status: Appointment['status'];
  size?: number;
}) {
  const StatusIcon = STATUS_ICONS[status.value] ?? IconClock;

  return (
    <span className="appointment-status-label text-nowrap" data-status={status.value}>
      <StatusIcon size={size} className="me-1" aria-hidden="true" />
      {status.label}
    </span>
  );
}
