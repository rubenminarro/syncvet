<?php

namespace Tests\Feature\Clients;

use App\Models\Clinic;
use App\Rules\RucCheckDigit;
use App\Services\ClinicService;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Alta de clientes (CLAUDE.md §6.1; FASE 1, entrega 1.2), con el rol
 * recepcionista.
 */
class ClientStoreTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    public function test_a_client_without_kind_is_a_person(): void
    {
        $this->store($this->clientPayload())
            ->assertCreated()
            ->assertJsonPath('data.kind.value', 'person')
            ->assertJsonPath('data.display_name', 'Juan Pérez')
            ->assertJsonPath('data.business_name', null);

        $this->assertDatabaseHas('clients', ['clinic_id' => $this->clinic->id, 'document_number' => '99400001', 'kind' => 'person']);
    }

    public function test_a_company_is_created_with_its_business_name(): void
    {
        $this->store($this->companyPayload())
            ->assertCreated()
            ->assertJsonPath('data.kind.value', 'company')
            ->assertJsonPath('data.display_name', 'Haras del Sur SA')
            ->assertJsonPath('data.first_name', null)
            ->assertJsonPath('data.last_name', null);
    }

    public function test_a_company_needs_a_business_name(): void
    {
        $this->assertFieldError(
            $this->store($this->companyPayload(['business_name' => null])),
            'business_name',
            'La razón social es obligatoria para una empresa.'
        );
    }

    public function test_a_company_does_not_take_a_first_or_last_name(): void
    {
        $this->assertFieldError(
            $this->store($this->companyPayload(['first_name' => 'Juan'])),
            'first_name',
            'Una empresa no lleva nombre: usá la razón social.'
        );
        $this->assertFieldError(
            $this->store($this->companyPayload(['last_name' => 'Pérez'])),
            'last_name',
            'Una empresa no lleva apellido: usá la razón social.'
        );
    }

    public function test_a_person_does_not_take_a_business_name_and_needs_first_and_last_name(): void
    {
        $this->assertFieldError(
            $this->store($this->clientPayload(['business_name' => 'Haras del Sur SA'])),
            'business_name',
            'La razón social solo corresponde a una empresa.'
        );
        $this->assertFieldError($this->store($this->clientPayload(['first_name' => null])), 'first_name', 'El nombre es obligatorio.');
        $this->assertFieldError($this->store($this->clientPayload(['last_name' => null])), 'last_name', 'El apellido es obligatorio.');
    }

    public function test_document_and_email_are_required(): void
    {
        $response = $this->store($this->clientPayload(['document_number' => null, 'email' => null]));

        $this->assertFieldError($response, 'document_number', 'El número de documento es obligatorio.');
        $response->assertJsonPath('errors.email.0', 'El correo es obligatorio.');
    }

    public function test_document_and_email_are_unique_in_the_clinic(): void
    {
        $this->clientIn($this->clinic, ['document_number' => '99400001', 'email' => 'juan@example.com']);

        $response = $this->store($this->clientPayload());

        $this->assertFieldError($response, 'document_number', 'Este número de documento ya está en uso.');
        $response->assertJsonPath('errors.email.0', 'Este correo ya está en uso.');
    }

    public function test_another_clinic_can_use_the_same_document_and_email(): void
    {
        $this->clientIn($this->createClinic(), ['document_number' => '99400001', 'email' => 'juan@example.com']);

        $this->store($this->clientPayload())->assertCreated();
    }

    public function test_the_email_must_be_in_lowercase(): void
    {
        $this->assertFieldError(
            $this->store($this->clientPayload(['email' => 'Juan@Example.com'])),
            'email',
            'El correo debe estar en minúsculas.'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validPhones(): array
    {
        return [
            'celular' => ['981654321'],
            'con prefijo internacional' => ['+595981654321'],
            'ocho dígitos' => ['99654321'],
            'quince dígitos' => ['+123456789012345'],
        ];
    }

    #[DataProvider('validPhones')]
    public function test_a_valid_phone_is_accepted(string $phone): void
    {
        $this->store($this->clientPayload(['phone' => $phone]))
            ->assertCreated()
            ->assertJsonPath('data.phone', $phone);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPhones(): array
    {
        return [
            'empieza con cero' => ['0981654321'],
            'seis dígitos' => ['+123456'],
            'dieciséis dígitos' => ['+1234567890123456'],
            'con espacios' => ['981 654 321'],
            'con letras' => ['98165432a'],
        ];
    }

    #[DataProvider('invalidPhones')]
    public function test_an_invalid_phone_is_rejected(string $phone): void
    {
        $this->assertFieldError(
            $this->store($this->clientPayload(['phone' => $phone])),
            'phone',
            'El teléfono debe tener un formato válido (opcionalmente con un + al inicio, seguido de 8 a 15 dígitos).'
        );
    }

    /**
     * RUC con su dígito verificador, para persona y empresa (R-015).
     */
    public function test_a_ruc_with_the_right_check_digit_is_accepted(): void
    {
        $this->store($this->clientPayload(['document_number' => '99999903-6']))->assertCreated();
        $this->store($this->companyPayload(['document_number' => '99999904-4']))->assertCreated();
    }

    public function test_a_ruc_with_a_wrong_check_digit_is_rejected(): void
    {
        $this->assertFieldError(
            $this->store($this->clientPayload(['document_number' => $this->wrongRuc('99999903')])),
            'document_number',
            RucCheckDigit::MESSAGE
        );
        $this->assertFieldError(
            $this->store($this->companyPayload(['document_number' => $this->wrongRuc('99999904')])),
            'document_number',
            RucCheckDigit::MESSAGE
        );
        $this->assertSame('El RUC no es válido: revisá el número y el dígito verificador.', RucCheckDigit::MESSAGE);
    }

    /**
     * Solo se valida la forma "número-dígito": una cédula sin guion o un
     * documento con letras no tiene dígito verificador que revisar.
     */
    public function test_documents_that_are_not_a_ruc_are_not_checked(): void
    {
        $this->store($this->clientPayload(['document_number' => '99400003', 'email' => 'cedula@example.com']))->assertCreated();
        $this->store($this->clientPayload(['document_number' => 'AB-12345-9', 'email' => 'letras@example.com']))->assertCreated();
        $this->store($this->clientPayload(['document_number' => 'X99999903-1', 'email' => 'pasaporte@example.com']))->assertCreated();
    }

    /**
     * Nombre y apellido: apóstrofo y guion entre letras (D'Alessandro, Ana-María; FASE 2,
     * entrega 2.2a), nunca al principio, al final ni repetidos.
     */
    public function test_person_names_take_an_apostrophe_or_a_hyphen_between_letters(): void
    {
        $this->store($this->clientPayload(['first_name' => 'Ana-María', 'last_name' => "D'Alessandro"]))
            ->assertCreated()
            ->assertJsonPath('data.display_name', "Ana-María D'Alessandro");

        foreach (["'Ana", "Ana'", '-Ana', 'Ana-', 'Ana--María', "D''Alessandro", "Ana -María", 'Ana1'] as $index => $name) {
            $this->store($this->clientPayload(['document_number' => '99400' . (100 + $index), 'email' => "nombre{$index}@example.com", 'last_name' => $name]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.last_name.0', 'El apellido solo puede contener letras y espacios, y apóstrofos o guiones entre letras.');
        }
    }

    private function store(array $payload): TestResponse
    {
        return $this->postJson('/api/clients', $payload);
    }

    private function companyPayload(array $overrides = []): array
    {
        return [
            'kind' => 'company',
            'business_name' => 'Haras del Sur SA',
            'document_number' => '99400002',
            'email' => 'haras@example.com',
            'phone' => '+595971222333',
            ...$overrides,
        ];
    }

    /**
     * Un RUC con el dígito verificador equivocado. Se arma en el código para que
     * FictitiousDocumentsTest no lo tome como un documento de ejemplo.
     */
    private function wrongRuc(string $number): string
    {
        return $number . '-' . ((ClinicService::rucCheckDigit($number) + 1) % 10);
    }

    private function assertFieldError(TestResponse $response, string $field, string $message): void
    {
        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath("errors.{$field}.0", $message);
    }
}
