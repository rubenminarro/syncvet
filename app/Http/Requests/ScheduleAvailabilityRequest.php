<?php

namespace App\Http\Requests;

use App\Support\ApiDateTime;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros de la disponibilidad de una agenda (FASE 3, entrega 3.4): from y to, fechas solas
 * (Y-m-d) de Asunción; sin from, hoy; sin to, el día de from; hasta MAX_DAYS días contando los
 * dos. service_id, un servicio de la clínica (la duración de los horarios; el Service revisa
 * que esté activo, como el alta). duration_minutes (decisión D2 de la revisión), de 5 a 720 en
 * múltiplos de 5: manda sobre la del servicio y slot_minutes (para un turno con ends_at propio).
 */
class ScheduleAvailabilityRequest extends FormRequest
{
    public const MAX_DAYS = 7;

    public const DATE_FORMAT = 'Ingresá una fecha (2026-11-09).';

    public const RANGE_TOO_LONG = 'La disponibilidad se consulta de hasta ' . self::MAX_DAYS . ' días.';

    public const DURATION = 'Ingresá una duración de 5 a 720 minutos, en múltiplos de 5.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', 'regex:' . ApiDateTime::DATE_ONLY, 'date'],
            'to' => ['nullable', 'string', 'regex:' . ApiDateTime::DATE_ONLY, 'date'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', CurrentClinic::id())],
            'duration_minutes' => ['nullable', 'integer', 'between:5,720', 'multiple_of:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.string' => self::DATE_FORMAT,
            'from.regex' => self::DATE_FORMAT,
            'from.date' => self::DATE_FORMAT,
            'to.string' => self::DATE_FORMAT,
            'to.regex' => self::DATE_FORMAT,
            'to.date' => self::DATE_FORMAT,
            'service_id.exists' => AppointmentMessages::SERVICE_NOT_AVAILABLE,
            'duration_minutes.integer' => self::DURATION,
            'duration_minutes.between' => self::DURATION,
            'duration_minutes.multiple_of' => self::DURATION,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                [$from, $to] = $this->days();

                if ($to->lessThan($from)) {
                    $validator->errors()->add('to', IndexAuditLogRequest::TO_BEFORE_FROM);
                } elseif ($to->greaterThanOrEqualTo($from->addDays(self::MAX_DAYS))) {
                    $validator->errors()->add('to', self::RANGE_TOO_LONG);
                }
            },
        ];
    }

    /**
     * El primer y el último día, a las 00:00 de Asunción.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function days(): array
    {
        $from = $this->filled('from') ? ApiDateTime::startOf($this->input('from')) : now()->toImmutable()->startOfDay();
        $to = $this->filled('to') ? ApiDateTime::startOf($this->input('to')) : $from;

        return [$from, $to];
    }
}
