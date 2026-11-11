<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_unknown_api_route_responds_json_404(): void
    {
        $this->get('/api/no-existe')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'status' => 404,
                'error_code' => 'RESOURCE_NOT_FOUND',
            ]);
    }

    public function test_protected_api_route_without_token_responds_json_401(): void
    {
        $this->get('/api/clients')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
            ]);
    }
}
