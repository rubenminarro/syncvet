<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\InteractsWithClinicalRecords;
use Tests\TestCase;

/**
 * Baja de clientes (CLAUDE.md §3, datos maestros, y §6.1; FASE 1, entrega 1.2).
 * La baja es solo del administrador (R-015): se prueba con un rol propio que
 * tiene clients.destroy, porque el administrador pasa todo por Gate::before.
 */
class ClientDeleteTest extends TestCase
{
    use InteractsWithAuth;
    use InteractsWithClinicalRecords;

    public function test_a_client_without_use_is_deleted(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $this->actAsDeleter($clinic);

        $this->deleteJson("/api/clients/{$client->id}")->assertOk();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->getJson("/api/clients/{$client->id}")->assertNotFound();
    }

    public function test_the_deleted_document_and_email_are_free_again(): void
    {
        $this->freezeTime();
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic, ['document_number' => '99400002', 'email' => 'libre@example.com']);
        $user = $this->actAsDeleter($clinic);
        $user->givePermissionTo('clients.store');

        $this->deleteJson("/api/clients/{$client->id}")->assertOk();

        $deleted = CurrentClinic::run($clinic, fn () => Client::withTrashed()->findOrFail($client->id));
        $suffix = "//deleted_{$client->id}_" . now()->timestamp;
        $this->assertSame('99400002' . $suffix, $deleted->document_number);
        $this->assertSame('99400002' . $suffix, $deleted->document_base);
        $this->assertSame('libre@example.com' . $suffix, $deleted->email);

        $this->postJson('/api/clients', $this->clientPayload(['document_number' => '99400002', 'email' => 'libre@example.com']))
            ->assertCreated();
    }

    /**
     * La baja libera también el número base (R-018): el documento vuelve a
     * cargarse en otra forma, con un cero adelante.
     */
    public function test_the_deleted_document_base_is_free_again(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic, ['document_number' => '99400002']);
        $this->actAsDeleter($clinic)->givePermissionTo('clients.store');

        $this->deleteJson("/api/clients/{$client->id}")->assertOk();

        $this->postJson('/api/clients', $this->clientPayload(['document_number' => '099400002']))->assertCreated();
    }

    public function test_a_client_with_patients_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $this->patientOf($client);
        $this->actAsDeleter($clinic);

        $this->assertInUse($this->deleteJson("/api/clients/{$client->id}"));
        $this->assertNotSoftDeleted('clients', ['id' => $client->id]);
    }

    /**
     * Con una consulta y sin pacientes: el paciente de la consulta cambió de dueño
     * y la consulta guarda al dueño de ese momento.
     */
    public function test_a_client_with_consultations_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $formerOwner = $this->clientIn($clinic);
        $patient = $this->patientOf($this->clientIn($clinic));
        CurrentClinic::run($clinic, fn () => $this->consultationFor($patient, $formerOwner));
        $this->actAsDeleter($clinic);

        $this->assertInUse($this->deleteJson("/api/clients/{$formerOwner->id}"));
        $this->assertNotSoftDeleted('clients', ['id' => $formerOwner->id]);
    }

    public function test_a_client_with_invoices_is_in_use(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        CurrentClinic::run($clinic, fn () => $this->invoiceFor($client));
        $this->actAsDeleter($clinic);

        $this->assertInUse($this->deleteJson("/api/clients/{$client->id}"));
        $this->assertNotSoftDeleted('clients', ['id' => $client->id]);
    }

    /**
     * El sufijo de baja no se corta: un documento de 20 caracteres (el máximo) se
     * puede dar de baja dos veces (pregunta 15 del diseño). Con el reloj congelado: el
     * timestamp que se compara se toma después del DELETE y, con la máquina cargada
     * (--parallel), el segundo podía cambiar en el medio.
     */
    public function test_the_same_long_document_can_be_deleted_twice(): void
    {
        $this->freezeTime();

        $this->assertTheSameDocumentCanBeDeletedTwice(fn () => $this->travel(1)->days());
    }

    /**
     * El id del registro en el sufijo: dos bajas del mismo documento en el mismo
     * segundo no chocan con el índice único.
     */
    public function test_the_same_document_can_be_deleted_twice_in_the_same_second(): void
    {
        $this->freezeTime();

        $this->assertTheSameDocumentCanBeDeletedTwice(fn () => null);
    }

    private function assertTheSameDocumentCanBeDeletedTwice(\Closure $between): void
    {
        $clinic = $this->createClinic();
        $document = 'ABCDEFGHIJ-KLMNOPQRS';
        $this->assertSame(20, strlen($document));
        $this->actAsDeleter($clinic);

        $first = $this->clientIn($clinic, ['document_number' => $document, 'email' => 'repetido@example.com']);
        $this->deleteJson("/api/clients/{$first->id}")->assertOk();
        $firstTimestamp = now()->timestamp;

        $between();

        $second = $this->clientIn($clinic, ['document_number' => $document, 'email' => 'repetido@example.com']);
        $this->deleteJson("/api/clients/{$second->id}")->assertOk();

        $deleted = CurrentClinic::run($clinic, fn () => Client::onlyTrashed()->orderBy('id')->get());
        $this->assertSame([
            "{$document}//deleted_{$first->id}_{$firstTimestamp}",
            "{$document}//deleted_{$second->id}_" . now()->timestamp,
        ], $deleted->pluck('document_number')->all());
        $this->assertCount(2, $deleted->pluck('email')->unique());
    }

    public function test_the_receptionist_cannot_delete_a_client(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->deleteJson("/api/clients/{$client->id}")
            ->assertForbidden()
            ->assertJson(['error_code' => 'FORBIDDEN']);
        $this->assertNotSoftDeleted('clients', ['id' => $client->id]);
    }

    private function actAsDeleter(Clinic $clinic)
    {
        Role::findOrCreate('baja-clientes', 'api')->syncPermissions(['clients.destroy', 'clients.show']);
        $user = $this->userWithRole($clinic, 'baja-clientes');
        Sanctum::actingAs($user);

        return $user;
    }

    private function assertInUse(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertStatus(409)->assertJson([
            'success' => false,
            'message' => 'El cliente tiene pacientes, consultas o facturas y no puede eliminarse.',
            'error_code' => 'CLIENT_IN_USE',
        ]);
    }
}
