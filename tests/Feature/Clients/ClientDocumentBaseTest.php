<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Documento único por número base (CLAUDE.md §6.1; R-018). En Paraguay el RUC de
 * una persona es su cédula más el dígito verificador: la cédula, la cédula con
 * ceros adelante y el RUC son el mismo documento.
 */
class ClientDocumentBaseTest extends TestCase
{
    use InteractsWithAuth;

    private const TAKEN = 'Este número de documento ya está en uso.';

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sameDocument(): array
    {
        return [
            'cédula y la misma con un cero' => ['99999903', '099999903'],
            'cédula y su RUC' => ['99999903', '99999903-6'],
            'RUC y su cédula' => ['99999903-6', '99999903'],
            'RUC y la cédula con un cero' => ['99999903-6', '099999903'],
            'letras en otra caja' => ['AB-12345', 'ab-12345'],
        ];
    }

    #[DataProvider('sameDocument')]
    public function test_the_same_document_in_another_form_is_taken_on_create(string $existing, string $new): void
    {
        $this->clientIn($this->clinic, ['document_number' => $existing]);

        $this->assertTaken($this->postJson('/api/clients', $this->clientPayload(['document_number' => $new])));
        $this->assertSame(1, CurrentClinic::run($this->clinic, fn () => Client::count()));
    }

    #[DataProvider('sameDocument')]
    public function test_the_same_document_in_another_form_is_taken_on_update(string $existing, string $new): void
    {
        $this->clientIn($this->clinic, ['document_number' => $existing]);
        $client = $this->clientIn($this->clinic);

        $this->assertTaken($this->patchJson("/api/clients/{$client->id}", ['document_number' => $new]));
    }

    public function test_the_base_is_saved_with_the_client(): void
    {
        $this->postJson('/api/clients', $this->clientPayload(['document_number' => '099999903']))->assertCreated();

        $this->assertDatabaseHas('clients', [
            'clinic_id' => $this->clinic->id,
            'document_number' => '099999903',
            'document_base' => '99999903',
        ]);
    }

    /**
     * Un cliente pasa su cédula a RUC: es su propio documento.
     */
    public function test_a_client_can_change_its_own_document_to_another_form(): void
    {
        $client = $this->clientIn($this->clinic, ['document_number' => '99999903']);

        $this->patchJson("/api/clients/{$client->id}", ['document_number' => '99999903-6'])
            ->assertOk()
            ->assertJsonPath('data.document_number', '99999903-6');

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'document_base' => '99999903']);
    }

    /**
     * Al cambiar el documento, la base sigue al documento nuevo y el anterior
     * queda libre.
     */
    public function test_the_base_follows_a_new_document(): void
    {
        $client = $this->clientIn($this->clinic, ['document_number' => '99400030']);

        $this->patchJson("/api/clients/{$client->id}", ['document_number' => '99400031'])->assertOk();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'document_base' => '99400031']);
        $this->postJson('/api/clients', $this->clientPayload(['document_number' => '099400030']))->assertCreated();
    }

    public function test_another_clinic_can_use_the_same_document_in_another_form(): void
    {
        $this->clientIn($this->createClinic(), ['document_number' => '99999903']);

        $this->postJson('/api/clients', $this->clientPayload(['document_number' => '99999903-6']))->assertCreated();
    }

    private function assertTaken(TestResponse $response): void
    {
        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.document_number', [self::TAKEN]);
    }
}
