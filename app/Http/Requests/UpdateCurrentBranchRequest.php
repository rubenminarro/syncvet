<?php

namespace App\Http\Requests;

use App\Support\CurrentClinic;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sucursal actual del usuario autenticado (FASE 2, entrega 2.2b): una sucursal
 * activa de la clínica asignada al usuario (branch_user). AuthService la vuelve a
 * revisar con la clínica bloqueada.
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
        $userId = $this->user()?->getKey();

        return [
            'branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')
                    ->where('clinic_id', CurrentClinic::id())
                    ->where('is_active', true)
                    ->where(fn (Builder $query) => $query->whereExists(
                        fn (Builder $assigned) => $assigned->from('branch_user')
                            ->whereColumn('branch_user.branch_id', 'branches.id')
                            ->where('branch_user.user_id', $userId)
                    )),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Elegí una sucursal.',
            'branch_id.integer' => self::BRANCH_NOT_AVAILABLE,
            'branch_id.exists' => self::BRANCH_NOT_AVAILABLE,
        ];
    }
}
