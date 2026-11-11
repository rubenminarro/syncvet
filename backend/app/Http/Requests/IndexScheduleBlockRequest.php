<?php

namespace App\Http\Requests;

use App\Support\ApiDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Filtros del listado de bloqueos de una agenda (FASE 3, entrega 3.1): los de ListFilters
 * (search literal en el motivo), from y to (una fecha sola es el día entero de Asunción, o
 * una fecha con hora; ApiDateTime, como la auditoría). Sin from ni to, los que terminan desde
 * hoy.
 */
class IndexScheduleBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...ListFilters::rules(),
            'from' => ['nullable', 'string', 'regex:' . ApiDateTime::FILTER_FORMAT, 'date'],
            'to' => ['nullable', 'string', 'regex:' . ApiDateTime::FILTER_FORMAT, 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.string' => IndexAuditLogRequest::DATE_FORMAT,
            'from.regex' => IndexAuditLogRequest::DATE_FORMAT,
            'from.date' => IndexAuditLogRequest::DATE_FORMAT,
            'to.string' => IndexAuditLogRequest::DATE_FORMAT,
            'to.regex' => IndexAuditLogRequest::DATE_FORMAT,
            'to.date' => IndexAuditLogRequest::DATE_FORMAT,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['from', 'to']) || ! $this->filled('from') || ! $this->filled('to')) {
                    return;
                }

                if ($this->toDate()->lessThan($this->fromDate())) {
                    $validator->errors()->add('to', IndexAuditLogRequest::TO_BEFORE_FROM);
                }
            },
        ];
    }

    public function fromDate(): ?CarbonImmutable
    {
        return $this->filled('from') ? ApiDateTime::startOf($this->input('from')) : null;
    }

    public function toDate(): ?CarbonImmutable
    {
        return $this->filled('to') ? ApiDateTime::endOf($this->input('to')) : null;
    }
}
