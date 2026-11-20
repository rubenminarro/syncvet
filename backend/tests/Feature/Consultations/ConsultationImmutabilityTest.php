<?php

namespace Tests\Feature\Consultations;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * La inmutabilidad (FASE 4, entrega 4.2; D4): cada ruta de escritura de la consulta con una cerrada o
 * una anulada responde 422 y no cambia nada. Editar (PATCH y PUT), 422 CONSULTATION_NOT_EDITABLE
 * (ConsultationService::lockEditable, el lugar que reusan la 4.3 a la 4.5); cerrar, 422
 * CONSULTATION_NOT_CLOSABLE. Enmendar y anular, en sus tests.
 */
class ConsultationImmutabilityTest extends TestCase
{
    use InteractsWithConsultations;

    public static function writes(): array
    {
        $cases = [];

        foreach (['closed', 'cancelled'] as $status) {
            $cases["PATCH {$status}"] = [$status, 'patch', '', 'CONSULTATION_NOT_EDITABLE'];
            $cases["PUT {$status}"] = [$status, 'put', '', 'CONSULTATION_NOT_EDITABLE'];
            $cases["close {$status}"] = [$status, 'post', '/close', 'CONSULTATION_NOT_CLOSABLE'];
        }

        return $cases;
    }

    #[DataProvider('writes')]
    public function test_a_closed_or_cancelled_consultation_does_not_change(string $status, string $method, string $action, string $errorCode): void
    {
        $this->setUpClinic();
        $this->actAsVet();
        $id = $this->opened(['appointment_id' => $this->checkedIn(), 'diagnosis' => 'Otitis']);
        DB::table('consultations')->where('id', $id)->update(['status' => $status]);
        $before = (array) $this->consultationRow($id);
        $this->actAsAdmin();

        $this->assertErrorCode($this->json($method, "/api/consultations/{$id}{$action}", ['diagnosis' => 'Cambiado']), 422, $errorCode);

        $this->assertSame($before, (array) $this->consultationRow($id));
    }
}
