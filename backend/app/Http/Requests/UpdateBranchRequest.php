<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH o PUT (semántica parcial): solo se validan los campos enviados (FASE 2,
 * entrega 2.1). La sucursal por defecto, la inactivación y el establecimiento con
 * facturas los revisa BranchService.
 */
class UpdateBranchRequest extends FormRequest
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
            'establishment' => ['sometimes', 'required', 'string', 'regex:' . BranchMessages::ESTABLISHMENT_REGEX],
            'address' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:' . BranchMessages::ADDRESS_REGEX],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:' . BranchMessages::PHONE_REGEX],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return BranchMessages::branch();
    }
}
