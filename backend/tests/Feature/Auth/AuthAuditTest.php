<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditEvent;
use App\Enums\ClinicStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Auditoría del login y del logout (con el usuario y su clínica), y la limpieza
 * diaria de tokens vencidos.
 */
class AuthAuditTest extends TestCase
{
    use InteractsWithAuth;

    public function test_login_is_audited_with_the_user_and_its_clinic(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRole($clinic, 'recepcionista');

        $this->login($user->email)->assertOk();

        $log = $this->sole(AuditEvent::LOGIN);
        $this->assertSame([$user->id, $clinic->id], [$log->user_id, $log->clinic_id]);
    }

    public function test_failed_login_of_a_known_email_is_audited_with_the_user_and_its_clinic(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRole($clinic, 'recepcionista');

        $this->login($user->email, 'otra-clave')->assertStatus(422);

        $log = $this->sole(AuditEvent::LOGIN_FAILED);
        $this->assertSame([$user->id, $clinic->id], [$log->user_id, $log->clinic_id]);
        $this->assertSame(['email' => $user->email], json_decode($log->new_values, true));
    }

    public function test_failed_login_of_an_unknown_email_is_audited_without_user_or_clinic(): void
    {
        $this->createClinic();

        $this->login('nadie@example.com', 'otra-clave')->assertStatus(422);

        $log = $this->sole(AuditEvent::LOGIN_FAILED);
        $this->assertNull($log->user_id);
        $this->assertNull($log->clinic_id);
        $this->assertSame(['email' => 'nadie@example.com'], json_decode($log->new_values, true));
    }

    public function test_login_to_a_suspended_clinic_is_audited_as_failed_with_the_reason(): void
    {
        $clinic = $this->createClinic(['status' => ClinicStatus::SUSPENDED]);
        $user = $this->userWithRole($clinic, 'recepcionista');

        $this->login($user->email)->assertStatus(403);

        $log = $this->sole(AuditEvent::LOGIN_FAILED);
        $this->assertSame([$user->id, $clinic->id], [$log->user_id, $log->clinic_id]);
        $this->assertSame(['email' => $user->email, 'reason' => 'clínica suspendida'], json_decode($log->new_values, true));
        $this->assertFalse(DB::table('audit_logs')->where('event', AuditEvent::LOGIN->value)->exists());
    }

    public function test_logout_is_audited_with_the_user_and_its_clinic(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRole($clinic, 'recepcionista');

        $this->postWithToken($this->tokenFor($user), '/api/logout')->assertOk();

        $log = $this->sole(AuditEvent::LOGOUT);
        $this->assertSame([$user->id, $clinic->id], [$log->user_id, $log->clinic_id]);
    }

    public function test_expired_tokens_are_pruned_daily(): void
    {
        $events = collect(Schedule::events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'sanctum:prune-expired --hours=24'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }

    private function sole(AuditEvent $event): object
    {
        return DB::table('audit_logs')->where('event', $event->value)->sole();
    }
}
