<?php

namespace App\Http\Requests;

use App\Enums\AppointmentSource;
use App\Support\ApiDateTime;
use App\Support\AppointmentRecurrence;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas y mensajes compartidos por el alta y la edición de turnos (FASE 3, entrega 3.2a).
 * La validación revisa el formato y que cada referencia sea de la clínica; AppointmentService
 * revisa con las filas bloqueadas lo demás: la agenda y su veterinario activos, el servicio
 * activo, el paciente no fallecido y de ese cliente, el día, la duración, el horario semanal,
 * los bloqueos y la superposición.
 *
 * starts_at y ends_at son instantes (con zona, o sin ella en hora de Asunción; ApiDateTime)
 * en múltiplos de 5 minutos y sin segundos en la hora de Asunción. La sucursal, el
 * veterinario, el estado, la serie y la marca de sobreturno no se reciben (prohibited): los
 * fija el Service.
 */
class AppointmentMessages
{
    public const MAX_MINUTES = 720;

    /**
     * El teléfono de los clientes (§6.1).
     */
    public const PHONE_REGEX = '/^\+?[1-9]\d{7,14}$/';

    /**
     * Texto libre con al menos una letra y sin caracteres de control ni < >: el nombre de
     * contacto no usa PersonName ("Juan, el de la perra Luna").
     */
    public const CONTACT_NAME_REGEX = '/^(?=.*\p{L})[^\p{Cc}<>]+$/u';

    public const SCHEDULE_NOT_AVAILABLE = 'La agenda no existe o está inactiva.';

    public const VETERINARIAN_INACTIVE = 'El veterinario de la agenda está inactivo.';

    public const SERVICE_NOT_AVAILABLE = 'El servicio no existe o está inactivo.';

    public const PATIENT_NOT_AVAILABLE = 'El paciente no existe.';

    public const PATIENT_DECEASED = 'El paciente falleció: no se le pueden dar turnos.';

    public const PATIENT_NOT_OF_CLIENT = 'El paciente no es de ese cliente.';

    public const CLIENT_NOT_AVAILABLE = 'El cliente no existe.';

    public const WHO_REQUIRED = 'Ingresá el paciente, el cliente o un nombre y un teléfono de contacto.';

    public const CONTACT_PROHIBITED = 'El nombre y el teléfono de contacto van solo en un turno sin cliente ni paciente.';

    public const CONTACT_TOGETHER = 'Ingresá el nombre y el teléfono de contacto juntos.';

    public const PAST_DAY = 'No podés dar un turno en un día que ya pasó.';

    public const ENDS_BEFORE_START = 'El fin del turno debe ser posterior a su inicio.';

    public const TOO_LONG = 'El turno no puede durar más de ' . self::MAX_MINUTES . ' minutos.';

    public const CROSSES_MIDNIGHT = 'El turno tiene que empezar y terminar el mismo día.';

    public const DATE_TIME_FORMAT = 'Ingresá una fecha con hora (2026-11-09T13:00:00Z).';

    public const OFF_GRID = 'La hora debe estar en múltiplos de 5 minutos, sin segundos.';

    public const RECURRENCE_BOTH = 'Ingresá la cantidad de turnos o la fecha de fin de la serie, no las dos.';

    public const RECURRENCE_END_REQUIRED = 'Ingresá la cantidad de turnos o la fecha de fin de la serie.';

    public const RECURRENCE_UNTIL_BEFORE = 'La fecha de fin de la serie no puede ser anterior al primer turno.';

    public const RECURRENCE_UNTIL_TOO_FAR = 'La fecha de fin de la serie puede ser, como mucho, un año después del primer turno.';

    public const RECURRENCE_COUNT_TOO_FAR = 'La última fecha de la serie puede ser, como mucho, un año después del primer turno: reducí la cantidad de turnos o las semanas entre turnos.';

    public const RECURRENCE_TOO_MANY = 'La serie no puede tener más de ' . AppointmentRecurrence::MAX_COUNT . ' turnos.';

    public const RECURRENCE_TOO_FEW = 'La serie tiene que tener al menos ' . AppointmentRecurrence::MIN_COUNT . ' turnos.';

