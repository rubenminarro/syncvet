<?php

namespace Tests\Concerns;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Datos de prueba de varias clínicas (D1). Los registros de negocio se crean
 * dentro de CurrentClinic::run, como lo haría un job o un comando.
 */
trait InteractsWithClinics
{
    protected function createClinic(array $attributes = []): Clinic
    {
        return Clinic::factory()->create($attributes);
    }

    /**
     * Usuario de la clínica con permisos directos (no el administrador, que pasa
     * todo por Gate::before).
     *
     * @param string[] $permissions
     */
    protected function userIn(Clinic $clinic, array $permissions = []): User
    {
        $user = User::factory()->for($clinic)->create();

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /**
     * @param string[] $permissions
     */
    protected function actingIn(Clinic $clinic, array $permissions = []): User
    {
        $user = $this->userIn($clinic, $permissions);

        Sanctum::actingAs($user);

        return $user;
    }

    protected function clientIn(Clinic $clinic, array $attributes = []): Client
    {
        static $sequence = 0;
        $sequence++;

        return CurrentClinic::run($clinic, fn () => Client::create([
            'document_number' => 'DOC' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'email' => "cliente{$sequence}@example.com",
            'phone' => '981123456',
            ...$attributes,
        ]));
    }

    protected function patientOf(Client $client, array $attributes = []): Patient
    {
        static $sequence = 0;
        $sequence++;

        return CurrentClinic::run($client->clinic, fn () => $client->patients()->create([
            'code' => 'TST-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'name' => 'Paciente',
            'species_id' => $this->globalSpeciesId(),
            ...$attributes,
        ]));
    }

    /**
     * Especie global sembrada por SpeciesSeeder (clinic_id nulo).
     */
    protected function globalSpeciesId(string $name = 'Canino'): int
    {
        return DB::table('species')->whereNull('clinic_id')->where('name', $name)->value('id');
    }

    /**
     * Datos válidos para el alta de un cliente por la API.
     */
    protected function clientPayload(array $overrides = []): array
    {
        return [
            'document_number' => '99400001',
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@example.com',
            'phone' => '981654321',
            ...$overrides,
        ];
    }

    protected function assertErrorCode(\Illuminate\Testing\TestResponse $response, int $status, string $errorCode): void
    {
        $response->assertStatus($status)->assertJson(['success' => false, 'error_code' => $errorCode]);
    }
}
