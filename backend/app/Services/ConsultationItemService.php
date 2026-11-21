<?php

namespace App\Services;

use App\Enums\ConsultationItemBillingStatus;
use App\Enums\ConsultationItemType;
use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use App\Http\Requests\ConsultationItemMessages;
use App\Http\Requests\ConsultationMessages;
use App\Models\Consultation;
use App\Models\ConsultationItem;
use App\Models\Service;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Los servicios usados en la consulta (FASE 4, entrega 4.3; diseño R-099): ítems de tipo service
 * (los de producto, en la FASE 5), con el estado de cobro pending.
 *
 * - Solo con la consulta abierta, por su veterinario o el administrador:
 *   ConsultationService::lockEditable() (403 CONSULTATION_NOT_YOURS; 422 CONSULTATION_NOT_EDITABLE).
 * - Sumar: el servicio activo de la clínica (si no, 422 en service_id); la cantidad mayor que cero;
 *   el precio unitario del pedido o, si no llega, el del catálogo; el descuento (0 por defecto) no
 *   mayor al subtotal; veterinarian_id opcional (honorarios), activo. Copia el nombre del servicio
 *   (description) y su tasa de IVA.
 * - Totales con BCMath sobre strings (§3), en guaraníes sin decimales: subtotal = cantidad × precio
 *   unitario, redondeado a 0 decimales (half-up); total = subtotal − descuento.
 * - Editar (parcial): cantidad, precio unitario, descuento y veterinario, con los totales de nuevo.
 *   Quitar: baja física, auditada.
 *
 * Concurrencia (§3: veterinario → servicio → consulta → ítem): el veterinario y el servicio con
 * sharedLock (su baja los bloquea para escribir antes de revisar el uso), después la consulta con
 * lockForUpdate (lockEditable) y después el ítem con lockForUpdate. Los reintentos de
 * AppointmentService::withRetries.
 */
class ConsultationItemService
{
    public function __construct(
        protected AppointmentService $appointments,
        protected ConsultationService $consultations
    ) {
    }

    /**
     * @param array<string, mixed> $data los campos validados de StoreConsultationItemRequest
     */
    public function add(Consultation $consultation, array $data, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($consultation, $data, $user) {
            $veterinarianId = $this->lockVeterinarian($data['veterinarian_id'] ?? null);
            $service = Service::query()->whereKey((int) $data['service_id'])->sharedLock()
                ->first(['id', 'name', 'standard_price', 'tax_rate', 'status']);

            if ($service === null || $service->status !== ServiceStatus::ACTIVE) {
                throw ValidationException::withMessages(['service_id' => ConsultationItemMessages::SERVICE_NOT_AVAILABLE]);
            }

            $locked = $this->consultations->lockEditable($consultation, $user);
            $unitPrice = array_key_exists('unit_price', $data) && $data['unit_price'] !== null
                ? (string) $data['unit_price']
                : (string) $service->standard_price;

            ConsultationItem::create([
                'consultation_id' => $locked->getKey(),
                'type' => ConsultationItemType::SERVICE,
                'service_id' => $service->getKey(),
                'veterinarian_id' => $veterinarianId,
                'description' => $service->name,
                'tax_rate' => $service->tax_rate,
                'billing_status' => ConsultationItemBillingStatus::PENDING,
                ...$this->amounts((string) $data['quantity'], $unitPrice, (string) ($data['discount'] ?? '0')),
            ]);

            return $locked;
        }), ConsultationService::CONFLICT);
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateConsultationItemRequest (semántica parcial)
     */
    public function update(ConsultationItem $item, array $data, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($item, $data, $user) {
            $veterinarianId = array_key_exists('veterinarian_id', $data) ? $this->lockVeterinarian($data['veterinarian_id']) : null;
            $locked = $this->consultations->lockEditable($this->consultationOf($item), $user);
            $lockedItem = ConsultationItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            $fields = $this->amounts(
                (string) ($data['quantity'] ?? $lockedItem->quantity),
                (string) ($data['unit_price'] ?? $lockedItem->unit_price),
                (string) ($data['discount'] ?? $lockedItem->discount),
            );

            if (array_key_exists('veterinarian_id', $data)) {
                $fields['veterinarian_id'] = $veterinarianId;
            }

            $lockedItem->fill($fields);
            $lockedItem->save();

            return $locked;
        }), ConsultationService::CONFLICT);
    }

    public function remove(ConsultationItem $item, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($item, $user) {
            $locked = $this->consultations->lockEditable($this->consultationOf($item), $user);
            ConsultationItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail()->delete();

            return $locked;
        }), ConsultationService::CONFLICT);
    }

    /**
     * La cantidad, el precio unitario, el descuento y el total, con BCMath: subtotal = cantidad ×
     * precio, redondeado a 0 decimales (guaraníes); el descuento, no mayor al subtotal (422); el
     * total, hasta WholeAmount de 12 dígitos (422 en quantity).
     *
     * @return array{quantity: string, unit_price: string, discount: string, total: string}
     */
    public function amounts(string $quantity, string $unitPrice, string $discount): array
    {
        $subtotal = Decimal::round(bcmul($quantity, $unitPrice, 6), 0);

        if (bccomp($discount, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount' => ConsultationItemMessages::DISCOUNT_TOO_HIGH]);
        }

        if (strlen(ltrim($subtotal, '0')) > ConsultationItemMessages::MAX_DIGITS) {
            throw ValidationException::withMessages(['quantity' => ConsultationItemMessages::TOTAL_TOO_HIGH]);
        }

        return [
            'quantity' => Decimal::round($quantity, 3),
            'unit_price' => Decimal::round($unitPrice, 2),
            'discount' => Decimal::round($discount, 2),
            'total' => Decimal::round(bcsub($subtotal, $discount, 2), 2),
        ];
    }

    /**
     * El veterinario de los honorarios, activo y no eliminado, con sharedLock; null si no llega.
     */
    private function lockVeterinarian(mixed $id): ?int
    {
        if ($id === null) {
            return null;
        }

        $veterinarian = Veterinarian::query()->whereKey((int) $id)->sharedLock()->first(['id', 'status']);

        if ($veterinarian === null || $veterinarian->status !== VeterinarianStatus::ACTIVE) {
            throw ValidationException::withMessages(['veterinarian_id' => ConsultationMessages::VETERINARIAN_INACTIVE]);
        }

        return (int) $veterinarian->getKey();
    }

    private function consultationOf(ConsultationItem $item): Consultation
    {
        return Consultation::query()->whereKey($item->consultation_id)->firstOrFail();
    }
}
