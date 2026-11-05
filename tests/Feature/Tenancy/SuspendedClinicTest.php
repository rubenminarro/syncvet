<?php

namespace Tests\Feature\Tenancy;

use App\Enums\ClinicStatus;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * SetCurrentClinic: con la clínica suspendida, ningún pedido autenticado opera.
 */
class SuspendedClinicTest extends TestCase
{
    use InteractsWithClinics;

    public function test_suspended_clinic_responds_403_clinic_not_available(): void
    {
        $clinic = $this->createClinic(['status' => ClinicStatus::SUSPENDED]);
        $this->actingIn($clinic, ['clients.index']);

        $response = $this->getJson('/api/clients');

        $this->assertErrorCode($response, 403, 'CLINIC_NOT_AVAILABLE');
        $response->assertJsonPath('message', 'La clínica no está disponible.');
    }

    public function test_active_clinic_operates(): void
    {
        $this->actingIn($this->createClinic(), ['clients.index']);

        $this->getJson('/api/clients')->assertOk();
    }
}
