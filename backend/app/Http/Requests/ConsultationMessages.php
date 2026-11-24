<?php

namespace App\Http\Requests;

use App\Support\ApiDateTime;
use Illuminate\Validation\Validator;

/**
 * Reglas y mensajes compartidos por el alta y la edición de la consulta (FASE 4, entrega 4.1;
 * pregunta 13 del diseño, R-099). Los límites van en caracteres (mb_strlen, la regla max de un
 * string): el motivo hasta 255 y los textos clínicos hasta 10000. Los números del examen, todos
 * opcionales mientras está abierta: peso de 0,01 a 9999,99 (2 decimales), temperatura de 30 a 45
 * °C (1 decimal), frecuencia cardíaca de 1 a 400, respiratoria de 1 a 200 y condición corporal de
 * 1 a 9. Los decimales, con punto, como el peso del paciente.
 *
 * consulted_at es un instante (con zona, o sin ella en hora de Asunción; ApiDateTime) y no puede
 * ser futuro; que no sea anterior al fichado del turno lo revisa ConsultationService, con el
 * turno a mano.
 */
class ConsultationMessages
{
    public const TEXT_MAX = 10000;

    public const FUTURE = 'La fecha de la consulta no puede ser futura.';

    public const BEFORE_CHECK_IN = 'La fecha de la consulta no puede ser anterior al fichado del turno.';

    public const CLOSE_REASON_REQUIRED = 'Ingresá el motivo para cerrar la consulta.';

    public const CLOSE_DIAGNOSIS_REQUIRED = 'Ingresá el diagnóstico para cerrar la consulta.';

    public const VETERINARIAN_REQUIRED = 'Indicá el veterinario de la consulta: no tenés una ficha de veterinario.';

    public const VETERINARIAN_FIXED = 'El veterinario de la consulta es el del turno o el de tu ficha: no se puede elegir otro.';

    public const VETERINARIAN_INACTIVE = 'El veterinario está inactivo.';

    /**
     * Los campos que se editan mientras está abierta (los del alta salvo el turno, el paciente y
     * el veterinario). En la edición, cada uno con "sometimes" delante.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function clinicalRules(): array
    {
        $text = ['nullable', 'string', 'max:' . self::TEXT_MAX];

        return [
            'consulted_at' => ['nullable', 'string', 'regex:' . ApiDateTime::DATE_TIME_FORMAT, 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'history' => $text,
            'weight_kg' => ['nullable', 'numeric', 'min:0.01', 'max:9999.99', 'decimal:0,2'],
            'temperature_c' => ['nullable', 'numeric', 'min:30', 'max:45', 'decimal:0,1'],
            'heart_rate' => ['nullable', 'integer', 'min:1', 'max:400'],
            'respiratory_rate' => ['nullable', 'integer', 'min:1', 'max:200'],
            'mucous_membranes' => ['nullable', 'string', 'max:50'],
            'capillary_refill_time' => ['nullable', 'string', 'max:20'],
            'body_condition_score' => ['nullable', 'integer', 'min:1', 'max:9'],
            'physical_exam' => $text,
            'diagnosis' => $text,
            'treatment' => $text,
            'instructions' => $text,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'consulted_at.string' => AppointmentMessages::DATE_TIME_FORMAT,
            'consulted_at.regex' => AppointmentMessages::DATE_TIME_FORMAT,
            'consulted_at.date' => AppointmentMessages::DATE_TIME_FORMAT,
            'weight_kg.min' => 'El peso debe ser de 0,01 a 9999,99 kg.',
            'weight_kg.max' => 'El peso debe ser de 0,01 a 9999,99 kg.',
            'weight_kg.decimal' => 'El peso admite hasta 2 decimales.',
            'temperature_c.min' => 'La temperatura debe ser de 30 a 45 °C.',
            'temperature_c.max' => 'La temperatura debe ser de 30 a 45 °C.',
            'temperature_c.decimal' => 'La temperatura admite 1 decimal.',
            'heart_rate.min' => 'La frecuencia cardíaca debe ser de 1 a 400.',
            'heart_rate.max' => 'La frecuencia cardíaca debe ser de 1 a 400.',
            'respiratory_rate.min' => 'La frecuencia respiratoria debe ser de 1 a 200.',
            'respiratory_rate.max' => 'La frecuencia respiratoria debe ser de 1 a 200.',
            'body_condition_score.min' => 'La condición corporal debe ser de 1 a 9.',
            'body_condition_score.max' => 'La condición corporal debe ser de 1 a 9.',
        ];
    }

    /**
     * consulted_at no futuro (al segundo: la hora de la API puede traer fracción).
     */
    public static function validateNotFuture(Validator $validator, mixed $consultedAt): void
    {
        if (! is_string($consultedAt) || $validator->errors()->has('consulted_at')) {
            return;
        }

        if (ApiDateTime::floorSecond(ApiDateTime::toLocal($consultedAt))->greaterThan(now())) {
            $validator->errors()->add('consulted_at', self::FUTURE);
        }
    }
}
