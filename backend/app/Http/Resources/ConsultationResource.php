<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Consulta en el listado (FASE 4, entrega 4.1). Lo administrativo, para todos los que la ven: el
 * código, la fecha (UTC, "Z"), el estado ({value, label}), la sucursal, el paciente, el cliente
 * (el dueño al abrirla), el veterinario (con el nombre de su usuario), el turno y el motivo. Con
 * consultations.show-clinical (el administrador y el veterinario; ajuste de R-099), además el
 * diagnóstico; la recepción no lo recibe (pregunta 7): la clave no está.
 *
 * El permiso se consulta con can() (pasa por la Gate y su before del administrador), nunca con
 * hasPermissionTo(). Espera branch, patient, client y veterinarian.user cargados.
 *
 * @mixin Consultation
 */
class ConsultationResource extends JsonResource
{
    public const CLINICAL_PERMISSION = 'consultations.show-clinical';

    /**
     * Una sola vez por pedido (revisión de la 4.1): el listado no vuelve a preguntar por cada
     * fila. Sin usuario, nunca.
     */
    public static function showsClinical(Request $request): bool
    {
        if (! $request->attributes->has(self::CLINICAL_PERMISSION)) {
            $request->attributes->set(self::CLINICAL_PERMISSION, (bool) $request->user()?->can(self::CLINICAL_PERMISSION));
        }

        return $request->attributes->get(self::CLINICAL_PERMISSION);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'consulted_at' => $this->consulted_at->toISOString(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ],
            'patient' => [
                'id' => $this->patient->id,
                'code' => $this->patient->code,
                'name' => $this->patient->name,
            ],
            'client' => [
                'id' => $this->client->id,
                'display_name' => $this->client->display_name,
            ],
            'veterinarian' => [
                'id' => $this->veterinarian->id,
                'code' => $this->veterinarian->code,
                'name' => $this->veterinarian->user?->name,
            ],
            'appointment_id' => $this->appointment_id,
            'reason' => $this->reason,
            $this->mergeWhen(self::showsClinical($request), fn () => [
                'diagnosis' => $this->diagnosis,
            ]),
        ];
    }
}
