<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial) de una agenda (FASE 3, entrega 3.1): solo se validan los
 * campos enviados. hours, si llega, reemplaza el horario entero (hours: [] lo vacía; null →
 * 422). Que la sucursal y el veterinario no cambien con turnos (SCHEDULE_NOT_EDITABLE), y
 * que el veterinario o el servicio nuevos estén activos, lo revisa ScheduleService.
 */
class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...array_map(fn (array $rules) => ['sometimes', ...$rules], ScheduleMessages::dataRules()),
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
