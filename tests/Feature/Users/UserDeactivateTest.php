<?php

namespace Tests\Feature\Users;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Usuarios inactivos (FASE 2, entrega 2.2b; P1 y P4): inactivar revoca todos sus
 * tokens, reactivar no se los devuelve; el administrador no se inactiva a sí mismo y
 * nadie deja la clínica sin un administrador activo; el listado oculta los inactivos.
 */
class UserDeactivateTest extends TestCase
{
    use InteractsWithUsers;

    public function test_deactivating_revokes_every_token_and_reactivating_does_not_give_them_back(): void
    {
        $clinic = $this->createClinic();
        [$admin, $adminToken] = $this->administratorWithToken($clinic);
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $first = $user->createToken('api')->plainTextToken;
        $user->createToken('api');
        $colleague = $this->userWithRoles($clinic, ['recepcionista']);
        $colleague->createToken('api');

        $this->withRealToken($first)->getJson('/api/me')->assertOk();

        $this->withRealToken($adminToken)->patchJson("/api/users/{$user->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $colleague->tokens()->count());
        $this->assertSame(1, $admin->tokens()->count());
        $this->assertErrorCode($this->withRealToken($first)->getJson('/api/me'), 401, 'UNAUTHENTICATED');

        $this->withRealToken($adminToken)->patchJson("/api/users/{$user->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertErrorCode($this->withRealToken($first)->getJson('/api/me'), 401, 'UNAUTHENTICATED');
    }

    /**
     * Con el mismo valor no hay cambio: los tokens siguen.
     */
    public function test_sending_is_active_true_to_an_active_user_keeps_the_tokens(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $user->createToken('api');
        $this->actAsUserManager($clinic);

        $this->patchJson("/api/users/{$user->id}", ['is_active' => true, 'first_name' => 'Marta'])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_the_administrator_does_not_deactivate_himself(): void
    {
        $clinic = $this->createClinic();
        [$admin, $token] = $this->administratorWithToken($clinic);
        $this->userWithRoles($clinic, ['administrador']);

        $response = $this->withRealToken($token)->patchJson("/api/users/{$admin->id}", ['is_active' => false]);

        $this->assertErrorCode($response, 409, 'USER_SELF_ACTION');
        $response->assertJsonPath('message', 'No podés inactivarte ni darte de baja a vos mismo.');
        $this->assertTrue($admin->fresh()->is_active);
        $this->withRealToken($token)->getJson('/api/me')->assertOk();

        // Reenviar is_active: true a sí mismo (el formulario completo) no es inactivarse.
        $this->withRealToken($token)->patchJson("/api/users/{$admin->id}", ['is_active' => true, 'first_name' => 'Ramona'])->assertOk();
    }

    public function test_the_last_active_administrator_is_not_deactivated(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $this->userWithRoles($this->createClinic(), ['administrador']);
        $this->actAsUserManager($clinic);

        $response = $this->patchJson("/api/users/{$admin->id}", ['is_active' => false, 'first_name' => 'Otro']);

        $this->assertErrorCode($response, 409, 'USER_LAST_ADMIN');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertNotSame('Otro', $admin->fresh()->first_name);

        $this->userWithRoles($clinic, ['administrador']);

        $this->patchJson("/api/users/{$admin->id}", ['is_active' => false])->assertOk();
    }

    /**
     * Condición del desvío 7 de la 2.2a (R-032): un administrador inactivo no salva al
     * último administrador activo, ni al quitarle el rol ni al inactivarlo.
     */
    public function test_an_inactive_administrator_does_not_count(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $inactive = $this->userWithRoles($clinic, ['administrador'], [], ['is_active' => false]);
        $this->actAsUserManager($clinic);

        $this->assertErrorCode($this->patchJson("/api/users/{$admin->id}", ['roles' => ['recepcionista']]), 409, 'USER_LAST_ADMIN');
        $this->assertErrorCode($this->patchJson("/api/users/{$admin->id}", ['is_active' => false]), 409, 'USER_LAST_ADMIN');
        $this->assertSame(['administrador'], $admin->fresh()->getRoleNames()->all());

        // Quitarle el rol al inactivo no cambia los administradores activos: 200.
        $this->patchJson("/api/users/{$inactive->id}", ['roles' => ['recepcionista']])->assertOk();

        // Reactivar a otro administrador sí salva al último.
        $other = $this->userWithRoles($clinic, ['administrador'], [], ['is_active' => false]);
        $this->patchJson("/api/users/{$other->id}", ['is_active' => true])->assertOk();
        $this->patchJson("/api/users/{$admin->id}", ['roles' => ['recepcionista']])->assertOk();
    }

    public function test_the_list_hides_inactive_users_unless_asked(): void
    {
        $clinic = $this->createClinic();
        $this->userWithRoles($clinic, ['recepcionista'], [], ['first_name' => 'Activa', 'last_name' => 'Acosta']);
        $this->userWithRoles($clinic, ['recepcionista'], [], ['first_name' => 'Inactiva', 'last_name' => 'Aquino', 'is_active' => false]);
        $this->actAsUserManager($clinic);

        $names = fn (string $query) => collect($this->getJson("/api/users?{$query}")->assertOk()->json('data'))->pluck('first_name')->all();

        $this->assertSame(['Activa', 'Zoe'], $names('per_page=100'));
        $this->assertSame(['Activa', 'Inactiva', 'Zoe'], $names('include_inactive=1'));
        $this->getJson('/api/users?include_inactive=1')->assertJsonPath('data.1.is_active', false);
        $this->getJson('/api/users?include_inactive=x')->assertUnprocessable()->assertJsonValidationErrors('include_inactive');
    }

    public function test_is_active_must_be_a_boolean_and_is_recorded_in_the_audit(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $this->actAsUserManager($clinic);

        $this->patchJson("/api/users/{$user->id}", ['is_active' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', 'El campo activo debe ser verdadero o falso.');

        $this->patchJson("/api/users/{$user->id}", ['is_active' => false])->assertOk();

        $log = DB::table('audit_logs')->where('auditable_type', 'user')->where('auditable_id', $user->id)->where('event', 'updated')->sole();
        $this->assertSame(['is_active' => false], json_decode($log->new_values, true));
    }
}
