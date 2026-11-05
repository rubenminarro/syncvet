<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Patient;
use App\Support\CurrentClinic;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * El route model binding corre con la clínica ya fijada (SetCurrentClinic antes
 * de SubstituteBindings): un id de otra clínica da 404, como si no existiera.
 */
class RouteBindingTest extends TestCase
{
    use InteractsWithClinics;

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

    public function test_own_records_are_found(): void
    {
        $own = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($own));

        $this->actingIn($own, self::PERMISSIONS);

        $this->getJson("/api/clients/{$patient->client_id}")->assertOk();
        $this->getJson("/api/clients/{$patient->client_id}/patients")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/patients/{$patient->id}")->assertOk();
    }
}
