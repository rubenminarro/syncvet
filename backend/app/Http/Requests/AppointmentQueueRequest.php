<?php

namespace App\Http\Requests;

use App\Support\ApiDateTime;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de la cola del día (FASE 3, entrega 3.4; P8 y pregunta 25): date, una fecha sola
 * (Y-m-d) de Asunción, hoy por defecto; all (toda la sucursal, para quien tiene ficha de
 * veterinario); veterinarian_id y schedule_id de la clínica (también inactivos o eliminados,
 * como el listado).
 */
class AppointmentQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = CurrentClinic::id();

        return [
            'date' => ['nullable', 'string', 'regex:' . ApiDateTime::DATE_ONLY, 'date'],
            'all' => ['nullable', 'boolean'],
            'veterinarian_id' => ['nullable', 'integer', Rule::exists('veterinarians', 'id')->where('clinic_id', $clinicId)],
            'schedule_id' => ['nullable', 'integer', Rule::exists('schedules', 'id')->where('clinic_id', $clinicId)],
        ];
    }

    public function messages(): array
    {
        return [
            'date.string' => ScheduleAvailabilityRequest::DATE_FORMAT,
            'date.regex' => ScheduleAvailabilityRequest::DATE_FORMAT,
            'date.date' => ScheduleAvailabilityRequest::DATE_FORMAT,
            'veterinarian_id.exists' => 'El veterinario no existe.',
            'schedule_id.exists' => 'La agenda no existe.',
        ];
    }

    /**
     * El día de la cola, a las 00:00 de Asunción.
     */
    public function day(): CarbonImmutable
    {
        return $this->filled('date') ? ApiDateTime::startOf($this->input('date')) : now()->toImmutable()->startOfDay();
    }
}
