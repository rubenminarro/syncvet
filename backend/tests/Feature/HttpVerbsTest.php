<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Patient;
use App\Support\CurrentClinic;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * PUT y PATCH (CLAUDE.md §3; FASE 1, entrega 1.1): apiResource registra los dos
 * verbos en la misma ruta, y los dos son parciales. Los campos que no se envían
 * no cambian. Con el rol recepcionista.
 */
class HttpVerbsTest extends TestCase
{
    use InteractsWithAuth;

    /**
     * @return array<string, array{0: string}>
     */
    public static function verbs(): array
    {
        return ['PUT' => ['PUT'], 'PATCH' => ['PATCH']];
    }

    #[DataProvider('verbs')]
    public function test_a_partial_client_update_keeps_the_fields_not_sent(string $verb): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic, ['city' => 'Luque', 'notes' => 'Cliente antiguo']);
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->json($verb, "/api/clients/{$client->id}", ['phone' => '+595981999999'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+595981999999');

        $fresh = CurrentClinic::run($clinic, fn () => Client::findOrFail($client->id));
        $this->assertSame('+595981999999', $fresh->phone);
        $this->assertSame($client->document_number, $fresh->document_number);
        $this->assertSame($client->email, $fresh->email);
        $this->assertSame('Ana', $fresh->first_name);
        $this->assertSame('Gómez', $fresh->last_name);
        $this->assertSame('Luque', $fresh->city);
        $this->assertSame('Cliente antiguo', $fresh->notes);
    }

    #[DataProvider('verbs')]
    public function test_a_partial_patient_update_keeps_the_fields_not_sent(string $verb): void
    {
        $clinic = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($clinic), ['color' => 'Negro', 'microchip' => 'ABC123', 'notes' => 'Tranquilo']);
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->json($verb, "/api/patients/{$patient->id}", ['weight_kg' => 12.5])
            ->assertOk()
            ->assertJsonPath('data.name', 'Paciente');

        $fresh = CurrentClinic::run($clinic, fn () => Patient::findOrFail($patient->id));
        $this->assertEquals(12.5, $fresh->weight_kg);
        $this->assertSame('Paciente', $fresh->name);
        $this->assertSame($patient->species_id, $fresh->species_id);
        $this->assertSame('Negro', $fresh->color);
        $this->assertSame('ABC123', $fresh->microchip);
        $this->assertSame('Tranquilo', $fresh->notes);
    }
}
