<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Patient;
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
