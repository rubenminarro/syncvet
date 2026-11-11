<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de una agenda (FASE 3, entrega 3.1; P9 y P10). branch_id y name obligatorios;
 * veterinarian_id y service_id opcionales, con al menos uno (lo revisa ScheduleService);
 * slot_minutes (30) e is_active (true) con su valor por defecto; hours opcional (sin
 * horario, la agenda no recibe turnos). online_booking no forma parte de la API: se ignora.
 */
class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = ScheduleMessages::dataRules();

        return [
            ...$data,
            'slot_minutes' => ['sometimes', ...$data['slot_minutes']],
            'is_active' => ['sometimes', ...$data['is_active']],
            'hours' => ['sometimes', ...$data['hours']],
            ...ScheduleMessages::hourRules(),
        ];
    }

    public function messages(): array
    {
        return ScheduleMessages::messages();
    }

    public function after(): array
    {
        return [fn ($validator) => ScheduleMessages::validateHours($validator, $this->input('hours'))];
    }
}
