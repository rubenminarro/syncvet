<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicStatus;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Tokens: vencen a las 12 horas, el logout revoca solo el actual, y sin token o
 * con la clínica suspendida no se entra.
 */
class SessionTest extends TestCase
{
    use InteractsWithAuth;

    public function test_token_works_until_it_expires_twelve_hours_later(): void
    {
        $token = $this->tokenFor($this->userWithRole($this->createClinic(), 'recepcionista'));

        $this->travel(12 * 60 - 1)->minutes();
        $this->getWithToken($token, '/api/me')->assertOk();

        $this->travel(2)->minutes();
        $this->assertErrorCode($this->getWithToken($token, '/api/me'), 401, 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $current = $this->tokenFor($user);
        $other = $this->tokenFor($user);

        $this->postWithToken($current, '/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada.')
            ->assertJsonPath('data', null);

        $this->assertErrorCode($this->getWithToken($current, '/api/me'), 401, 'UNAUTHENTICATED');
        $this->getWithToken($other, '/api/me')->assertOk();
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_logout_and_me_require_a_token(): void
    {
        $this->assertErrorCode($this->postJson('/api/logout'), 401, 'UNAUTHENTICATED');
        $this->assertErrorCode($this->getJson('/api/me'), 401, 'UNAUTHENTICATED');
    }

    public function test_a_valid_token_of_a_suspended_clinic_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $token = $this->tokenFor($this->userWithRole($clinic, 'recepcionista'));

        $clinic->update(['status' => ClinicStatus::SUSPENDED]);

        $this->assertErrorCode($this->getWithToken($token, '/api/me'), 403, 'CLINIC_NOT_AVAILABLE');
        $this->assertErrorCode($this->postWithToken($token, '/api/logout'), 403, 'CLINIC_NOT_AVAILABLE');
    }
}
