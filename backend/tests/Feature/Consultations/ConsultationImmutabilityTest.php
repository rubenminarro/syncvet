<?php

namespace Tests\Feature\Consultations;

use App\Models\Service;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * La inmutabilidad (FASE 4, entregas 4.2 y 4.3; D4): cada ruta de escritura de la consulta con una
 * cerrada o una anulada responde 422 y no cambia nada, ni la consulta ni sus ítems. Editar (PATCH y
 * PUT) y los ítems (sumar, editar y quitar, 4.3), 422 CONSULTATION_NOT_EDITABLE
 * (ConsultationService::lockEditable, el lugar único); cerrar, 422 CONSULTATION_NOT_CLOSABLE.
 * Enmendar y anular, en sus tests.
 */
class ConsultationImmutabilityTest extends TestCase
{
    use InteractsWithConsultations;

    public static function writes(): array
    {
        $cases = [];

        foreach (['closed', 'cancelled'] as $status) {
            $cases["PATCH {$status}"] = [$status, 'patch', '/api/consultations/{id}', 'CONSULTATION_NOT_EDITABLE'];
            $cases["PUT {$status}"] = [$status, 'put', '/api/consultations/{id}', 'CONSULTATION_NOT_EDITABLE'];
            $cases["close {$status}"] = [$status, 'post', '/api/consultations/{id}/close', 'CONSULTATION_NOT_CLOSABLE'];
            $cases["sumar un ítem {$status}"] = [$status, 'post', '/api/consultations/{id}/items', 'CONSULTATION_NOT_EDITABLE'];
            $cases["editar un ítem {$status}"] = [$status, 'patch', '/api/consultation-items/{item}', 'CONSULTATION_NOT_EDITABLE'];
            $cases["quitar un ítem {$status}"] = [$status, 'delete', '/api/consultation-items/{item}', 'CONSULTATION_NOT_EDITABLE'];
        }

        return $cases;
    }

    #[DataProvider('writes')]
    public function test_a_closed_or_cancelled_consultation_does_not_change(string $status, string $method, string $uri, string $errorCode): void
    {
        $this->setUpClinic();
        $service = CurrentClinic::run($this->clinic, fn () => Service::create(['code' => 'SRV-000001', 'name' => 'Consulta general', 'standard_price' => '150000']));
        $this->actAsVet();
        $id = $this->opened(['appointment_id' => $this->checkedIn(), 'diagnosis' => 'Otitis']);
        $this->postJson("/api/consultations/{$id}/items", ['service_id' => $service->id, 'quantity' => 1])->assertCreated();
        $item = (int) DB::table('consultation_items')->value('id');
        DB::table('consultations')->where('id', $id)->update(['status' => $status]);
        $before = [(array) $this->consultationRow($id), DB::table('consultation_items')->get()->map(fn ($row) => (array) $row)->all()];
        $this->actAsAdmin();

        // Un cuerpo válido para cada ruta: editar un ítem no acepta service_id.
        $body = str_contains($uri, '{item}') ? ['quantity' => 3] : ['diagnosis' => 'Cambiado', 'service_id' => $service->id, 'quantity' => 3];
        $response = $this->json($method, str_replace(['{id}', '{item}'], [$id, $item], $uri), $body);

        $this->assertErrorCode($response, 422, $errorCode);
        $this->assertSame($before, [(array) $this->consultationRow($id), DB::table('consultation_items')->get()->map(fn ($row) => (array) $row)->all()]);
    }
}
