<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de una sucursal con su depósito por defecto (FASE 2, entrega 2.1, pregunta 17).
 * El código, el nombre y el establecimiento únicos los revisa BranchService.
 */
class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(BranchMessages::upperCodes($this->all(), ['code', 'warehouse_code']));
    }

    public function rules(): array
    {
        return [
            'code' => ['required', ...BranchMessages::code()],
            'name' => ['required', ...BranchMessages::name()],
            'establishment' => ['required', 'string', 'regex:' . BranchMessages::ESTABLISHMENT_REGEX],
            'address' => ['nullable', 'string', 'max:255', 'regex:' . BranchMessages::ADDRESS_REGEX],
            'phone' => ['nullable', 'string', 'regex:' . BranchMessages::PHONE_REGEX],
            'is_default' => ['sometimes', 'boolean'],
            'warehouse_code' => ['required', ...BranchMessages::code()],
            'warehouse_name' => ['nullable', ...BranchMessages::name()],
        ];
    }

    public function messages(): array
    {
        return BranchMessages::branch();
    }
}
