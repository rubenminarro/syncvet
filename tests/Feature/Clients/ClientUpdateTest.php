<?php

namespace Tests\Feature\Clients;

use App\Enums\ClientKind;
use App\Models\Client;
use App\Models\Clinic;
use App\Rules\RucCheckDigit;
use App\Services\ClinicService;
use App\Support\CurrentClinic;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\InteractsWithClinicalRecords;
use Tests\TestCase;

/**
 * Edición de clientes (CLAUDE.md §6.1; FASE 1, entrega 1.2), con el rol
 * recepcionista.
 */
class ClientUpdateTest extends TestCase
{
    use InteractsWithAuth;
    use InteractsWithClinicalRecords;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    public function test_a_person_becomes_a_company_and_loses_first_and_last_name(): void
    {
        $client = $this->clientIn($this->clinic);

        $this->update($client, ['kind' => 'company', 'business_name' => 'Haras del Sur SA'])
            ->assertOk()
            ->assertJsonPath('data.kind.value', 'company')
            ->assertJsonPath('data.display_name', 'Haras del Sur SA');

        $fresh = $this->fresh($client);
        $this->assertSame(ClientKind::COMPANY, $fresh->kind);
        $this->assertNull($fresh->first_name);
        $this->assertNull($fresh->last_name);
    }

    public function test_a_company_becomes_a_person_and_loses_its_business_name(): void
    {
        $client = $this->companyIn($this->clinic);

        $this->update($client, ['kind' => 'person', 'first_name' => 'Rosa', 'last_name' => 'Benítez'])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Rosa Benítez');

        $this->assertNull($this->fresh($client)->business_name);
    }

    public function test_a_kind_change_needs_the_fields_of_the_new_kind(): void
    {
        $this->assertFieldError(
            $this->update($this->clientIn($this->clinic), ['kind' => 'company']),
            'business_name',
            'La razón social es obligatoria para una empresa.'
        );
        $this->assertFieldError(
            $this->update($this->companyIn($this->clinic), ['kind' => 'person', 'first_name' => 'Rosa']),
            'last_name',
            'El apellido es obligatorio.'
        );
    }

