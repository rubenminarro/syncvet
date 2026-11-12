<?php

namespace App\Http\Requests;

use App\Rules\SelectableBranch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sucursal actual del usuario autenticado (FASE 2, entrega 2.2b): una sucursal
 * activa de la clínica asignada al usuario (Branch::selectableBy, la misma regla que
 * me.branches). AuthService la vuelve a revisar con la clínica bloqueada.
 */
class UpdateCurrentBranchRequest extends FormRequest
{
    public const BRANCH_NOT_AVAILABLE = 'La sucursal no existe, está inactiva o no está asignada a tu usuario.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'required', 'integer',
                // La regla de Branch::selectableBy (activa y asignada), con el scope de la clínica.
                new SelectableBranch($this->user()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Elegí una sucursal.',
            'branch_id.integer' => self::BRANCH_NOT_AVAILABLE,
        ];
    }
}
