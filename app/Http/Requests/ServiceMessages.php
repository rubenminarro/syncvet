<?php

namespace App\Http\Requests;

use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Enums\TaxRate;
use App\Rules\WholeAmount;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Reglas y mensajes compartidos por el alta y la edición de servicios (FASE 2, entrega
 * 2.4). El nombre repetido (CatalogName, con la clínica bloqueada) y el nombre fijo de
 * un servicio usado los revisa ServiceCatalogService.
 */
class ServiceMessages
{
    /**
     * La columna admite 150: el nombre más el sufijo de baja entero.
     */
    public const NAME_MAX = 120;

    public const NAME_TAKEN = 'Ya hay un servicio con ese nombre.';

    /**
     * Los datos del servicio. En la edición, cada uno con "sometimes" delante.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function dataRules(): array
    {
        return [
            'code' => ['prohibited'],
            'name' => ['required', 'string', 'min:2', 'max:' . self::NAME_MAX, 'regex:' . CatalogMessages::NAME_REGEX],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:720', 'multiple_of:5'],
            // Pregunta 21: sin decimales mientras la moneda sea PYG (la columna sigue en decimal(14,2)).
            'standard_price' => ['required', new WholeAmount(12)],
            'tax_rate' => ['required', Rule::enum(TaxRate::class)],
            'status' => ['required', Rule::enum(ServiceStatus::class)],
        ];
    }

    public static function messages(): array
    {
        return [
            'code.prohibited' => 'El código del servicio se asigna automáticamente.',
            'name.required' => 'El nombre del servicio es obligatorio.',
            'name.string' => 'El nombre del servicio debe tener el formato correcto.',
            'name.min' => 'El nombre del servicio debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre del servicio no debe tener más de ' . self::NAME_MAX . ' caracteres.',
            'name.regex' => 'El nombre del servicio debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.',
            'category.required' => 'La categoría es obligatoria.',
            'category.' . Enum::class => 'La categoría no es válida: debe ser una de ' . implode(', ', ServiceCategory::values()) . '.',
            'description.string' => 'La descripción debe tener el formato correcto.',
            'description.max' => 'La descripción no debe tener más de 1000 caracteres.',
            'duration_minutes.required' => 'La duración es obligatoria.',
            'duration_minutes.integer' => 'La duración debe ser un número entero de minutos.',
            'duration_minutes.min' => 'La duración debe estar entre 5 y 720 minutos.',
            'duration_minutes.max' => 'La duración debe estar entre 5 y 720 minutos.',
            'duration_minutes.multiple_of' => 'La duración debe ser un múltiplo de 5 minutos.',
            'standard_price.required' => 'El precio es obligatorio.',
            'tax_rate.required' => 'La tasa de IVA es obligatoria.',
            'tax_rate.' . Enum::class => 'La tasa de IVA no es válida: debe ser 0, 5 o 10.',
            'status.required' => 'El estado es obligatorio.',
            'status.' . Enum::class => 'El estado no es válido: debe ser active (activo) o inactive (inactivo).',
        ];
    }
}
