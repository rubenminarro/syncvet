<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Patient;
use App\Support\CurrentClinic;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * ClinicScope (BelongsToClinic): los listados y las consultas por modelo solo
 * ven la clínica en contexto.
 */
class ClinicScopeTest extends TestCase
{
    use InteractsWithClinics;

    public function test_client_list_only_shows_own_clinic(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $mine = [$this->clientIn($own)->id, $this->clientIn($own)->id];
        $this->clientIn($other);

        $this->actingIn($own, ['clients.index']);

        $response = $this->getJson('/api/clients')->assertOk();

        $this->assertSame(2, $response->json('meta.pagination.total'));
        $this->assertEqualsCanonicalizing($mine, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_patient_list_only_shows_own_clinic(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $mine = $this->patientOf($this->clientIn($own));
        $this->patientOf($this->clientIn($other));

        $this->actingIn($own, ['patients.index']);

        $response = $this->getJson('/api/patients')->assertOk();

        $this->assertSame(1, $response->json('meta.pagination.total'));
        $this->assertSame([$mine->id], collect($response->json('data'))->pluck('id')->all());
    }

    /**
     * La búsqueda usa orWhere y orWhereHas: el filtro de la clínica no se puede
     * perder dentro del OR.
     */
    public function test_client_search_does_not_reach_other_clinics(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $this->patientOf($this->clientIn($other), ['name' => 'Firulais']);
        $this->clientIn($other, ['first_name' => 'Firulais']);

        $this->actingIn($own, ['clients.index', 'patients.index']);

        $this->getJson('/api/clients?search=Firulais')->assertOk()->assertJsonPath('meta.pagination.total', 0);
        $this->getJson('/api/patients?search=Firulais')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_counting_with_the_model_only_counts_own_clinic(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $this->patientOf($this->clientIn($own));
        $this->clientIn($own);
        $this->patientOf($this->clientIn($other));

        CurrentClinic::run($own, function () {
            $this->assertSame(2, Client::count());
            $this->assertSame(1, Patient::count());
        });

        CurrentClinic::run($other, function () {
            $this->assertSame(1, Client::count());
            $this->assertSame(1, Patient::count());
        });
    }
}
