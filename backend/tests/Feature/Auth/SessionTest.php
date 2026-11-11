<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditEvent;
use App\Enums\ClinicStatus;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Tokens: vencen a las 12 horas, el logout revoca solo el actual (también con la
 * clínica suspendida), sin token o con la clínica suspendida no se opera, y un
 * usuario eliminado pierde sus tokens.
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
    }

    /**
     * El logout no lleva SetCurrentClinic: con la clínica suspendida el usuario igual
     * revoca su token, y queda auditado en su clínica.
     */
    public function test_logout_works_with_a_suspended_clinic(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRole($clinic, 'recepcionista');
        $token = $this->tokenFor($user);

        $clinic->update(['status' => ClinicStatus::SUSPENDED]);

        $this->postWithToken($token, '/api/logout')->assertOk()->assertJsonPath('message', 'Sesión cerrada.');
        $this->assertSame(0, $user->tokens()->count());

        $log = DB::table('audit_logs')->where('event', AuditEvent::LOGOUT->value)->sole();
        $this->assertSame([$user->id, $clinic->id], [$log->user_id, $log->clinic_id]);

        $clinic->update(['status' => ClinicStatus::ACTIVE]);
        $this->assertErrorCode($this->getWithToken($token, '/api/me'), 401, 'UNAUTHENTICATED');
    }

    public function test_deleting_a_user_deletes_its_tokens_and_restoring_it_does_not_revive_them(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $first = $this->tokenFor($user);
        $this->tokenFor($user);
        $other = $this->tokenFor($this->userWithRole($user->clinic, 'recepcionista'));

        $user->delete();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->getWithToken($other, '/api/me')->assertOk();

        $user->restore();
        $this->assertErrorCode($this->getWithToken($first, '/api/me'), 401, 'UNAUTHENTICATED');
    }
}
