<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Un alta o una edición que pasa la validación y choca después con un índice único de
 * clients (otro pedido guardó el mismo documento o email en el medio) responde 422 en su
 * campo, con el mensaje de la regla unique, nunca 500 (revisión de la entrega 1.2 del
 * frontend: el doble clic en Guardar). El choque se arma con un oyente del modelo que
 * guarda el duplicado justo antes del INSERT o del UPDATE, después de la validación del
 * Form Request. Las dos altas simultáneas de verdad, en ClientConcurrencyTest (MySQL).
 */
class ClientUniqueIndexTest extends TestCase
{
    use InteractsWithAuth;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($this->clinic, 'recepcionista'));
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>, string, string}>
     */
    public static function collisions(): array
    {
        return [
            'el mismo documento' => [
                ['document_number' => '99400001'],
                ['document_number' => '99400001', 'email' => 'otro@example.com'],
                'document_number',
                'Este número de documento ya está en uso.',
            ],
            // R-018: la cédula y su RUC tienen la misma base (el índice de document_base).
            'la cédula del RUC que se guarda' => [
                ['document_number' => '99999903'],
                ['document_number' => '99999903-6', 'email' => 'otro@example.com'],
                'document_number',
                'Este número de documento ya está en uso.',
            ],
            'el mismo email' => [
                ['document_number' => '99400002', 'email' => 'juan@example.com'],
                ['document_number' => '99400001', 'email' => 'juan@example.com'],
                'email',
                'Este correo ya está en uso.',
            ],
        ];
    }

    #[DataProvider('collisions')]
    public function test_an_insert_that_hits_a_unique_index_responds_422_in_its_field(array $first, array $payload, string $field, string $message): void
    {
        $this->beforeWriting('creating', $first);

        $response = $this->postJson('/api/clients', $this->clientPayload($payload));

        $this->assertFieldError($response, $field, $message);
        $this->assertSame(1, $this->clientsInClinic(), 'Solo queda el que se guardó primero.');
    }

    #[DataProvider('collisions')]
    public function test_an_update_that_hits_a_unique_index_responds_422_in_its_field(array $first, array $payload, string $field, string $message): void
    {
        $client = CurrentClinic::run($this->clinic, fn () => Client::create($this->clientPayload([
            'document_number' => '99400009',
            'email' => 'antes@example.com',
        ])));
        $this->beforeWriting('updating', $first);

        $response = $this->patchJson("/api/clients/{$client->id}", $payload);

        $this->assertFieldError($response, $field, $message);
        $this->assertSame('99400009', $client->fresh()->document_number, 'La edición no se guardó.');
    }

    /**
     * Guarda otro cliente con $attributes (sin el modelo, como otro pedido) en el evento
     * $event del próximo cliente: ya pasó la validación y todavía no escribió.
     */
    private function beforeWriting(string $event, array $attributes): void
    {
        $done = false;

        Client::{$event}(function () use (&$done, $attributes) {
            if ($done) {
                return;
            }

            $done = true;
            $document = $attributes['document_number'];

            DB::table('clients')->insert([
                'clinic_id' => $this->clinic->id,
                'kind' => 'person',
                'first_name' => 'Otro',
                'last_name' => 'Pedido',
                'document_number' => $document,
                'document_base' => ltrim(explode('-', $document)[0], '0'),
                'email' => $attributes['email'] ?? 'primero@example.com',
                'phone' => '981123456',
                'notifications_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function clientsInClinic(): int
    {
        return DB::table('clients')->where('clinic_id', $this->clinic->id)->count();
    }

    private function assertFieldError(TestResponse $response, string $field, string $message): void
    {
        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath("errors.{$field}.0", $message);
    }
}
