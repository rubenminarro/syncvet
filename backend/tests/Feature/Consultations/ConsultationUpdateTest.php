<?php

namespace Tests\Feature\Consultations;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Editar una consulta (FASE 4, entrega 4.1): parcial, solo abierta (422
 * CONSULTATION_NOT_EDITABLE), su veterinario o el administrador (otro veterinario, 403
 * CONSULTATION_NOT_YOURS, antes que el estado), los límites de la pregunta 13 y consulted_at.
 */
class ConsultationUpdateTest extends TestCase
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
        $this->consultation = $this->opened(['appointment_id' => $this->appointment, 'reason' => 'Vómitos', 'weight_kg' => 12.4]);
    }

    private function edit(array $body)
    {
        return $this->patchJson("/api/consultations/{$this->consultation}", $body);
    }

    public function test_the_veterinarian_edits_only_what_arrives(): void
    {
        $this->edit([
            'history' => 'Vómitos desde ayer.',
            'temperature_c' => 39.2,
            'heart_rate' => 120,
            'respiratory_rate' => 30,
            'mucous_membranes' => 'Rosadas',
            'capillary_refill_time' => '< 2 s',
            'body_condition_score' => 5,
            'physical_exam' => 'Abdomen doloroso.',
            'diagnosis' => 'Gastroenteritis.',
            'treatment' => 'Omeprazol.',
            'instructions' => 'Dieta blanda por 3 días.',
        ])->assertOk()
            ->assertJsonPath('data.reason', 'Vómitos')
            ->assertJsonPath('data.weight_kg', '12.40')
            ->assertJsonPath('data.temperature_c', '39.2')
            ->assertJsonPath('data.heart_rate', 120)
            ->assertJsonPath('data.body_condition_score', 5)
            ->assertJsonPath('data.diagnosis', 'Gastroenteritis.')
            ->assertJsonPath('data.treatment', 'Omeprazol.')
            ->assertJsonPath('data.instructions', 'Dieta blanda por 3 días.');

        $this->edit(['reason' => null])->assertOk()->assertJsonPath('data.reason', null)->assertJsonPath('data.diagnosis', 'Gastroenteritis.');
    }

    public function test_the_administrator_edits_any_consultation(): void
    {
        $this->actAsAdmin();

        $this->edit(['diagnosis' => 'Revisado por la dirección.'])->assertOk()->assertJsonPath('data.diagnosis', 'Revisado por la dirección.');
    }

    public function test_another_veterinarian_cannot_edit_it(): void
    {
        $this->actAsVet($this->veterinarianIn($this->branch));

        $this->assertErrorCode($this->edit(['diagnosis' => 'Ajeno']), 403, 'CONSULTATION_NOT_YOURS');
        $this->assertNull($this->consultationRow($this->consultation)->diagnosis);
    }

    public function test_a_closed_or_cancelled_consultation_is_not_editable(): void
    {
        foreach (['closed', 'cancelled'] as $status) {
            DB::table('consultations')->where('id', $this->consultation)->update(['status' => $status]);

            $this->assertErrorCode($this->edit(['diagnosis' => 'Tarde']), 422, 'CONSULTATION_NOT_EDITABLE');
        }

        // La ficha va antes que el estado: un veterinario ajeno no averigua que está cerrada.
        $this->actAsVet($this->veterinarianIn($this->branch));
        $this->assertErrorCode($this->edit(['diagnosis' => 'Ajeno']), 403, 'CONSULTATION_NOT_YOURS');
        $this->assertNull($this->consultationRow($this->consultation)->diagnosis);
    }

    public static function outOfRange(): array
    {
        return [
            'peso 0' => ['weight_kg', 0],
            'peso 10000' => ['weight_kg', 10000],
            'peso con 3 decimales' => ['weight_kg', 1.234],
            'temperatura 29.9' => ['temperature_c', 29.9],
            'temperatura 45.1' => ['temperature_c', 45.1],
            'temperatura con 2 decimales' => ['temperature_c', 38.25],
            'cardíaca 0' => ['heart_rate', 0],
            'cardíaca 401' => ['heart_rate', 401],
            'respiratoria 201' => ['respiratory_rate', 201],
            'condición 0' => ['body_condition_score', 0],
            'condición 10' => ['body_condition_score', 10],
            'motivo de 256' => ['reason', str_repeat('a', 256)],
            'diagnóstico de 10001' => ['diagnosis', str_repeat('ñ', 10001)],
            'mucosas de 51' => ['mucous_membranes', str_repeat('a', 51)],
            'llenado de 21' => ['capillary_refill_time', str_repeat('a', 21)],
            'fecha null' => ['consulted_at', null],
            'fecha mal formada' => ['consulted_at', '10/11/2026'],
        ];
    }

    #[DataProvider('outOfRange')]
    public function test_limits(string $field, mixed $value): void
    {
        $this->edit([$field => $value])->assertUnprocessable()->assertJsonValidationErrors([$field]);
    }

    public function test_limits_at_the_edges_are_accepted(): void
    {
        $this->edit([
            'weight_kg' => 9999.99, 'temperature_c' => 30, 'heart_rate' => 400, 'respiratory_rate' => 1,
            'body_condition_score' => 9, 'diagnosis' => str_repeat('ñ', 10000),
        ])->assertOk();

        $this->edit(['temperature_c' => 45])->assertOk()->assertJsonPath('data.temperature_c', '45.0');
    }

    public function test_consulted_at_is_not_future_nor_before_the_check_in(): void
    {
        $this->edit(['consulted_at' => '2026-11-10T09:00:01'])->assertUnprocessable()->assertJsonValidationErrors(['consulted_at']);
        $this->edit(['consulted_at' => '2026-11-10T08:39:59'])
            ->assertUnprocessable()->assertJsonPath('errors.consulted_at.0', 'La fecha de la consulta no puede ser anterior al fichado del turno.');

        $this->edit(['consulted_at' => '2026-11-10T08:40:00'])->assertOk()->assertJsonPath('data.consulted_at', '2026-11-10T11:40:00.000000Z');
    }

    public function test_fixed_fields_cannot_be_changed(): void
    {
        $this->edit(['patient_id' => 1, 'veterinarian_id' => 1, 'status' => 'closed', 'appointment_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_id', 'veterinarian_id', 'status', 'appointment_id']);
    }

    public function test_the_reception_cannot_edit(): void
    {
        $this->actAsReceptionist();

        $this->assertErrorCode($this->edit(['diagnosis' => 'X']), 403, 'FORBIDDEN');
    }

    public function test_delete_is_not_allowed(): void
    {
        $this->deleteJson("/api/consultations/{$this->consultation}")->assertStatus(405);
        $this->assertSame(1, DB::table('consultations')->count());
    }
}
