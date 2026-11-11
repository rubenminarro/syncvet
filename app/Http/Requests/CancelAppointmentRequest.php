<?php

namespace App\Http\Requests;

use App\Enums\AppointmentCancelScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cancelar un turno (FASE 3, entregas 3.2b y 3.3; P3): el motivo es obligatorio, de 3 a 500
 * caracteres, y vale para todos los turnos que se cancelan. Sin límite de anticipación.
 *
 * scope (3.3, AppointmentCancelScope): single (por defecto, también con null: solo este turno),
 * following (este y los siguientes de su serie) o series (toda la serie). Con following o
 * series, de los demás turnos de la serie se cancelan solo los que están a confirmar o
 * confirmados y todavía no empezaron (AppointmentTransitionService::cancel).
 */
class CancelAppointmentRequest extends FormRequest
{
    public const SCOPE_INVALID = 'El alcance debe ser single (solo este turno), following (este y los siguientes) o series (toda la serie).';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:500'],
            'scope' => ['sometimes', 'nullable', 'string', Rule::in(AppointmentCancelScope::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Ingresá el motivo de la cancelación.',
            'cancellation_reason.string' => 'El motivo de la cancelación debe tener el formato correcto.',
            'cancellation_reason.min' => 'El motivo de la cancelación debe tener al menos 3 caracteres.',
            'cancellation_reason.max' => 'El motivo de la cancelación no debe tener más de 500 caracteres.',
            'scope.string' => self::SCOPE_INVALID,
            'scope.in' => self::SCOPE_INVALID,
        ];
    }

    public function scope(): AppointmentCancelScope
    {
        return AppointmentCancelScope::from($this->validated('scope') ?? AppointmentCancelScope::SINGLE->value);
    }
}
