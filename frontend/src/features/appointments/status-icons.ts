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
