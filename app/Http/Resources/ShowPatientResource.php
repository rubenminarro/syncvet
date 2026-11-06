<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ShowPatientResource extends PatientResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'client' => $this->client ? [
                'id' => $this->client->id,
                'display_name' => $this->client->display_name,
                'phone' => $this->client->phone,
                'email' => $this->client->email,
                'deleted' => $this->client->trashed(),
            ] : null,
            'color' => $this->color,
            'photo_path' => $this->photo_path,
            'allergies' => $this->allergies,
            'notes' => $this->notes,
            'deceased_at' => $this->deceased_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
