<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un depósito en una sucursal (FASE 2, entrega 2.1). La sucursal sale de la
 * URL: un branch_id en el cuerpo no se valida ni se usa. El código y el nombre únicos
 * los revisa WarehouseService.
 */
class StoreWarehouseRequest extends FormRequest
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
            'code' => ['required', ...BranchMessages::code()],
            'name' => ['required', ...BranchMessages::name()],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return BranchMessages::warehouse();
    }
}
