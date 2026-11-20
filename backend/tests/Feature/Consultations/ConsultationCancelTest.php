<?php

namespace Tests\Feature\Consultations;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Anular una consulta (FASE 4, entrega 4.2; pregunta 12): solo el administrador, con motivo,
 * abierta o cerrada (anulada, 422 CONSULTATION_NOT_CANCELLABLE); queda visible con su contenido; sus
 * ítems pendientes pasan a no_charge; el turno sigue atendido.
 */
class ConsultationCancelTest extends TestCase
{
    use InteractsWithConsultations;

    private int $appointment;

    private int $consultation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
        $this->appointment = $this->checkedIn();
        $this->actAsVet();
        $this->consultation = $this->opened(['appointment_id' => $this->appointment, 'diagnosis' => 'Otitis']);
    }

    private function cancel(array $body = ['cancellation_reason' => 'Paciente equivocado'])
    {
        return $this->postJson("/api/consultations/{$this->consultation}/cancel", $body);
    }

    private function item(string $billingStatus): int
    {
        return DB::table('consultation_items')->insertGetId([
            'clinic_id' => $this->clinic->id,
            'consultation_id' => $this->consultation,
            'type' => 'service',
            'description' => 'Consulta',
            'quantity' => '1.000',
            'unit_price' => '150000.00',
            'discount' => '0.00',
            'total' => '150000.00',
            'tax_rate' => '10',
            'billing_status' => $billingStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function cancellable(): array
    {
        return ['abierta' => ['open'], 'cerrada' => ['closed']];
    }

    #[DataProvider('cancellable')]
    public function test_the_administrator_cancels_an_open_or_closed_one(string $status): void
    {
        DB::table('consultations')->where('id', $this->consultation)->update(['status' => $status]);
        $pending = $this->item('pending');
        $admin = $this->actAsAdmin();

        $this->cancel()->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled')
            ->assertJsonPath('data.cancelled_at', '2026-11-10T12:00:00.000000Z')
            ->assertJsonPath('data.cancelled_by.id', $admin->id)
            ->assertJsonPath('data.cancellation_reason', 'Paciente equivocado')
            // Visible, con todo su contenido.
            ->assertJsonPath('data.diagnosis', 'Otitis');

        $this->assertSame('no_charge', DB::table('consultation_items')->where('id', $pending)->value('billing_status'));
        $this->assertSame('attended', $this->appointmentRow($this->appointment)->status);
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'consultation_item')->where('auditable_id', $pending)->count());
    }

    public function test_an_invoiced_item_is_not_touched(): void
    {
        $invoiced = $this->item('invoiced');
        $this->actAsAdmin();

        $this->cancel()->assertOk();

        $this->assertSame('invoiced', DB::table('consultation_items')->where('id', $invoiced)->value('billing_status'));
    }

    public function test_a_cancelled_one_is_not_cancelled_again(): void
    {
        $this->actAsAdmin();
        $this->cancel()->assertOk();

        $this->assertErrorCode($this->cancel(['cancellation_reason' => 'Otra vez']), 422, 'CONSULTATION_NOT_CANCELLABLE');
        $this->assertSame('Paciente equivocado', $this->consultationRow($this->consultation)->cancellation_reason);
    }

    public static function invalidReason(): array
    {
        return ['sin motivo' => [null], 'de 2' => ['ab'], 'de 501' => [str_repeat('a', 501)]];
    }

    #[DataProvider('invalidReason')]
    public function test_the_reason_is_required(?string $reason): void
    {
        $this->actAsAdmin();

        $this->cancel(['cancellation_reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors(['cancellation_reason']);
        $this->assertSame('open', $this->consultationRow($this->consultation)->status);
    }

    public function test_the_veterinarian_and_the_reception_cannot_cancel(): void
    {
        $this->actAsVet();
        $this->assertErrorCode($this->cancel(), 403, 'FORBIDDEN');

        $this->actAsReceptionist();
        $this->assertErrorCode($this->cancel(), 403, 'FORBIDDEN');

        $this->assertSame('open', $this->consultationRow($this->consultation)->status);
    }
}
