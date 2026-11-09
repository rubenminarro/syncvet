<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Patient;
use App\Models\User;
use App\Models\Veterinarian;
use App\Models\Warehouse;
use App\Support\CurrentClinic;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * El route model binding corre con la clínica ya fijada (SetCurrentClinic antes
 * de SubstituteBindings): un id de otra clínica da 404, como si no existiera.
 */
class RouteBindingTest extends TestCase
{
    use InteractsWithBranches;

    private const PERMISSIONS = [
        'clients.show', 'clients.update', 'clients.destroy',
        'patients.index', 'patients.store', 'patients.show', 'patients.update', 'patients.destroy', 'patients.change-owner',
    ];

    public static function clientRoutes(): array
    {
        return [
            'GET clients/{client}' => ['get', '/api/clients/%d'],
            'PATCH clients/{client}' => ['patch', '/api/clients/%d'],
            'DELETE clients/{client}' => ['delete', '/api/clients/%d'],
            'GET clients/{client}/patients' => ['get', '/api/clients/%d/patients'],
            'POST clients/{client}/patients' => ['post', '/api/clients/%d/patients'],
        ];
    }

    public static function patientRoutes(): array
    {
        return [
            'GET patients/{patient}' => ['get', '/api/patients/%d'],
            'PATCH patients/{patient}' => ['patch', '/api/patients/%d'],
            'DELETE patients/{patient}' => ['delete', '/api/patients/%d'],
            'POST patients/{patient}/change-owner' => ['post', '/api/patients/%d/change-owner'],
        ];
    }

