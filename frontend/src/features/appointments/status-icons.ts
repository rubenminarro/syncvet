import {
  IconCircleCheck,
  IconCircleX,
  IconClock,
  IconDoorEnter,
  IconStethoscope,
  IconUserX,
  type Icon,
} from '@tabler/icons-react';

/** El ícono de cada estado (el estado va siempre con su texto, no solo con el ícono o el color). */
export const STATUS_ICONS: Record<string, Icon> = {
  pending_confirmation: IconClock,
  confirmed: IconCircleCheck,
  checked_in: IconDoorEnter,
  attended: IconStethoscope,
  no_show: IconUserX,
  cancelled: IconCircleX,
};

/**
 * Cómo se ve cada estado, igual en el día, la semana, la lista (la tabla y las tarjetas) y el detalle (3.5, pregunta 5): el
 * ausente, atenuado; el cancelado, atenuado y tachado (salvo su estado, que sigue legible con el ícono y el texto); los
 * demás, normales.
 */
export type StatusLook = 'dimmed' | 'cancelled' | null;

export function statusLook(status: string): StatusLook {
  if (status === 'cancelled') {
    return 'cancelled';
  }

  return status === 'no_show' ? 'dimmed' : null;
}

/** La clase de una fila de la tabla de la lista (`appointment-row-dimmed` o `appointment-row-cancelled`). */
export function statusRowClass(status: string): string | undefined {
  const look = statusLook(status);

  return look === null ? undefined : `appointment-row-${look}`;
}
