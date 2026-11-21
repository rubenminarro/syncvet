<?php

namespace App\Http\Requests;

use App\Enums\PatientApplicationType;
use App\Support\ApiDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros de las aplicaciones de un paciente (FASE 4, entrega 4.4): los de ListFilters (sin search),
 * type (uno o varios) y from y to sobre applied_at (una fecha sola es el día entero de Asunción, o una
 * fecha con hora; ApiDateTime), sin tope de días: sin ellos, todas.
 */
class IndexPatientApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ListFilters::rules()['per_page'],
            'page' => ListFilters::rules()['page'],
            'type' => ['nullable', 'array', 'list', 'max:' . count(PatientApplicationType::cases())],
            'type.*' => ['string', Rule::in(PatientApplicationType::values())],
            'from' => ['nullable', 'string', 'regex:' . ApiDateTime::FILTER_FORMAT, 'date'],
            'to' => ['nullable', 'string', 'regex:' . ApiDateTime::FILTER_FORMAT, 'date'],
        ];
    }

    public function messages(): array
    {
        $types = implode(', ', PatientApplicationType::values());

        return [
            'type.array' => "El tipo debe ser uno o varios de: {$types}.",
            'type.list' => "El tipo debe ser uno o varios de: {$types}.",
            'type.max' => "El tipo debe ser uno o varios de: {$types}.",
            'type.*.string' => "El tipo debe ser uno o varios de: {$types}.",
            'type.*.in' => "El tipo debe ser uno o varios de: {$types}.",
            'from.string' => IndexAuditLogRequest::DATE_FORMAT,
            'from.regex' => IndexAuditLogRequest::DATE_FORMAT,
            'from.date' => IndexAuditLogRequest::DATE_FORMAT,
            'to.string' => IndexAuditLogRequest::DATE_FORMAT,
            'to.regex' => IndexAuditLogRequest::DATE_FORMAT,
            'to.date' => IndexAuditLogRequest::DATE_FORMAT,
        ];
    }

    /**
     * Un solo tipo (type=vaccine) se toma como una lista de uno.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('type'))) {
            $this->merge(['type' => [$this->input('type')]]);
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

                if ($from !== null && $to !== null && $to->lessThan($from)) {
                    $validator->errors()->add('to', IndexAuditLogRequest::TO_BEFORE_FROM);
                }
            },
        ];
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function range(): array
    {
        return [
            $this->filled('from') ? ApiDateTime::ceilSecond(ApiDateTime::startOf($this->input('from'))) : null,
            $this->filled('to') ? ApiDateTime::floorSecond(ApiDateTime::endOf($this->input('to'))) : null,
        ];
    }
}
