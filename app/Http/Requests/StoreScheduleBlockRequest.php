<?php

namespace App\Http\Requests;

use App\Support\ApiDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Alta de un bloqueo de una agenda (FASE 3, entrega 3.1). starts_at y ends_at son
 * instantes (con zona, o sin ella en hora de Asunción; ApiDateTime), en múltiplos de 5
 * minutos y sin segundos en la hora de Asunción; ends_at posterior a starts_at, hasta 366
 * días de largo. reason opcional. La agenda sale de la URL.
 */
class StoreScheduleBlockRequest extends FormRequest
{
    public const MAX_DAYS = 366;

    public const DATE_TIME_FORMAT = 'Ingresá una fecha con hora (2026-11-09T13:00:00Z).';

    public const OFF_GRID = 'La hora debe estar en múltiplos de 5 minutos, sin segundos.';

    public const ENDS_BEFORE_START = 'El fin del bloqueo debe ser posterior a su inicio.';

    public const TOO_LONG = 'El bloqueo no puede durar más de ' . self::MAX_DAYS . ' días.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'string', 'regex:' . ApiDateTime::DATE_TIME_FORMAT, 'date'],
            'ends_at' => ['required', 'string', 'regex:' . ApiDateTime::DATE_TIME_FORMAT, 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.required' => 'El inicio del bloqueo es obligatorio.',
            'starts_at.string' => self::DATE_TIME_FORMAT,
            'starts_at.regex' => self::DATE_TIME_FORMAT,
            'starts_at.date' => self::DATE_TIME_FORMAT,
            'ends_at.required' => 'El fin del bloqueo es obligatorio.',
            'ends_at.string' => self::DATE_TIME_FORMAT,
            'ends_at.regex' => self::DATE_TIME_FORMAT,
            'ends_at.date' => self::DATE_TIME_FORMAT,
            'reason.string' => 'El motivo debe tener el formato correcto.',
            'reason.max' => 'El motivo no debe tener más de 255 caracteres.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
                    return;
                }

                $startsAt = $this->startsAt();
                $endsAt = $this->endsAt();

                foreach (['starts_at' => $startsAt, 'ends_at' => $endsAt] as $field => $value) {
                    if (! self::isOnGrid($value)) {
                        $validator->errors()->add($field, self::OFF_GRID);
                    }
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($endsAt->lessThanOrEqualTo($startsAt)) {
                    $validator->errors()->add('ends_at', self::ENDS_BEFORE_START);
                } elseif ($endsAt->greaterThan($startsAt->addDays(self::MAX_DAYS))) {
                    $validator->errors()->add('ends_at', self::TOO_LONG);
                }
            },
        ];
    }

    /**
     * El inicio en la hora de Asunción.
     */
    public function startsAt(): CarbonImmutable
    {
        return ApiDateTime::toLocal($this->input('starts_at'));
    }

    /**
     * El fin en la hora de Asunción.
     */
    public function endsAt(): CarbonImmutable
    {
        return ApiDateTime::toLocal($this->input('ends_at'));
    }

    /**
     * Múltiplo de 5 minutos, sin segundos ni fracción, en la hora de Asunción.
     */
    public static function isOnGrid(CarbonImmutable $value): bool
    {
        return $value->minute % 5 === 0 && $value->second === 0 && $value->microsecond === 0;
    }
}
