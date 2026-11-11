<?php

namespace App\Enums;

/**
 * Las acciones que cambian el estado de un turno (FASE 3, entrega 3.2b), cada una con los
 * estados desde los que se admite y el estado al que lleva (tabla "Estados del turno" del
 * diseño). Atendido y cancelado son finales; el ausente solo vuelve a confirmado con
 * UNDO_NO_SHOW, el mismo día del turno. Repetir la misma (confirmar un confirmado) no se admite.
 */
enum AppointmentTransition: string
{
    case CONFIRM = 'confirm';
    case CHECK_IN = 'check_in';
    case ATTEND = 'attend';
    case NO_SHOW = 'no_show';
    case UNDO_NO_SHOW = 'undo_no_show';
    case CANCEL = 'cancel';

    /**
     * Los estados desde los que se admite.
     *
     * @return list<AppointmentStatus>
     */
    public function origins(): array
    {
        return match ($this) {
            self::CONFIRM => [AppointmentStatus::PENDING_CONFIRMATION],
            self::CHECK_IN, self::NO_SHOW => [AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED],
            self::ATTEND => [AppointmentStatus::CHECKED_IN],
            self::UNDO_NO_SHOW => [AppointmentStatus::NO_SHOW],
            self::CANCEL => [AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED, AppointmentStatus::CHECKED_IN],
        };
    }

    public function to(): AppointmentStatus
    {
        return match ($this) {
            self::CONFIRM, self::UNDO_NO_SHOW => AppointmentStatus::CONFIRMED,
            self::CHECK_IN => AppointmentStatus::CHECKED_IN,
            self::ATTEND => AppointmentStatus::ATTENDED,
            self::NO_SHOW => AppointmentStatus::NO_SHOW,
            self::CANCEL => AppointmentStatus::CANCELLED,
        };
    }

    public function allowsFrom(AppointmentStatus $status): bool
    {
        return in_array($status, $this->origins(), true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
