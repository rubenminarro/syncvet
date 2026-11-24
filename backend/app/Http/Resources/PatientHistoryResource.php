<?php

namespace App\Http\Resources;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\PatientApplication;
use App\Services\PatientHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un elemento del historial del paciente (FASE 4, entrega 4.6): type ("consultation" o "application"),
 * occurred_at (la fecha de la consulta o de la aplicación, UTC "Z"), cancelled (anulada) y el
 * elemento, en consultation o en application (el otro, null).
 *
 * - La consulta, con el resumen del listado (ConsultationResource: código, fecha, estado, sucursal,
 *   paciente, cliente, veterinario, turno y motivo) y, con consultations.show-clinical, además el
 *   diagnóstico y el peso (weight_kg, para ver su evolución; en el detalle también es del examen
 *   clínico). La recepción no los recibe: las claves no están.
 * - La aplicación, como en su listado (PatientApplicationResource), para todos.
 *
 * Espera las relaciones de PatientHistoryService cargadas.
 *
 * @mixin Consultation|PatientApplication
 */
class PatientHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Consultation) {
            $consultation = (new ConsultationResource($this->resource))->resolve($request);

            if (ConsultationResource::showsClinical($request)) {
                $consultation['weight_kg'] = $this->resource->weight_kg;
            }

            return [
                'type' => PatientHistoryService::CONSULTATION,
                'occurred_at' => $this->resource->consulted_at->toISOString(),
                'cancelled' => $this->resource->status === ConsultationStatus::CANCELLED,
                'consultation' => $consultation,
                'application' => null,
            ];
        }

        return [
            'type' => PatientHistoryService::APPLICATION,
            'occurred_at' => $this->resource->applied_at->toISOString(),
            'cancelled' => $this->resource->cancelled_at !== null,
            'consultation' => null,
            'application' => (new PatientApplicationResource($this->resource))->resolve($request),
        ];
    }
}
