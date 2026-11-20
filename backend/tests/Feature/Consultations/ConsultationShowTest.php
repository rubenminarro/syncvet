<?php

namespace Tests\Feature\Consultations;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * El detalle de una consulta (FASE 4, entrega 4.1): la vista completa del administrador y del
 * veterinario (consultations.show-clinical; ajuste de R-099) y la administrativa de la recepción,
 * sin los textos clínicos ni el examen; una de otra clínica, 404.
 */
class ConsultationShowTest extends TestCase
{
    use InteractsWithConsultations;

    private const CLINICAL = [
        'diagnosis', 'history', 'weight_kg', 'temperature_c', 'heart_rate', 'respiratory_rate', 'mucous_membranes',
        'capillary_refill_time', 'body_condition_score', 'physical_exam', 'treatment', 'instructions',
    ];

    private int $consultation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
        $this->actAsVet();
        $this->consultation = $this->opened([
            'appointment_id' => $this->checkedIn(),
            'history' => 'Anamnesis', 'weight_kg' => 8.5, 'diagnosis' => 'Otitis', 'treatment' => 'Gotas', 'instructions' => 'Limpiar',
        ]);
    }

    private function show(): array
    {
        return $this->getJson("/api/consultations/{$this->consultation}")->assertOk()->json('data');
    }

    public function test_the_administrator_sees_the_clinical_content(): void
    {
        $this->actAsAdmin();
        $data = $this->show();

        foreach (self::CLINICAL as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $this->assertSame('Otitis', $data['diagnosis']);
        $this->assertSame('8.50', $data['weight_kg']);
        $this->assertSame('Limpiar', $data['instructions']);
    }

    /**
     * Ajuste de R-099: el permiso, también en el rol (no solo por Gate::before), y la vista lo
     * consulta con can(): sin el permiso en su rol, el administrador sigue viéndolo.
     */
    public function test_the_administrator_role_has_the_permission_and_the_view_goes_through_the_gate(): void
    {
        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('administrador', 'api')->hasPermissionTo('consultations.show-clinical'));

        \Spatie\Permission\Models\Role::findByName('administrador', 'api')->revokePermissionTo('consultations.show-clinical');
        $this->actAsAdmin();

        $this->assertSame('Otitis', $this->show()['diagnosis']);
    }

    public function test_any_veterinarian_of_the_clinic_sees_the_clinical_content(): void
    {
        $this->actAsVet($this->veterinarianIn($this->branch));

        $this->assertSame('Anamnesis', $this->show()['history']);
    }

    public function test_the_reception_sees_only_the_administrative_part(): void
    {
        $this->actAsReceptionist();
        $data = $this->show();

        foreach (self::CLINICAL as $key) {
            $this->assertArrayNotHasKey($key, $data, "La recepción recibió {$key}.");
        }

        $this->assertSame('CON-000001', $data['code']);
        $this->assertSame('Control anual', $data['reason']);
        $this->assertSame('attended', $data['appointment']['status']['value']);
        $this->assertArrayHasKey('created_by', $data);
    }

    public function test_a_role_without_the_permission_gets_the_administrative_view(): void
    {
        $this->actingIn($this->clinic, ['consultations.show']);

        $this->assertArrayNotHasKey('diagnosis', $this->show());

        $this->actingIn($this->clinic, ['consultations.show', 'consultations.show-clinical']);
        $this->assertSame('Otitis', $this->show()['diagnosis']);
    }

    public function test_a_consultation_of_another_clinic_is_not_found(): void
    {
        DB::table('consultations')->where('id', $this->consultation)->update(['clinic_id' => $this->createClinic()->id]);
        $this->actAsAdmin();

        $this->assertErrorCode($this->getJson("/api/consultations/{$this->consultation}"), 404, 'RESOURCE_NOT_FOUND');
        $this->assertErrorCode($this->patchJson("/api/consultations/{$this->consultation}", ['diagnosis' => 'X']), 404, 'RESOURCE_NOT_FOUND');
        $this->assertSame('Otitis', $this->consultationRow($this->consultation)->diagnosis);
    }
}