    #[DataProvider('clientRoutes')]
    public function test_client_of_another_clinic_is_not_found(string $method, string $uri): void
    {
        $own = $this->createClinic();
        $foreign = $this->clientIn($this->createClinic(), ['first_name' => 'Ajeno']);

        $this->actingIn($own, self::PERMISSIONS);

        $response = $this->json($method, sprintf($uri, $foreign->id), ['first_name' => 'Cambiado', 'name' => 'Nuevo']);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreign->clinic, function () use ($foreign) {
            $this->assertSame('Ajeno', Client::find($foreign->id)?->first_name, 'El cliente ajeno cambió o se eliminó.');
            $this->assertSame(0, Patient::count());
        });
    }

    #[DataProvider('patientRoutes')]
    public function test_patient_of_another_clinic_is_not_found(string $method, string $uri): void
    {
        $own = $this->createClinic();
        $foreign = $this->patientOf($this->clientIn($this->createClinic()), ['name' => 'Ajeno']);
        $newOwner = $this->clientIn($own);

        $this->actingIn($own, self::PERMISSIONS);

        $response = $this->json($method, sprintf($uri, $foreign->id), ['name' => 'Cambiado', 'client_id' => $newOwner->id]);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreign->clinic, function () use ($foreign) {
            $patient = Patient::find($foreign->id);

            $this->assertSame('Ajeno', $patient?->name, 'El paciente ajeno cambió o se eliminó.');
            $this->assertSame($foreign->client_id, $patient->client_id);
        });
    }

    /**
     * Sucursales y depósitos (FASE 2, entrega 2.1): una sucursal o un depósito de otra
     * clínica responde 404 en cada ruta y no cambia.
     */
    public static function branchRoutes(): array
    {
        return [
            'GET branches/{branch}' => ['get', '/api/branches/%d'],
            'PATCH branches/{branch}' => ['patch', '/api/branches/%d'],
            'PUT branches/{branch}' => ['put', '/api/branches/%d'],
            'GET branches/{branch}/warehouses' => ['get', '/api/branches/%d/warehouses'],
            'POST branches/{branch}/warehouses' => ['post', '/api/branches/%d/warehouses'],
        ];
    }

    #[DataProvider('branchRoutes')]
    public function test_branch_of_another_clinic_is_not_found(string $method, string $uri): void
    {
        $own = $this->createClinic();
        $this->defaultBranchIn($own);
        $foreign = $this->branchIn($this->createClinic(), ['name' => 'Ajena']);

        $this->actAsBranchManager($own);

        $response = $this->json($method, sprintf($uri, $foreign->id), ['name' => 'Cambiada', 'code' => 'DEP-99', 'is_default' => true]);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreign->clinic, function () use ($foreign) {
            $branch = Branch::find($foreign->id);

            $this->assertSame('Ajena', $branch?->name, 'La sucursal ajena cambió.');
            $this->assertSame(1, Warehouse::count());
        });
    }

    public function test_warehouse_of_another_clinic_is_not_found(): void
    {
        $own = $this->createClinic();
        $this->defaultBranchIn($own);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $warehouse = CurrentClinic::run($foreign->clinic, fn () => Warehouse::firstOrFail());

        $this->actAsBranchManager($own);

        foreach (['patch', 'put'] as $method) {
            $this->assertErrorCode($this->json($method, "/api/warehouses/{$warehouse->id}", ['name' => 'Cambiado']), 404, 'RESOURCE_NOT_FOUND');
        }

        $this->assertSame('Principal', CurrentClinic::run($foreign->clinic, fn () => Warehouse::find($warehouse->id)?->name));
    }

    /**
     * Usuarios (FASE 2, entrega 2.2a): User no tiene el scope de la clínica; su route
     * binding (inClinic()) da 404 a un usuario de otra clínica en cada ruta, y no lo
     * cambia.
     */
    public static function userRoutes(): array
    {
        return [
            'GET users/{user}' => ['get'],
            'PATCH users/{user}' => ['patch'],
            'PUT users/{user}' => ['put'],
            'DELETE users/{user}' => ['delete'],
        ];
    }

    #[DataProvider('userRoutes')]
    public function test_user_of_another_clinic_is_not_found(string $method): void
    {
        $own = $this->createClinic();
        $foreign = User::factory()->for($this->createClinic())->create(['first_name' => 'Ajena']);
        $foreign->syncRoles(['recepcionista']);

        $this->actingIn($own, ['users.show', 'users.update', 'users.destroy']);

        $response = $this->json($method, "/api/users/{$foreign->id}", ['first_name' => 'Cambiada', 'roles' => ['veterinario'], 'password' => 'Otra-Clave-2026!']);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');
        $this->assertSame('Ajena', $foreign->fresh()->first_name);
        $this->assertSame(['recepcionista'], $foreign->fresh()->getRoleNames()->all());
        $this->assertFalse($foreign->fresh()->trashed());
    }

    /**
     * Veterinarios (FASE 2, entrega 2.3): una ficha de otra clínica responde 404 en cada
     * ruta y no cambia. Un usuario de otra clínica en el alta responde 422 (VeterinarianStoreTest).
     */
    public static function veterinarianRoutes(): array
    {
        return [
            'GET veterinarians/{veterinarian}' => ['get'],
            'PATCH veterinarians/{veterinarian}' => ['patch'],
            'PUT veterinarians/{veterinarian}' => ['put'],
            'DELETE veterinarians/{veterinarian}' => ['delete'],
        ];
    }

    #[DataProvider('veterinarianRoutes')]
    public function test_veterinarian_of_another_clinic_is_not_found(string $method): void
    {
        $foreignClinic = $this->createClinic();
        $foreign = CurrentClinic::run($foreignClinic, fn () => Veterinarian::create([
            'user_id' => User::factory()->for($foreignClinic)->create()->id,
            'code' => 'VET-000001',
            'license_number' => 'MV-1001',
        ]));

        $this->actingIn($this->createClinic(), ['veterinarians.show', 'veterinarians.update', 'veterinarians.destroy']);

        $response = $this->json($method, "/api/veterinarians/{$foreign->id}", ['license_number' => 'MV-9999', 'status' => 'inactive']);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreignClinic, function () use ($foreign) {
            $veterinarian = Veterinarian::find($foreign->id);

            $this->assertSame('MV-1001', $veterinarian?->license_number, 'La ficha ajena cambió o se eliminó.');
            $this->assertSame('active', $veterinarian->status->value);
        });
    }

    public function test_own_records_are_found(): void
    {
        $own = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($own));

        $this->actingIn($own, self::PERMISSIONS);

        $this->getJson("/api/clients/{$patient->client_id}")->assertOk();
        $this->getJson("/api/clients/{$patient->client_id}/patients")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/patients/{$patient->id}")->assertOk();

        $branch = $this->defaultBranchIn($own);
        $this->actAsBranchManager($own);

        $this->getJson("/api/branches/{$branch->id}")->assertOk();
        $this->getJson("/api/branches/{$branch->id}/warehouses")->assertOk()->assertJsonCount(1, 'data');
    }
}
