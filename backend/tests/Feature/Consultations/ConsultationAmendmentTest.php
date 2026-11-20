<?php

namespace Tests\Feature\Consultations;

use App\Exceptions\ConsultationException;
use App\Models\ConsultationAmendment;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * Las enmiendas (FASE 4, entrega 4.2; pregunta 4 y D4): solo de una cerrada (abierta o anulada,
 * 422 CONSULTATION_NOT_AMENDABLE), de su veterinario o el administrador (otro, 403
 * CONSULTATION_NOT_YOURS); content de 3 a 10000, reason de 3 a 500, field uno de los campos
 * clínicos o null; inmutables; en el detalle, solo con consultations.show-clinical.
 */
class ConsultationAmendmentTest extends TestCase
{
    use InteractsWithConsultations;

    private int $consultation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
        $this->actAsVet();
        $this->consultation = $this->opened(['appointment_id' => $this->checkedIn(), 'diagnosis' => 'Otitis']);
        $this->postJson("/api/consultations/{$this->consultation}/close")->assertOk();
    }

    private function amend(array $body)
    {
        return $this->postJson("/api/consultations/{$this->consultation}/amendments", $body);
    }

    private function valid(array $overrides = []): array
    {
        return ['field' => 'diagnosis', 'content' => 'Otitis externa bilateral.', 'reason' => 'Faltó el lado.', ...$overrides];
    }

    public function test_the_veterinarian_amends_a_closed_one_and_the_consultation_does_not_change(): void
    {
        $user = $this->actAsVet();

        $this->amend($this->valid())->assertCreated()
            ->assertJsonPath('data.diagnosis', 'Otitis')
            ->assertJsonPath('data.amendments.0.field', 'diagnosis')
            ->assertJsonPath('data.amendments.0.content', 'Otitis externa bilateral.')
            ->assertJsonPath('data.amendments.0.reason', 'Faltó el lado.')
            ->assertJsonPath('data.amendments.0.created_by.id', $user->id)
            ->assertJsonPath('data.amendments.0.created_at', '2026-11-10T12:00:00.000000Z');

        $this->amend($this->valid(['field' => null, 'content' => 'Nota general.']))->assertCreated()
            ->assertJsonPath('data.amendments.1.field', null)
            ->assertJsonCount(2, 'data.amendments');

        $this->assertSame('Otitis', $this->consultationRow($this->consultation)->diagnosis);
        $this->assertSame(2, DB::table('audit_logs')->where('auditable_type', 'consultation_amendment')->count());
    }

    public function test_the_administrator_amends_and_another_veterinarian_cannot(): void
    {
        $this->actAsVet($this->veterinarianIn($this->branch));
        $this->assertErrorCode($this->amend($this->valid()), 403, 'CONSULTATION_NOT_YOURS');

        $this->actAsAdmin();
        $this->amend($this->valid())->assertCreated();
        $this->assertSame(1, DB::table('consultation_amendments')->count());
    }

    public function test_only_a_closed_one_is_amended(): void
    {
        $this->actAsVet();

        foreach (['open', 'cancelled'] as $status) {
            DB::table('consultations')->where('id', $this->consultation)->update(['status' => $status]);

            $this->assertErrorCode($this->amend($this->valid()), 422, 'CONSULTATION_NOT_AMENDABLE');
        }

        $this->assertSame(0, DB::table('consultation_amendments')->count());
    }

    public static function invalid(): array
    {
        return [
            'sin contenido' => ['content', null],
            'contenido de 2' => ['content', 'ab'],
            'contenido de 10001' => ['content', str_repeat('ñ', 10001)],
            'sin motivo' => ['reason', null],
            'motivo de 2' => ['reason', 'ab'],
            'motivo de 501' => ['reason', str_repeat('a', 501)],
            'campo que no es clínico' => ['field', 'status'],
            'campo que no existe' => ['field', 'plan'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_validation(string $key, mixed $value): void
    {
        $this->actAsVet();

        $this->amend($this->valid([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors([$key]);
    }

    public function test_the_limits_are_accepted(): void
    {
        $this->actAsVet();

        $this->amend($this->valid(['content' => str_repeat('ñ', 10000), 'reason' => str_repeat('a', 500), 'field' => 'weight_kg']))->assertCreated();
    }

    public function test_the_reception_cannot_amend_nor_see_the_amendments(): void
    {
        $this->actAsVet();
        $this->amend($this->valid())->assertCreated();
        $this->actAsReceptionist();

        $this->assertErrorCode($this->amend($this->valid()), 403, 'FORBIDDEN');
        $this->assertArrayNotHasKey('amendments', $this->getJson("/api/consultations/{$this->consultation}")->assertOk()->json('data'));
    }

    public function test_an_amendment_cannot_be_changed_nor_deleted(): void
    {
        $this->actAsVet();
        $this->amend($this->valid())->assertCreated();

        CurrentClinic::run($this->clinic, function () {
            $amendment = ConsultationAmendment::firstOrFail();

            foreach ([fn () => $amendment->update(['content' => 'Otra']), fn () => $amendment->delete()] as $write) {
                try {
                    $write();
                    $this->fail('La enmienda cambió.');
                } catch (ConsultationException $e) {
                    $this->assertSame('CONSULTATION_AMENDMENT_IMMUTABLE', $e->getErrorCode());
                }
            }
        });

        $this->assertSame('Otitis externa bilateral.', DB::table('consultation_amendments')->value('content'));
    }
}
