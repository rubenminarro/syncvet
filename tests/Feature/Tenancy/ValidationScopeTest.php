<?php

namespace Tests\Feature\Tenancy;

use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * Las reglas unique y exists de los Form Requests filtran por la clínica actual:
 * el mismo documento, email o microchip puede estar en dos clínicas (R-005), y
 * un id de otra clínica no "existe".
 */
class ValidationScopeTest extends TestCase
{
    use InteractsWithClinics;

    private const PERMISSIONS = ['clients.store', 'clients.update', 'patients.store', 'patients.update', 'patients.change-owner'];

    public function test_client_document_and_email_are_unique_per_clinic_on_create(): void
    {
        $own = $this->createClinic();
        $this->clientIn($this->createClinic(), ['document_number' => '4567890', 'email' => 'juan@example.com']);
        $this->actingIn($own, self::PERMISSIONS);

        // Libres en esta clínica, aunque otra los use.
        $this->postJson('/api/clients', $this->clientPayload())->assertCreated();

        // Repetidos dentro de la misma clínica.
        $response = $this->postJson('/api/clients', $this->clientPayload());

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['document_number', 'email']);
    }

    public function test_client_document_and_email_are_unique_per_clinic_on_update(): void
    {
        $own = $this->createClinic();
        $this->clientIn($this->createClinic(), ['document_number' => '4567890', 'email' => 'juan@example.com']);
        $taken = $this->clientIn($own, ['document_number' => '7777777', 'email' => 'tomado@example.com']);
        $client = $this->clientIn($own);
        $this->actingIn($own, self::PERMISSIONS);

        $this->patchJson("/api/clients/{$client->id}", ['document_number' => '4567890', 'email' => 'juan@example.com'])
            ->assertOk();

        $response = $this->patchJson("/api/clients/{$client->id}", [
            'document_number' => $taken->document_number,
            'email' => $taken->email,
        ]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['document_number', 'email']);
    }

    public function test_microchip_is_unique_per_clinic_on_create(): void
    {
        $own = $this->createClinic();
        $this->patientOf($this->clientIn($this->createClinic()), ['microchip' => 'CHIP123']);
        $client = $this->clientIn($own);
        $this->actingIn($own, self::PERMISSIONS);
        $payload = ['name' => 'Toby', 'species_id' => $this->globalSpeciesId(), 'microchip' => 'CHIP123'];

        $this->postJson("/api/clients/{$client->id}/patients", $payload)->assertCreated();

        $response = $this->postJson("/api/clients/{$client->id}/patients", $payload);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['microchip']);
    }

    public function test_microchip_is_unique_per_clinic_on_update(): void
    {
        $own = $this->createClinic();
        $this->patientOf($this->clientIn($this->createClinic()), ['microchip' => 'CHIP123']);
        $client = $this->clientIn($own);
        $this->patientOf($client, ['microchip' => 'CHIP999']);
        $patient = $this->patientOf($client);
        $this->actingIn($own, self::PERMISSIONS);

        $this->patchJson("/api/patients/{$patient->id}", ['microchip' => 'CHIP123'])->assertOk();

        $response = $this->patchJson("/api/patients/{$patient->id}", ['microchip' => 'CHIP999']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['microchip']);
    }

    public function test_change_owner_rejects_a_client_of_another_clinic(): void
    {
        $own = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($own));
        $foreignClient = $this->clientIn($this->createClinic());
        $this->actingIn($own, self::PERMISSIONS);

        $response = $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $foreignClient->id]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['client_id']);
        $this->assertSame($patient->client_id, $patient->fresh()->client_id);
    }

    public function test_change_owner_accepts_a_client_of_own_clinic(): void
    {
        $own = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($own));
        $newOwner = $this->clientIn($own);
        $this->actingIn($own, self::PERMISSIONS);

        $this->postJson("/api/patients/{$patient->id}/change-owner", ['client_id' => $newOwner->id])
            ->assertOk()
            ->assertJsonPath('data.client.id', $newOwner->id);
    }
}
