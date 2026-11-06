<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Especie del catálogo. GET species conserva la forma anterior a la entrega 1.4
 * (id, name, breeds[].id, breeds[].name) y suma campos. Sin Show…Resource: el
 * catálogo no tiene detalle.
 */
class SpeciesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'is_global' => $this->isGlobal(),
            'breeds' => BreedResource::collection($this->whenLoaded('breeds')),
        ];
    }
}
