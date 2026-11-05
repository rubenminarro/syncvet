<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * POST /api/login: token de 12 horas, varias sesiones por usuario, el mismo
 * error para cualquier credencial incorrecta y clínica suspendida sin token.
 */
class LoginTest extends TestCase
{
    use InteractsWithAuth;

    public static function roles(): array
    {
        return [
            'administrador' => ['administrador'],
            'recepcionista' => ['recepcionista'],
            'veterinario' => ['veterinario'],
        ];
    }

    #[DataProvider('roles')]
    public function test_login_with_each_role_returns_a_token_and_the_profile(string $role): void
    {
        $this->freezeSecond();
        $clinic = $this->createClinic(['name' => 'Veterinaria Norte']);
        $user = $this->userWithRole($clinic, $role);

        $response = $this->login($user->email);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', now()->addHours(12)->toISOString())
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.clinic', ['id' => $clinic->id, 'name' => 'Veterinaria Norte'])
            ->assertJsonPath('data.user.roles', [$role])
            ->assertJsonStructure(['data' => [
                'token', 'token_type', 'expires_at',
                'user' => ['id', 'first_name', 'last_name', 'name', 'email', 'clinic', 'current_branch', 'roles', 'permissions', 'veterinarian'],
            ]]);
        $this->assertStringEndsWith('Z', $response->json('data.expires_at'));

        $this->getWithToken($response->json('data.token'), '/api/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_token_expires_twelve_hours_after_login(): void
    {
        $this->freezeSecond();
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $this->login($user->email)->assertOk();

        $this->assertEquals(now()->addHours(12), $user->tokens()->sole()->expires_at);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_error(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $wrongPassword = $this->login($user->email, 'otra-clave');
        $unknownEmail = $this->login('nadie@example.com');

        foreach ([$wrongPassword, $unknownEmail] as $response) {
            $this->assertErrorCode($response, 422, 'INVALID_CREDENTIALS');
            $response->assertJsonPath('message', 'El email o la contraseña no son correctos.');
        }
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_deleted_user_cannot_log_in_and_gets_the_same_error(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $user->delete();

        $response = $this->login($user->email);

        $this->assertErrorCode($response, 422, 'INVALID_CREDENTIALS');
        $response->assertJsonPath('message', 'El email o la contraseña no son correctos.');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_suspended_clinic_cannot_log_in_and_no_token_is_created(): void
    {
        $user = $this->userWithRole($this->createClinic(['status' => ClinicStatus::SUSPENDED]), 'recepcionista');

        $response = $this->login($user->email);

        $this->assertErrorCode($response, 403, 'CLINIC_NOT_AVAILABLE');
        $response->assertJsonPath('message', 'La clínica no está disponible.');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_each_login_creates_a_new_token_and_the_previous_ones_stay_valid(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');

        $first = $this->tokenFor($user);
        $second = $this->tokenFor($user);

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $user->tokens()->count());
        $this->getWithToken($first, '/api/me')->assertOk();
        $this->getWithToken($second, '/api/me')->assertOk();
    }

    public function test_login_validates_email_and_password_with_voseo_messages(): void
    {
        $response = $this->postJson('/api/login', ['email' => 'no-es-un-email']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.email.0', 'Ingresá un email válido.')
            ->assertJsonPath('errors.password.0', 'Ingresá tu contraseña.');

        $this->postJson('/api/login', [])->assertJsonPath('errors.email.0', 'Ingresá tu email.');
    }
}
