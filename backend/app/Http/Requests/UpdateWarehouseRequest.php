<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial) de un depósito (FASE 2, entrega 2.1). No cambia de
 * sucursal: branch_id es prohibited. El depósito por defecto y la inactivación los
 * revisa WarehouseService.
 */
class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(BranchMessages::upperCodes($this->all(), ['code']));
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', ...BranchMessages::code()],
            'name' => ['sometimes', 'required', ...BranchMessages::name()],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return BranchMessages::warehouse();
    }
}
