<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ShowClientResource extends ClientResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'patients' => PatientResource::collection($this->whenLoaded('patients')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
