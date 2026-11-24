<?php

namespace Tests\Feature\Consultations;

use App\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Cerrar una consulta (FASE 4, entrega 4.2; preguntas 5, 8 y 14): su veterinario o el
 * administrador (otro, 403 CONSULTATION_NOT_YOURS); solo abierta (422 CONSULTATION_NOT_CLOSABLE); el
 * motivo y el diagnóstico obligatorios (422 CONSULTATION_CLOSE_INCOMPLETE, con errors por campo);
 * closed_at y closed_by; el peso al paciente, auditado (no a un fallecido).
 */
class ConsultationCloseTest extends TestCase
{
    use InteractsWithConsultations;

    private int $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
        $this->appointment = $this->checkedIn();
    }

    private function open(array $fields = []): int
    {
        $this->actAsVet();

        return $this->opened(['appointment_id' => $this->appointment, 'diagnosis' => 'Otitis', ...$fields]);
    }

    private function close(int $id)
    {
        return $this->postJson("/api/consultations/{$id}/close");
    }

    public function test_the_veterinarian_closes_it_and_the_weight_goes_to_the_patient(): void
    {
        $id = $this->open(['weight_kg' => 8.75]);
        $user = $this->actAsVet();

        $this->close($id)->assertOk()
            ->assertJsonPath('data.status.value', 'closed')
            ->assertJsonPath('data.closed_at', '2026-11-10T12:00:00.000000Z')
            ->assertJsonPath('data.closed_by.id', $user->id);

        $patientId = $this->patientIdOf($this->appointment);
        $this->assertEquals(8.75, (float) DB::table('patients')->where('id', $patientId)->value('weight_kg'));
        $this->assertSame(1, DB::table('audit_logs')
            ->where('auditable_type', 'patient')->where('auditable_id', $patientId)->where('event', AuditEvent::UPDATED->value)
            ->where('new_values', 'like', '%8.75%')->count());
        // El turno sigue atendido.
        $this->assertSame('attended', $this->appointmentRow($this->appointment)->status);
    }

    public function test_without_weight_the_patient_keeps_his(): void
    {
        $patientId = $this->patientIdOf($this->appointment);
        DB::table('patients')->where('id', $patientId)->update(['weight_kg' => '5.00']);
        $id = $this->open();

        $this->close($id)->assertOk();

        $this->assertEquals(5.0, (float) DB::table('patients')->where('id', $patientId)->value('weight_kg'));
    }

    public function test_a_deceased_patient_keeps_his_weight(): void
    {
        $id = $this->open(['weight_kg' => 8]);
        $patientId = $this->patientIdOf($this->appointment);
        DB::table('patients')->where('id', $patientId)->update(['weight_kg' => '5.00', 'deceased_at' => now()]);

        $this->close($id)->assertOk()->assertJsonPath('data.status.value', 'closed');

        $this->assertEquals(5.0, (float) DB::table('patients')->where('id', $patientId)->value('weight_kg'));
    }

    public static function incomplete(): array
    {
        return [
            'sin motivo' => [['reason' => null], ['reason']],
            'sin diagnóstico' => [['diagnosis' => null], ['diagnosis']],
            'sin los dos' => [['reason' => null, 'diagnosis' => null], ['reason', 'diagnosis']],
        ];
    }

    #[DataProvider('incomplete')]
    public function test_reason_and_diagnosis_are_required(array $fields, array $missing): void
    {
        $id = $this->open($fields);

        $response = $this->close($id);

        $this->assertErrorCode($response, 422, 'CONSULTATION_CLOSE_INCOMPLETE');
        $this->assertSame($missing, array_keys($response->json('errors')));
        $this->assertSame('open', $this->consultationRow($id)->status);
    }

    public function test_only_an_open_one_is_closed(): void
    {
        $id = $this->open();

        foreach (['closed', 'cancelled'] as $status) {
            DB::table('consultations')->where('id', $id)->update(['status' => $status]);

            $this->assertErrorCode($this->close($id), 422, 'CONSULTATION_NOT_CLOSABLE');
        }
    }

    public function test_the_administrator_closes_any_and_another_veterinarian_cannot(): void
    {
        $id = $this->open();
        $this->actAsVet($this->veterinarianIn($this->branch));

        $this->assertErrorCode($this->close($id), 403, 'CONSULTATION_NOT_YOURS');
        $this->assertSame('open', $this->consultationRow($id)->status);

        $admin = $this->actAsAdmin();
        $this->close($id)->assertOk()->assertJsonPath('data.closed_by.id', $admin->id);
    }

    public function test_the_reception_cannot_close(): void
    {
        $id = $this->open();
        $this->actAsReceptionist();

        $this->assertErrorCode($this->close($id), 403, 'FORBIDDEN');
    }
}
