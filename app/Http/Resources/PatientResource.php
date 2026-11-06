<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'species' => $this->species ? ['id' => $this->species->id, 'name' => $this->species->name] : null,
            'breed' => $this->breed ? ['id' => $this->breed->id, 'name' => $this->breed->name] : null,
            'sex' => [
                'value' => $this->sex->value,
                'label' => $this->sex->label(),
            ],
            'is_neutered' => $this->is_neutered,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'birth_date_estimated' => $this->birth_date_estimated,
            'age' => $this->age_label,
            'weight_kg' => $this->weight_kg,
            'microchip' => $this->microchip,
            'is_deceased' => $this->deceased_at !== null,
            // La ruta interna del archivo no sale de la API (entrega 1.5, pregunta 14).
            'has_photo' => $this->photo_path !== null,
        ];
    }
}
