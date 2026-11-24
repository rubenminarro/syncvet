<?php

namespace App\Http\Requests;

use App\Rules\WholeAmount;
use App\Support\CurrentClinic;
use Illuminate\Validation\Rule;

/**
 * Reglas y mensajes compartidos por el alta y la edición de un ítem de servicio de la consulta (FASE
 * 4, entrega 4.3). La cantidad, mayor que cero, hasta 9999 y con hasta 3 decimales (la columna es
 * decimal(12,3)); el precio unitario y el descuento, importes enteros en guaraníes (WholeAmount, 12
 * dígitos, como el precio del catálogo). Que el servicio esté activo, el descuento no supere el
 * subtotal y el total no pase el máximo lo revisa ConsultationItemService.
 */
class ConsultationItemMessages
{
    public const MAX_DIGITS = 12;

    public const SERVICE_NOT_AVAILABLE = 'El servicio no existe o está inactivo.';

    public const DISCOUNT_TOO_HIGH = 'El descuento no puede ser mayor que el subtotal del ítem.';

    public const TOTAL_TOO_HIGH = 'El total del ítem supera el máximo: revisá la cantidad o el precio.';

    /**
     * Los importes y el veterinario; en la edición, cada uno con "sometimes" delante.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function amountRules(): array
    {
        return [
            'quantity' => ['numeric', 'gt:0', 'max:9999', 'decimal:0,3'],
            'unit_price' => ['nullable', new WholeAmount(self::MAX_DIGITS)],
            'discount' => ['nullable', new WholeAmount(self::MAX_DIGITS)],
            'veterinarian_id' => [
                'nullable', 'integer',
                Rule::exists('veterinarians', 'id')->where('clinic_id', CurrentClinic::id())->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'quantity.gt' => 'La cantidad debe ser mayor que cero.',
            'quantity.max' => 'La cantidad no puede superar 9999.',
            'quantity.decimal' => 'La cantidad admite hasta 3 decimales.',
            'veterinarian_id.exists' => 'El veterinario no existe.',
        ];
    }
}
