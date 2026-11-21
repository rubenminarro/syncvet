<?php

namespace Tests\Feature\Consultations;

use App\Models\Service;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Los servicios usados en la consulta (FASE 4, entrega 4.3): el precio del catálogo o el del pedido,
 * el descuento (no mayor al subtotal), los totales con BCMath en guaraníes, el servicio activo, el
 * veterinario de los honorarios, editar y quitar, la consulta cerrada o anulada (422
 * CONSULTATION_NOT_EDITABLE), otra ficha (403), la recepción (403 al escribir; ve los ítems) y el uso
 * del servicio (SERVICE_NAME_NOT_EDITABLE).
 */
class ConsultationItemTest extends TestCase
{
    use InteractsWithConsultations;

    private int $consultation;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
        $this->service = CurrentClinic::run($this->clinic, fn () => Service::create([
            'code' => 'SRV-000001', 'name' => 'Consulta general', 'standard_price' => '150000', 'tax_rate' => 10,
        ]));
        $this->actAsVet();
        $this->consultation = $this->opened(['appointment_id' => $this->checkedIn(), 'diagnosis' => 'Otitis']);
    }

    private function add(array $body = [])
    {
        return $this->postJson("/api/consultations/{$this->consultation}/items", ['service_id' => $this->service->id, 'quantity' => 1, ...$body]);
    }

    private function itemId(): int
    {
        return (int) DB::table('consultation_items')->orderByDesc('id')->value('id');
    }

    public function test_the_catalog_price_by_default(): void
    {
        $this->add()->assertCreated()
            ->assertJsonPath('data.items.0.service.id', $this->service->id)
            ->assertJsonPath('data.items.0.description', 'Consulta general')
            ->assertJsonPath('data.items.0.type.value', 'service')
            ->assertJsonPath('data.items.0.quantity', '1.000')
            ->assertJsonPath('data.items.0.unit_price', '150000.00')
            ->assertJsonPath('data.items.0.discount', '0.00')
            ->assertJsonPath('data.items.0.total', '150000.00')
            ->assertJsonPath('data.items.0.tax_rate.value', 10)
            ->assertJsonPath('data.items.0.billing_status.value', 'pending')
            ->assertJsonPath('data.items.0.veterinarian', null);

        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'consultation_item')->where('event', 'created')->count());
    }

    /**
     * Totales con BCMath: subtotal = cantidad × precio, redondeado a 0 decimales (half-up), menos el
     * descuento. Sin float: 0,1 × 3 no da 0,30000000000000004.
     */
    public static function totals(): array
    {
        return [
            'entero' => [2, 75000, 0, '150000.00'],
            'con descuento' => [2, 75000, 5000, '145000.00'],
            'decimal que redondea hacia arriba' => ['1.5', 33333, 0, '50000.00'],
            'decimal que redondea hacia abajo' => ['0.333', 1000, 0, '333.00'],
            'sin float' => ['0.1', 3, 0, '0.00'],
            'medio guaraní hacia arriba' => ['0.5', 1, 0, '1.00'],
            'descuento igual al subtotal' => [1, 150000, 150000, '0.00'],
            'precio cero' => [3, 0, 0, '0.00'],
        ];
    }

    #[DataProvider('totals')]
    public function test_totals(int|string $quantity, int $unitPrice, int $discount, string $total): void
    {
        $this->add(['quantity' => $quantity, 'unit_price' => $unitPrice, 'discount' => $discount])->assertCreated()
            ->assertJsonPath('data.items.0.total', $total);
    }

    public static function invalid(): array
    {
        return [
            'sin servicio' => [['service_id' => null], 'service_id'],
            'servicio que no existe' => [['service_id' => 999999], 'service_id'],
            'sin cantidad' => [['quantity' => null], 'quantity'],
            'cantidad cero' => [['quantity' => 0], 'quantity'],
            'cantidad negativa' => [['quantity' => -1], 'quantity'],
            'cantidad con 4 decimales' => [['quantity' => '1.2345'], 'quantity'],
            'cantidad de 10000' => [['quantity' => 10000], 'quantity'],
            'precio negativo' => [['unit_price' => -1], 'unit_price'],
            'precio con decimales' => [['unit_price' => '150000.5'], 'unit_price'],
            'descuento negativo' => [['discount' => -1], 'discount'],
            'descuento mayor al subtotal' => [['discount' => 150001], 'discount'],
            'veterinario que no existe' => [['veterinarian_id' => 999999], 'veterinarian_id'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_validation(array $body, string $field): void
    {
        $this->add($body)->assertUnprocessable()->assertJsonValidationErrors([$field]);
        $this->assertSame(0, DB::table('consultation_items')->count());
    }

    public function test_an_inactive_service_is_rejected(): void
    {
        DB::table('services')->where('id', $this->service->id)->update(['status' => 'inactive']);

        $this->add()->assertUnprocessable()->assertJsonPath('errors.service_id.0', 'El servicio no existe o está inactivo.');
    }

    public function test_the_veterinarian_of_the_fees_is_active(): void
    {
        $other = $this->veterinarianIn($this->branch);

        $this->add(['veterinarian_id' => $other->id])->assertCreated()->assertJsonPath('data.items.0.veterinarian.id', $other->id);

        DB::table('veterinarians')->where('id', $other->id)->update(['status' => 'inactive']);
        $this->add(['veterinarian_id' => $other->id])->assertUnprocessable()->assertJsonPath('errors.veterinarian_id.0', 'El veterinario está inactivo.');
    }

    public function test_edit_recalculates_and_delete_removes(): void
    {
        $this->add(['quantity' => 2])->assertCreated();
        $id = $this->itemId();

        $this->patchJson("/api/consultation-items/{$id}", ['discount' => 10000])->assertOk()
            ->assertJsonPath('data.items.0.quantity', '2.000')
            ->assertJsonPath('data.items.0.total', '290000.00');
        $this->patchJson("/api/consultation-items/{$id}", ['quantity' => 1])->assertOk()->assertJsonPath('data.items.0.total', '140000.00');
        $this->patchJson("/api/consultation-items/{$id}", ['discount' => 150001])->assertUnprocessable()->assertJsonValidationErrors(['discount']);
        $this->patchJson("/api/consultation-items/{$id}", ['service_id' => $this->service->id])->assertUnprocessable()->assertJsonValidationErrors(['service_id']);

        $this->deleteJson("/api/consultation-items/{$id}")->assertOk()->assertJsonCount(0, 'data.items');
        $this->assertSame(0, DB::table('consultation_items')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'consultation_item')->where('event', 'deleted')->count());
    }

    public static function notEditable(): array
    {
        return ['cerrada' => ['closed'], 'anulada' => ['cancelled']];
    }

    #[DataProvider('notEditable')]
    public function test_a_closed_or_cancelled_consultation_takes_no_items(string $status): void
    {
        $this->add()->assertCreated();
        $id = $this->itemId();
        DB::table('consultations')->where('id', $this->consultation)->update(['status' => $status]);

        $this->assertErrorCode($this->add(), 422, 'CONSULTATION_NOT_EDITABLE');
        $this->assertErrorCode($this->patchJson("/api/consultation-items/{$id}", ['quantity' => 3]), 422, 'CONSULTATION_NOT_EDITABLE');
        $this->assertErrorCode($this->deleteJson("/api/consultation-items/{$id}"), 422, 'CONSULTATION_NOT_EDITABLE');
        $this->assertSame(1, DB::table('consultation_items')->where('quantity', 1)->count());
    }

    public function test_the_administrator_adds_and_another_veterinarian_cannot(): void
    {
        $this->add()->assertCreated();
        $id = $this->itemId();
        $this->actAsVet($this->veterinarianIn($this->branch));

        $this->assertErrorCode($this->add(), 403, 'CONSULTATION_NOT_YOURS');
        $this->assertErrorCode($this->patchJson("/api/consultation-items/{$id}", ['quantity' => 3]), 403, 'CONSULTATION_NOT_YOURS');
        $this->assertErrorCode($this->deleteJson("/api/consultation-items/{$id}"), 403, 'CONSULTATION_NOT_YOURS');

        $this->actAsAdmin();
        $this->add()->assertCreated()->assertJsonCount(2, 'data.items');
    }

    public function test_the_reception_sees_the_items_but_does_not_write(): void
    {
        $this->add()->assertCreated();
        $id = $this->itemId();
        $this->actAsReceptionist();

        $this->getJson("/api/consultations/{$this->consultation}")->assertOk()
            ->assertJsonPath('data.items.0.total', '150000.00')
            ->assertJsonMissingPath('data.diagnosis');
        $this->assertErrorCode($this->add(), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->patchJson("/api/consultation-items/{$id}", ['quantity' => 3]), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->deleteJson("/api/consultation-items/{$id}"), 403, 'FORBIDDEN');
    }

    /**
     * El uso del servicio (FASE 2, §6.7): usado en una consulta, no cambia de nombre (422
     * SERVICE_NAME_NOT_EDITABLE) ni se elimina (409 SERVICE_IN_USE); el ítem guarda su nombre y su precio.
     */
    public function test_a_used_service_keeps_its_name_and_the_item_its_price(): void
    {
        $this->add()->assertCreated();
        $this->actAsAdmin();

        $this->assertErrorCode($this->patchJson("/api/services/{$this->service->id}", ['name' => 'Otra consulta']), 422, 'SERVICE_NAME_NOT_EDITABLE');
        $this->assertErrorCode($this->deleteJson("/api/services/{$this->service->id}"), 409, 'SERVICE_IN_USE');

        $this->patchJson("/api/services/{$this->service->id}", ['standard_price' => 180000])->assertOk();
        $this->getJson("/api/consultations/{$this->consultation}")->assertOk()->assertJsonPath('data.items.0.unit_price', '150000.00');
    }
}