    public function test_a_client_with_invoices_keeps_its_kind(): void
    {
        $client = $this->clientIn($this->clinic);
        CurrentClinic::run($this->clinic, fn () => $this->invoiceFor($client));

        $this->update($client, ['kind' => 'company', 'business_name' => 'Haras del Sur SA'])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'El cliente ya tiene facturas: no se puede cambiar de persona a empresa ni al revés.',
                'error_code' => 'CLIENT_KIND_NOT_EDITABLE',
            ]);

        $fresh = $this->fresh($client);
        $this->assertSame(ClientKind::PERSON, $fresh->kind);
        $this->assertSame('Ana', $fresh->first_name);
        $this->assertNull($fresh->business_name);
    }

    /**
     * Con facturas, el resto de los datos se sigue editando, también enviando el
     * mismo tipo.
     */
    public function test_a_client_with_invoices_can_edit_the_other_fields(): void
    {
        $client = $this->clientIn($this->clinic);
        CurrentClinic::run($this->clinic, fn () => $this->invoiceFor($client));

        $this->update($client, ['kind' => 'person', 'phone' => '+595981999999', 'last_name' => 'Gómez Ruiz'])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Ana Gómez Ruiz');
    }

    public function test_the_client_keeps_its_own_document_and_email(): void
    {
        $client = $this->clientIn($this->clinic, ['document_number' => '99400004', 'email' => 'propio@example.com']);

        $this->update($client, ['document_number' => '99400004', 'email' => 'propio@example.com'])->assertOk();
    }

    public function test_the_document_and_email_of_another_client_are_taken(): void
    {
        $this->clientIn($this->clinic, ['document_number' => '99400005', 'email' => 'otro@example.com']);
        $client = $this->clientIn($this->clinic);

        $response = $this->update($client, ['document_number' => '99400005', 'email' => 'otro@example.com']);

        $this->assertFieldError($response, 'document_number', 'Este número de documento ya está en uso.');
        $response->assertJsonPath('errors.email.0', 'Este correo ya está en uso.');
    }

    public function test_the_ruc_check_digit_is_validated_on_update(): void
    {
        $client = $this->clientIn($this->clinic);

        $this->assertFieldError(
            $this->update($client, ['document_number' => '99999905-' . ((ClinicService::rucCheckDigit('99999905') + 1) % 10)]),
            'document_number',
            RucCheckDigit::MESSAGE
        );

        $this->update($client, ['document_number' => '99999905-2'])
            ->assertOk()
            ->assertJsonPath('data.document_number', '99999905-2');
    }

    /**
     * Sin cambiar el tipo, la edición también rechaza los campos del otro tipo
     * (revisión de la 1.2: solo estaba probado en el alta).
     */
    public function test_a_company_does_not_take_a_first_or_last_name(): void
    {
        $company = $this->companyIn($this->clinic);

        $this->assertFieldError($this->update($company, ['first_name' => 'Juan']), 'first_name', 'Una empresa no lleva nombre: usá la razón social.');
        $this->assertFieldError($this->update($company, ['last_name' => 'Pérez']), 'last_name', 'Una empresa no lleva apellido: usá la razón social.');

        $fresh = $this->fresh($company);
        $this->assertNull($fresh->first_name);
        $this->assertNull($fresh->last_name);
    }

    public function test_a_person_does_not_take_a_business_name(): void
    {
        $person = $this->clientIn($this->clinic);

        $this->assertFieldError(
            $this->update($person, ['business_name' => 'Haras del Sur SA']),
            'business_name',
            'La razón social solo corresponde a una empresa.'
        );
        $this->assertNull($this->fresh($person)->business_name);
    }

    /**
     * El email queda en minúsculas también al editar (revisión de la 1.2).
     */
    public function test_the_email_must_be_in_lowercase_on_update(): void
    {
        $client = $this->clientIn($this->clinic, ['email' => 'antes@example.com']);

        $this->assertFieldError($this->update($client, ['email' => 'Nuevo@Example.com']), 'email', 'El correo debe estar en minúsculas.');
        $this->assertSame('antes@example.com', $this->fresh($client)->email);

        $this->update($client, ['email' => 'nuevo@example.com'])->assertOk();
        $this->assertSame('nuevo@example.com', $this->fresh($client)->email);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function requiredFields(): array
    {
        return [
            'nombre' => ['person', 'first_name', 'El nombre es obligatorio.'],
            'apellido' => ['person', 'last_name', 'El apellido es obligatorio.'],
            'razón social' => ['company', 'business_name', 'La razón social es obligatoria para una empresa.'],
            'documento' => ['person', 'document_number', 'El número de documento es obligatorio.'],
            'email' => ['person', 'email', 'El correo es obligatorio.'],
            'teléfono' => ['person', 'phone', 'El teléfono es obligatorio.'],
        ];
    }

    /**
     * Como en el alta: un campo obligatorio enviado vacío responde solo "es
     * obligatorio", sin los mensajes de formato (revisión de la 1.2).
     */
    #[DataProvider('requiredFields')]
    public function test_null_in_a_required_field_only_says_it_is_required(string $kind, string $field, string $message): void
    {
        $client = $kind === 'company' ? $this->companyIn($this->clinic) : $this->clientIn($this->clinic);

        foreach ([null, ''] as $empty) {
            $this->update($client, [$field => $empty])
                ->assertUnprocessable()
                ->assertJsonPath('error_code', 'VALIDATION_ERROR')
                ->assertJsonPath("errors.{$field}", [$message]);
        }
    }

    /**
     * El frontend manda todos los campos: al cambiar de tipo, los del otro tipo
     * llegan en null y se aceptan, como en el alta (revisión de la 1.2).
     */
    public function test_a_kind_change_accepts_null_in_the_other_kind_fields(): void
    {
        $person = $this->clientIn($this->clinic);
        $this->update($person, ['kind' => 'company', 'business_name' => 'Haras del Sur SA', 'first_name' => null, 'last_name' => null])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Haras del Sur SA');

        $company = $this->companyIn($this->clinic);
        $this->update($company, ['kind' => 'person', 'first_name' => 'Rosa', 'last_name' => 'Benítez', 'business_name' => null])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Rosa Benítez');

        // Sin cambiar el tipo, también.
        $this->update($this->companyIn($this->clinic), ['business_name' => 'Otra SA', 'first_name' => null, 'last_name' => null])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Otra SA');
    }

    private function companyIn(Clinic $clinic): Client
    {
        return $this->clientIn($clinic, [
            'kind' => ClientKind::COMPANY,
            'first_name' => null,
            'last_name' => null,
            'business_name' => 'Haras del Sur SA',
        ]);
    }

    private function update(Client $client, array $payload): TestResponse
    {
        return $this->patchJson("/api/clients/{$client->id}", $payload);
    }

    private function fresh(Client $client): Client
    {
        return CurrentClinic::run($this->clinic, fn () => Client::findOrFail($client->id));
    }

    private function assertFieldError(TestResponse $response, string $field, string $message): void
    {
        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath("errors.{$field}.0", $message);
    }
}