    public const RECURRENCE_FORMAT = 'La serie solo admite every_weeks, count y until.';

    public const SOURCE_INVALID = 'El origen debe ser recepción, teléfono o WhatsApp: la reserva online no está disponible.';

    /**
     * Los orígenes que acepta la API: todos menos ONLINE (reserva online, fuera del MVP).
     *
     * @return list<string>
     */
    public static function sources(): array
    {
        return array_values(array_filter(AppointmentSource::values(), fn (string $value) => $value !== AppointmentSource::ONLINE->value));
    }

    /**
     * Los datos del turno. En la edición, cada uno con "sometimes" delante.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function dataRules(): array
    {
        $clinicId = CurrentClinic::id();

        return [
            // Activa: lo revisa el Service, con la agenda bloqueada.
            'schedule_id' => ['required', 'integer', Rule::exists('schedules', 'id')->where('clinic_id', $clinicId)],
            'patient_id' => [
                'nullable', 'integer',
                Rule::exists('patients', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
            ],
            'client_id' => [
                'nullable', 'integer',
                Rule::exists('clients', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
            ],
            'service_id' => [
                'nullable', 'integer',
                Rule::exists('services', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
            ],
            'contact_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:' . self::CONTACT_NAME_REGEX],
            'contact_phone' => ['nullable', 'string', 'regex:' . self::PHONE_REGEX],
            'starts_at' => ['required', 'string', 'regex:' . ApiDateTime::DATE_TIME_FORMAT, 'date'],
            'ends_at' => ['nullable', 'string', 'regex:' . ApiDateTime::DATE_TIME_FORMAT, 'date'],
            'source' => ['string', Rule::in(self::sources())],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'overbook' => ['boolean'],
        ];
    }

    /**
     * La serie del alta (FASE 3, entrega 3.3; P1): every_weeks (1 a 12; 1 por defecto, también
     * con null) y uno de count (2 a 52) o until (una fecha). Lo que depende del primer turno
     * (la última fecha hasta un año después, con count o con until; until desde su fecha y de 2
     * a 52 turnos) y count o until, uno solo, lo revisa StoreAppointmentRequest::after().
     *
     * @return array<string, array<int, mixed>>
     */
    public static function recurrenceRules(): array
    {
        return [
            'recurrence' => ['sometimes', 'nullable', 'array:every_weeks,count,until'],
            'recurrence.every_weeks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:' . AppointmentRecurrence::MAX_EVERY_WEEKS],
            'recurrence.count' => ['nullable', 'integer', 'min:' . AppointmentRecurrence::MIN_COUNT, 'max:' . AppointmentRecurrence::MAX_COUNT],
            'recurrence.until' => ['nullable', 'string', 'date_format:Y-m-d'],
        ];
    }

    public static function recurrenceMessages(): array
    {
        $everyWeeks = 'La serie se repite cada 1 a ' . AppointmentRecurrence::MAX_EVERY_WEEKS . ' semanas.';
        $count = 'La serie tiene de ' . AppointmentRecurrence::MIN_COUNT . ' a ' . AppointmentRecurrence::MAX_COUNT . ' turnos.';
        $until = 'Ingresá la fecha de fin de la serie (2026-12-31).';

        return [
            'recurrence.array' => self::RECURRENCE_FORMAT,
            'recurrence.every_weeks.integer' => $everyWeeks,
            'recurrence.every_weeks.min' => $everyWeeks,
            'recurrence.every_weeks.max' => $everyWeeks,
            'recurrence.count.integer' => $count,
            'recurrence.count.min' => $count,
            'recurrence.count.max' => self::RECURRENCE_TOO_MANY,
            'recurrence.until.string' => $until,
            'recurrence.until.date_format' => $until,
        ];
    }

    /**
     * Los campos que fija el Service: van siempre, en el alta y en la edición.
     *
     * @return array<string, array<int, string>>
     */
    public static function prohibitedRules(): array
    {
        return array_fill_keys(['branch_id', 'veterinarian_id', 'status', 'series_id', 'is_overbooked'], ['prohibited']);
    }

    public static function messages(): array
    {
        return [
            'schedule_id.required' => 'La agenda es obligatoria.',
            'schedule_id.integer' => self::SCHEDULE_NOT_AVAILABLE,
            'schedule_id.exists' => self::SCHEDULE_NOT_AVAILABLE,
            'patient_id.integer' => self::PATIENT_NOT_AVAILABLE,
            'patient_id.exists' => self::PATIENT_NOT_AVAILABLE,
            'client_id.integer' => self::CLIENT_NOT_AVAILABLE,
            'client_id.exists' => self::CLIENT_NOT_AVAILABLE,
            'service_id.integer' => self::SERVICE_NOT_AVAILABLE,
            'service_id.exists' => self::SERVICE_NOT_AVAILABLE,
            'contact_name.string' => 'El nombre de contacto debe tener el formato correcto.',
            'contact_name.min' => 'El nombre de contacto debe tener al menos 2 caracteres.',
            'contact_name.max' => 'El nombre de contacto no debe tener más de 150 caracteres.',
            'contact_name.regex' => 'El nombre de contacto debe tener al menos una letra.',
            'contact_phone.string' => 'El teléfono de contacto debe tener el formato correcto.',
            'contact_phone.regex' => 'El teléfono de contacto debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).',
            'starts_at.required' => 'El inicio del turno es obligatorio.',
            'starts_at.string' => self::DATE_TIME_FORMAT,
            'starts_at.regex' => self::DATE_TIME_FORMAT,
            'starts_at.date' => self::DATE_TIME_FORMAT,
            'ends_at.string' => self::DATE_TIME_FORMAT,
            'ends_at.regex' => self::DATE_TIME_FORMAT,
            'ends_at.date' => self::DATE_TIME_FORMAT,
            'source.string' => self::SOURCE_INVALID,
            'source.in' => self::SOURCE_INVALID,
            'reason.string' => 'El motivo debe tener el formato correcto.',
            'reason.max' => 'El motivo no debe tener más de 255 caracteres.',
            'notes.string' => 'Las notas deben tener el formato correcto.',
            'notes.max' => 'Las notas no deben tener más de 1000 caracteres.',
            'overbook.boolean' => 'El sobreturno debe ser verdadero o falso.',
            'branch_id.prohibited' => 'La sucursal sale de la agenda: no se envía.',
            'veterinarian_id.prohibited' => 'El veterinario sale de la agenda: no se envía.',
            'status.prohibited' => 'El estado del turno no se envía: lo cambian sus acciones (confirmar, fichar, cancelar).',
            'series_id.prohibited' => 'La serie no se envía: la asigna el sistema.',
            'is_overbooked.prohibited' => 'Para dar un sobreturno, enviá overbook: true.',
        ];
    }

    /**
     * La grilla de 5 minutos y, si llegan los dos, el fin posterior al inicio. Lo demás del
     * horario (el día, la duración, el horario semanal) lo revisa el Service con el fin final.
     */
    public static function validateTimes(Validator $validator, mixed $startsAt, mixed $endsAt): void
    {
        $values = [];

        foreach (['starts_at' => $startsAt, 'ends_at' => $endsAt] as $field => $value) {
            if (! is_string($value) || $validator->errors()->has($field)) {
                continue;
            }

            $values[$field] = ApiDateTime::toLocal($value);

            if (! self::isOnGrid($values[$field])) {
                $validator->errors()->add($field, self::OFF_GRID);
                unset($values[$field]);
            }
        }

        if (isset($values['starts_at'], $values['ends_at']) && $values['ends_at']->lessThanOrEqualTo($values['starts_at'])) {
            $validator->errors()->add('ends_at', self::ENDS_BEFORE_START);
        }
    }

    /**
     * Múltiplo de 5 minutos, sin segundos ni fracción, en la hora de Asunción.
     */
    public static function isOnGrid(CarbonImmutable $value): bool
    {
        return $value->minute % 5 === 0 && $value->second === 0 && $value->microsecond === 0;
    }

    /**
     * overbook: true en el pedido (los valores que acepta la regla boolean).
     */
    public static function wantsOverbook(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }
}
