<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Enum;

/**
 * Mensajes compartidos por el alta y la edición de pacientes.
 */
class PatientMessages
{
    /**
     * Fallecimiento anterior al nacimiento (UpdatePatientRequest::after).
     */
    public const DECEASED_BEFORE_BIRTH = 'La fecha de fallecimiento no puede ser anterior a la fecha de nacimiento.';

    public static function all(): array
    {
        return [
            'client_id.prohibited' => 'El dueño se cambia con la acción "cambiar dueño".',
            'code.prohibited' => 'El código del paciente se asigna automáticamente.',
            'name' => [
                'required' => 'El nombre del paciente es obligatorio.',
                'string' => 'El nombre debe tener el formato correcto.',
                'max' => 'El nombre no debe tener más de 100 caracteres.',
                'regex' => 'El nombre contiene caracteres no permitidos.',
            ],
            'species_id' => [
                'required' => 'La especie es obligatoria.',
                'integer' => 'La especie debe tener el formato correcto.',
                'exists' => 'La especie seleccionada no existe o está inactiva.',
            ],
            'breed_id' => [
                'integer' => 'La raza debe tener el formato correcto.',
                'exists' => 'La raza seleccionada no existe o está inactiva.',
            ],
            'sex' => [
                Enum::class => 'El sexo debe ser male (macho), female (hembra) o unknown (sin determinar).',
            ],
            'is_neutered.boolean' => 'El campo castrado debe ser verdadero o falso.',
            'birth_date' => [
                'date_format' => 'La fecha de nacimiento debe tener el formato AAAA-MM-DD.',
                'before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            ],
            'birth_date_estimated.boolean' => 'El campo fecha estimada debe ser verdadero o falso.',
            'color' => [
                'max' => 'El color no debe tener más de 80 caracteres.',
                'regex' => 'El color solo puede contener letras, espacios, comas y guiones.',
            ],
            'weight_kg' => [
                'numeric' => 'El peso debe ser un número.',
                'min' => 'El peso debe ser mayor que cero.',
                'max' => 'El peso no puede superar los 2000 kg.',
                'decimal' => 'El peso admite hasta 2 decimales.',
            ],
            'microchip' => [
                'max' => 'El microchip no debe tener más de 30 caracteres.',
                'regex' => 'El microchip solo puede contener letras y números.',
                'unique' => 'Este microchip ya está registrado en otro paciente.',
            ],
            'allergies.max' => 'Las alergias no deben tener más de 1000 caracteres.',
            'notes.max' => 'Las notas no deben tener más de 1000 caracteres.',
            'deceased_at' => [
                'date' => 'La fecha de fallecimiento no es válida.',
                'before_or_equal' => 'La fecha de fallecimiento no puede ser futura.',
            ],
        ];
    }
}
