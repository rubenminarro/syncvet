<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Enum;

/**
 * Mensajes compartidos por el alta y la edición de clientes.
 */
class ClientMessages
{
    public static function all(): array
    {
        return [
            'kind' => [
                Enum::class => 'El tipo de cliente debe ser person (persona) o company (empresa).',
            ],
            'business_name' => [
                'required' => 'La razón social es obligatoria para una empresa.',
                'prohibited' => 'La razón social solo corresponde a una empresa.',
                'string' => 'La razón social debe tener el formato correcto.',
                'min' => 'La razón social debe tener al menos 2 caracteres.',
                'max' => 'La razón social no debe tener más de 150 caracteres.',
                'regex' => 'La razón social contiene caracteres no permitidos.',
            ],
            'document_number' => [
                'required' => 'El número de documento es obligatorio.',
                'string' => 'El número de documento debe tener el formato correcto.',
                'min' => 'El número de documento debe tener al menos 5 caracteres.',
                'max' => 'El número de documento no debe tener más de 20 caracteres.',
                'unique' => 'Este número de documento ya está en uso.',
                'regex' => 'El número de documento solo puede contener letras, números y guiones.',
            ],
            'first_name' => [
                'required' => 'El nombre es obligatorio.',
                'prohibited' => 'Una empresa no lleva nombre: usá la razón social.',
                'string' => 'El nombre debe tener el formato correcto.',
                'min' => 'El nombre debe tener al menos 2 caracteres.',
                'max' => 'El nombre no debe tener más de 100 caracteres.',
                'regex' => 'El nombre solo puede contener letras y espacios.',
            ],
            'last_name' => [
                'required' => 'El apellido es obligatorio.',
                'prohibited' => 'Una empresa no lleva apellido: usá la razón social.',
                'string' => 'El apellido debe tener el formato correcto.',
                'min' => 'El apellido debe tener al menos 2 caracteres.',
                'max' => 'El apellido no debe tener más de 100 caracteres.',
                'regex' => 'El apellido solo puede contener letras y espacios.',
            ],
            'email' => [
                'required' => 'El correo es obligatorio.',
                'email' => 'El correo debe tener el formato correcto.',
                'max' => 'El correo no debe tener más de 50 caracteres.',
                'lowercase' => 'El correo debe estar en minúsculas.',
                'unique' => 'Este correo ya está en uso.',
            ],
            'phone' => [
                'required' => 'El teléfono es obligatorio.',
                'regex' => 'El teléfono debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).',
            ],
            'address' => [
                'string' => 'La dirección debe tener el formato correcto.',
                'max' => 'La dirección no debe tener más de 255 caracteres.',
                'regex' => 'La dirección contiene caracteres no permitidos.',
            ],
            'city' => [
                'string' => 'La ciudad debe tener el formato correcto.',
                'max' => 'La ciudad no debe tener más de 100 caracteres.',
                'regex' => 'La ciudad solo puede contener letras, espacios, puntos y guiones.',
            ],
            'notifications_enabled' => [
                'boolean' => 'El campo de avisos debe ser verdadero o falso.',
            ],
            'notes' => [
                'string' => 'Las notas deben tener el formato correcto.',
                'max' => 'Las notas no deben tener más de 1000 caracteres.',
                'regex' => 'Las notas solo pueden contener letras, números, espacios y los siguientes caracteres: . , ; : ( ) - # @ ! ?',
            ],
        ];
    }
}
