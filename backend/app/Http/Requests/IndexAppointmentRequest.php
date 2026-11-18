<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Support\ApiDateTime;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros del listado de turnos (FASE 3, entrega 3.2a): los de ListFilters (search literal en
 * el paciente, el cliente y el contacto), from y to (una fecha sola es el día entero de
 * Asunción, o una fecha con hora; ApiDateTime), hasta 31 días; sin from ni to, hoy. Con uno
 * solo, el otro es el mismo día. schedule_id, branch_id, veterinarian_id, service_id,
 * patient_id y client_id de la clínica (también inactivos o eliminados); status, uno o varios
 * (status=confirmed o status[]=confirmed&status[]=checked_in); series_id.
 *
 * per_page llega hasta 500, no hasta 100 como los demás listados (entrega front-03, pregunta 1
 * de la F3 del frontend): la semana de una sucursal entra en un solo pedido. El resto de
 * ListFilters (search, page y el valor por defecto) no cambia.
 */
class IndexAppointmentRequest extends FormRequest
{
    public const MAX_DAYS = 31;

    public const RANGE_TOO_LONG = 'El rango no puede superar los ' . self::MAX_DAYS . ' días.';

    public const MAX_PER_PAGE = 500;

    public const PER_PAGE_OUT_OF_RANGE = 'Pedí de 1 a ' . self::MAX_PER_PAGE . ' turnos por página.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = CurrentClinic::id();

        return [
            ...ListFilters::rules(),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'from' => ['nullable', 'string', 'regex:' . ApiDateTime::FILTER_FORMAT, 'date'],
            'to' => ['nullable', 'string', 'regex:' . ApiDateTime::FILTER_FORMAT, 'date'],
            'schedule_id' => ['nullable', 'integer', Rule::exists('schedules', 'id')->where('clinic_id', $clinicId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('clinic_id', $clinicId)],
            'veterinarian_id' => ['nullable', 'integer', Rule::exists('veterinarians', 'id')->where('clinic_id', $clinicId)],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->where('clinic_id', $clinicId)],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('clinic_id', $clinicId)],
            'status' => ['nullable', 'array', 'list', 'max:' . count(AppointmentStatus::cases())],
            'status.*' => ['string', Rule::in(AppointmentStatus::values())],
            'series_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        $statuses = implode(', ', AppointmentStatus::values());

        return [
            'per_page.integer' => self::PER_PAGE_OUT_OF_RANGE,
            'per_page.min' => self::PER_PAGE_OUT_OF_RANGE,
            'per_page.max' => self::PER_PAGE_OUT_OF_RANGE,
            'from.string' => IndexAuditLogRequest::DATE_FORMAT,
            'from.regex' => IndexAuditLogRequest::DATE_FORMAT,
            'from.date' => IndexAuditLogRequest::DATE_FORMAT,
            'to.string' => IndexAuditLogRequest::DATE_FORMAT,
            'to.regex' => IndexAuditLogRequest::DATE_FORMAT,
            'to.date' => IndexAuditLogRequest::DATE_FORMAT,
            'schedule_id.exists' => 'La agenda no existe.',
            'branch_id.exists' => 'La sucursal no existe.',
            'veterinarian_id.exists' => 'El veterinario no existe.',
            'service_id.exists' => 'El servicio no existe.',
            'patient_id.exists' => 'El paciente no existe.',
            'client_id.exists' => 'El cliente no existe.',
            'status.array' => "El estado debe ser uno o varios de: {$statuses}.",
            'status.list' => "El estado debe ser uno o varios de: {$statuses}.",
            'status.max' => "El estado debe ser uno o varios de: {$statuses}.",
            'status.*.string' => "El estado debe ser uno o varios de: {$statuses}.",
            'status.*.in' => "El estado debe ser uno o varios de: {$statuses}.",
            'series_id.uuid' => 'La serie debe ser un UUID.',
        ];
    }

    /**
     * Un solo estado (status=confirmed) se toma como una lista de uno.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('status'))) {
            $this->merge(['status' => [$this->input('status')]]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                [$from, $to] = $this->range();

                if ($to->lessThan($from)) {
                    $validator->errors()->add('to', IndexAuditLogRequest::TO_BEFORE_FROM);
                } elseif ($to->greaterThan($from->addDays(self::MAX_DAYS))) {
                    $validator->errors()->add('to', self::RANGE_TOO_LONG);
                }
            },
        ];
    }

    /**
     * El rango en la hora de Asunción: sin from ni to, hoy; con uno solo, el día de ese.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $from = $this->filled('from') ? ApiDateTime::startOf($this->input('from')) : null;
        $to = $this->filled('to') ? ApiDateTime::endOf($this->input('to')) : null;

        if ($from === null && $to === null) {
            $today = now()->toImmutable();

            return [$today->startOfDay(), $today->endOfDay()];
        }

        return [
            ApiDateTime::ceilSecond($from ?? $to->startOfDay()),
            ApiDateTime::floorSecond($to ?? $from->endOfDay()),
        ];
    }
}
