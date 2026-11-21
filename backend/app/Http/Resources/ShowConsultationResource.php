<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use App\Support\ConsultationTotals;
use Illuminate\Http\Request;

/**
 * Detalle de una consulta (FASE 4, entrega 4.1): lo del listado más el turno (id, inicio y
 * estado), quién la creó, cerró o anuló y cuándo (UTC, "Z"), el motivo de la anulación y la hora
 * de alta. Con consultations.show-clinical (el administrador y el veterinario), además la
 * anamnesis, el examen físico (peso, temperatura, frecuencias, mucosas, llenado capilar,
 * condición corporal y el texto del examen), el diagnóstico, el tratamiento y las indicaciones;
 * sin él (la recepción, pregunta 7), esas claves no están.
 *
 * Con el permiso, además las enmiendas (4.2). Para todos, los ítems (4.3: lo administrativo, para
 * cobrar) y las aplicaciones (4.4, resumidas). Los adjuntos se suman en la 4.5.
 * Espera además appointment, items.service, items.veterinarian.user, createdBy, closedBy y cancelledBy cargados
 * (ConsultationService::loadDetail).
 *
 * @mixin Consultation
 */
class ShowConsultationResource extends ConsultationResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'appointment' => $this->appointment === null ? null : [
                'id' => $this->appointment->id,
                'starts_at' => $this->appointment->starts_at->toISOString(),
                'status' => [
                    'value' => $this->appointment->status->value,
                    'label' => $this->appointment->status->label(),
                ],
            ],
            // Entrega 4.3: los servicios usados, por id; administrativos (la recepción los cobra).
            'items' => ConsultationItemResource::collection($this->items)->resolve($request),
            // Revisión de la 4.3: lo que se cobra (sin los no_charge), con BCMath (ConsultationTotals).
            'totals' => ConsultationTotals::of($this->items),
            // Revisión de la 4.4 (el detalle del diseño): las aplicaciones de la consulta, administrativas como
            // su listado (la recepción las ve); el detalle completo, en GET patients/{patient}/applications.
            'applications' => $this->applications->map(fn ($application) => [
                'id' => $application->id,
                'type' => ['value' => $application->type->value, 'label' => $application->type->label()],
                'name' => $application->name,
                'applied_at' => $application->applied_at->toISOString(),
                'dose_number' => $application->dose_number,
                'next_dose_due_at' => $application->next_dose_due_at?->toDateString(),
                'cancelled' => $application->cancelled_at !== null,
            ])->all(),
            $this->mergeWhen(self::showsClinical($request), fn () => [
                'history' => $this->history,
                'weight_kg' => $this->weight_kg,
                'temperature_c' => $this->temperature_c,
                'heart_rate' => $this->heart_rate,
                'respiratory_rate' => $this->respiratory_rate,
                'mucous_membranes' => $this->mucous_membranes,
                'capillary_refill_time' => $this->capillary_refill_time,
                'body_condition_score' => $this->body_condition_score,
                'physical_exam' => $this->physical_exam,
                'treatment' => $this->treatment,
                'instructions' => $this->instructions,
                // Entrega 4.2: las enmiendas, de la más vieja a la más nueva; textos clínicos, como el resto.
                'amendments' => $this->amendments->map(fn ($amendment) => [
                    'id' => $amendment->id,
                    'field' => $amendment->field,
                    'content' => $amendment->content,
                    'reason' => $amendment->reason,
                    'created_by' => $this->user($amendment->createdBy),
                    'created_at' => $amendment->created_at?->toISOString(),
                ])->all(),
            ]),
            'created_by' => $this->user($this->createdBy),
            'closed_at' => $this->closed_at?->toISOString(),
            'closed_by' => $this->user($this->closedBy),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_by' => $this->user($this->cancelledBy),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function user(mixed $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
