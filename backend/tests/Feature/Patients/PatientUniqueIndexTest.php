<?php

namespace Tests\Feature\Patients;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Services\PatientService;
use App\Support\CurrentClinic;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Un alta o una edición que pasa la validación y choca después con el índice único del
 * microchip (otro pedido guardó el mismo microchip en el medio) responde 422 en microchip,
 * con el mensaje de la regla unique, nunca 500 (deuda 22, como ClientUniqueIndexTest). El
 * choque se arma con un oyente del modelo que guarda el duplicado justo antes del INSERT o
 * del UPDATE, después de la validación del Form Request. Las dos altas simultáneas de verdad,
 * en PatientConcurrencyTest (MySQL).
 */
class PatientUniqueIndexTest extends TestCase
{
    use InteractsWithAuth;

    private const MESSAGE = 'Este microchip ya está registrado en otro paciente.';

    private Clinic $clinic;

    private Client $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        $this->owner = $this->clientIn($this->clinic);
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    public function test_an_insert_that_hits_the_microchip_index_responds_422_in_microchip(): void
    {
        $this->beforeWriting('creating', ['microchip' => '985112003456789']);

        $response = $this->postJson("/api/clients/{$this->owner->id}/patients", $this->patientPayload([
            'microchip' => '985112003456789',
        ]));

        $this->assertMicrochipError($response);
        // El duplicado del oyente entra en la transacción del alta y se revierte con ella.
        $this->assertSame(0, $this->patientsInClinic(), 'El alta no se guardó.');
    }

    public function test_an_update_that_hits_the_microchip_index_responds_422_in_microchip(): void
    {
        $patient = $this->patientOf($this->owner, ['microchip' => 'ANTES1']);
        $this->beforeWriting('updating', ['microchip' => '985112003456789']);

        $response = $this->patchJson("/api/patients/{$patient->id}", ['microchip' => '985112003456789']);

        $this->assertMicrochipError($response);
        $this->assertSame('ANTES1', $patient->fresh()->microchip, 'La edición no se guardó.');
    }

    /**
     * Otra violación sigue su camino, aunque el pedido traiga un microchip. Por la API no hay
     * otra que llegue (el código no se edita y su alta la traduce la serie): se prueba en el
     * servicio, con una edición que repite el código de otro paciente.
     */
    public function test_another_unique_violation_is_not_translated(): void
    {
        $other = $this->patientOf($this->owner, ['code' => 'PAC-000009']);
        $patient = $this->patientOf($this->owner);

        $this->expectException(UniqueConstraintViolationException::class);

        CurrentClinic::run($this->clinic, fn () => app(PatientService::class)->update($patient, [
            'code' => $other->code,
            'microchip' => '985112003456789',
        ]));
    }

    /**
     * La violación del código en el alta la traduce la serie (409), no esta regla.
     */
    public function test_a_code_violation_still_responds_document_code_conflict(): void
    {
        $this->beforeWriting('creating', ['code' => 'PAC-000001']);

        $this->postJson("/api/clients/{$this->owner->id}/patients", $this->patientPayload(['microchip' => '985112003456789']))
            ->assertConflict()
            ->assertJsonPath('error_code', 'DOCUMENT_CODE_CONFLICT')
            ->assertJsonMissingPath('errors.microchip');
    }

    /**
     * Guarda otro paciente con $attributes (sin el modelo, como otro pedido) en el evento
     * $event del próximo paciente: ya pasó la validación y todavía no escribió.
     */
    private function beforeWriting(string $event, array $attributes): void
    {
        $done = false;

        Patient::{$event}(function () use (&$done, $attributes) {
            if ($done) {
                return;
            }

            $done = true;

            DB::table('patients')->insert([
                'clinic_id' => $this->clinic->id,
                'client_id' => $this->owner->id,
                'code' => 'OTR-000001',
                'name' => 'Otro pedido',
                'species_id' => $this->globalSpeciesId(),
                'created_at' => now(),
                'updated_at' => now(),
                ...$attributes,
            ]);
        });
    }

    private function patientsInClinic(): int
    {
        return DB::table('patients')->where('clinic_id', $this->clinic->id)->count();
    }

    private function assertMicrochipError(TestResponse $response): void
    {
        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.microchip.0', self::MESSAGE);
    }
}
