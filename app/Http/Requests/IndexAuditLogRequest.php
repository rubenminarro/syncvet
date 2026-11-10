<?php

namespace App\Http\Requests;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Support\ApiDateTime;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * Filtros de la auditoría (FASE 2, entrega 2.6; P14): los de ListFilters (search literal
 * en el nombre o el email del usuario), from y to, user_id (un usuario de la clínica,
 * también eliminado), event (AuditEvent), auditable_type (AuditLog::AUDITABLE_TYPES) y
 * auditable_id (exige auditable_type).
 *
 * from y to aceptan una fecha con hora, con zona ("Z", "-03:00") o sin ella (hora de
 * Asunción), o una fecha sola: from desde las 00:00 y to hasta las 23:59:59 de ese día
 * en Asunción (ApiDateTime). to no es anterior a from.
 */
class IndexAuditLogRequest extends FormRequest
{
    public const DATE_FORMAT = 'Ingresá una fecha (2026-11-09) o una fecha con hora (2026-11-09T13:00:00Z).';

    public const TO_BEFORE_FROM = 'La fecha hasta no puede ser anterior a la fecha desde.';

    public const TYPE_REQUIRED = 'Para filtrar por el id del registro, indicá también su tipo.';

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
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('clinic_id', CurrentClinic::id())],
            'event' => ['nullable', Rule::enum(AuditEvent::class)],
            'auditable_type' => ['nullable', 'string', Rule::in(AuditLog::AUDITABLE_TYPES), 'required_with:auditable_id'],
            'auditable_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.regex' => self::DATE_FORMAT,
            'from.date' => self::DATE_FORMAT,
            'to.regex' => self::DATE_FORMAT,
            'to.date' => self::DATE_FORMAT,
            'user_id.exists' => 'El usuario no existe.',
            'event.' . Enum::class => 'El evento no es válido: los eventos son ' . implode(', ', AuditEvent::values()) . '.',
            'auditable_type.in' => 'El tipo de registro no es válido.',
            'auditable_type.required_with' => self::TYPE_REQUIRED,
        ];
    }

    /**
     * to no es anterior a from, comparados como los usa el listado.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['from', 'to']) || ! $this->filled('from') || ! $this->filled('to')) {
                    return;
                }

                if ($this->toDate()->lessThan($this->fromDate())) {
                    $validator->errors()->add('to', self::TO_BEFORE_FROM);
                }
            },
        ];
    }

    /**
     * El comienzo del filtro en la hora de Asunción, o null sin from.
     */
    public function fromDate(): ?CarbonImmutable
    {
        return $this->filled('from') ? ApiDateTime::startOf($this->input('from')) : null;
    }

    /**
     * El final del filtro en la hora de Asunción, o null sin to.
     */
    public function toDate(): ?CarbonImmutable
    {
        return $this->filled('to') ? ApiDateTime::endOf($this->input('to')) : null;
    }
}
