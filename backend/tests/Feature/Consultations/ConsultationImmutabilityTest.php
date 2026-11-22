<?php

namespace Tests\Feature\Consultations;

use App\Models\Service;
use App\Services\ConsultationAttachmentService;
use App\Support\CurrentClinic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * La inmutabilidad (FASE 4, entregas 4.2 a 4.4; D4): cada ruta de escritura de la consulta con una
 * cerrada o una anulada responde 422 y no cambia nada, ni la consulta, ni sus ítems, ni sus
 * aplicaciones. Editar (PATCH y PUT), los ítems (sumar, editar y quitar, 4.3) y las aplicaciones (cargar
 * con la consulta y anular una de ella, 4.4) y los adjuntos (subir, sin dejar el archivo, y quitar,
 * 4.5), 422 CONSULTATION_NOT_EDITABLE
 * (ConsultationService::lockEditable y ensureEditable, el lugar único); cerrar, 422
 * CONSULTATION_NOT_CLOSABLE. Una aplicación de una anulada ya está anulada (422
 * PATIENT_APPLICATION_NOT_CANCELLABLE, en PatientApplicationTest). Enmendar y anular la consulta, en sus
 * tests.
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
            $cases["cargar una aplicación {$status}"] = [$status, 'post', '/api/patients/{patient}/applications', 'CONSULTATION_NOT_EDITABLE'];
            // Entrega 4.5: subir un adjunto (el archivo ya escrito se borra) y quitar uno.
            $cases["subir un adjunto {$status}"] = [$status, 'post', '/api/consultations/{id}/attachments', 'CONSULTATION_NOT_EDITABLE'];
            $cases["quitar un adjunto {$status}"] = [$status, 'delete', '/api/consultation-attachments/{attachment}', 'CONSULTATION_NOT_EDITABLE'];
        }

        $cases['anular una aplicación closed'] = ['closed', 'post', '/api/patient-applications/{application}/cancel', 'CONSULTATION_NOT_EDITABLE'];

        return $cases;
    }

    #[DataProvider('writes')]
    public function test_a_closed_or_cancelled_consultation_does_not_change(string $status, string $method, string $uri, string $errorCode): void
    {
        $this->setUpClinic();
        $service = CurrentClinic::run($this->clinic, fn () => Service::create(['code' => 'SRV-000001', 'name' => 'Consulta general', 'standard_price' => '150000']));
        $this->actAsVet();
        $appointment = $this->checkedIn();
        $patient = $this->patientIdOf($appointment);
        $id = $this->opened(['appointment_id' => $appointment, 'diagnosis' => 'Otitis']);
        $this->postJson("/api/consultations/{$id}/items", ['service_id' => $service->id, 'quantity' => 1])->assertCreated();
        $application = $this->postJson("/api/patients/{$patient}/applications", [
            'type' => 'vaccine', 'name' => 'Antirrábica', 'applied_at' => '2026-11-10T08:50:00', 'consultation_id' => $id,
        ])->assertCreated()->json('data.id');
        $item = (int) DB::table('consultation_items')->value('id');
        Storage::fake(ConsultationAttachmentService::DISK);
        $attachment = $this->post("/api/consultations/{$id}/attachments", ['file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.attachments.0.id');
        DB::table('consultations')->where('id', $id)->update(['status' => $status]);
        $snapshot = fn () => [
            (array) $this->consultationRow($id),
            DB::table('consultation_items')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('patient_applications')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('consultation_attachments')->get()->map(fn ($row) => (array) $row)->all(),
            Storage::disk(ConsultationAttachmentService::DISK)->allFiles(),
        ];
        $before = $snapshot();
        $this->actAsAdmin();

        // Un cuerpo válido para cada ruta: editar un ítem no acepta service_id.
        $body = match (true) {
            str_contains($uri, '{item}') => ['quantity' => 3],
            str_contains($uri, '{application}') => ['cancellation_reason' => 'Por error'],
            str_contains($uri, '{patient}') => ['type' => 'vaccine', 'name' => 'Otra', 'applied_at' => '2026-11-10T08:55:00', 'consultation_id' => $id],
            default => ['diagnosis' => 'Cambiado', 'service_id' => $service->id, 'quantity' => 3],
        };
        $uri = str_replace(['{id}', '{item}', '{patient}', '{application}', '{attachment}'], [$id, $item, $patient, $application, $attachment], $uri);
        $response = str_ends_with($uri, '/attachments')
            ? $this->post($uri, ['file' => $this->pdf()], ['Accept' => 'application/json'])
            : $this->json($method, $uri, $body);

        $this->assertErrorCode($response, 422, $errorCode);
        $this->assertSame($before, $snapshot());
    }

    private function pdf(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'adj');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return new UploadedFile($path, 'estudio.pdf', 'application/pdf', null, true);
    }
}
