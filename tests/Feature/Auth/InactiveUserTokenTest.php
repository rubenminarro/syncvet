<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAuth;
use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * Defensa en profundidad (FASE 2, entrega 2.2b): un token vigente de un usuario
 * inactivo (la fila editada a mano, sin el evento que revoca los tokens) responde 403
 * USER_INACTIVE en las rutas con la clínica (SetCurrentClinic), también en las del
 * perfil (me/password y me/current-branch). logout sigue funcionando.
 */
class InactiveUserTokenTest extends TestCase
{
    use InteractsWithAuth, InteractsWithBranches;

    public function test_a_valid_token_of_an_inactive_user_is_rejected_but_logout_works(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $token = $this->tokenFor($user);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

        foreach (['/api/me', '/api/clients', '/api/branches'] as $uri) {
            $response = $this->getWithToken($token, $uri);

            $this->assertErrorCode($response, 403, 'USER_INACTIVE');
            $response->assertJsonPath('message', 'Tu usuario está inactivo: consultá con el administrador de la clínica.');
        }

        $this->postWithToken($token, '/api/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * Las rutas del perfil también pasan por SetCurrentClinic: un inactivo no cambia su
     * contraseña ni su sucursal actual con un token que quedó vigente.
     */
    public function test_an_inactive_user_does_not_change_the_own_password_nor_the_current_branch(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $user = $this->userWithRole($clinic, 'recepcionista', ['current_branch_id' => $default->id]);
        $user->branches()->attach([$default->id, $north->id]);
        $token = $this->tokenFor($user);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $hash = DB::table('users')->where('id', $user->id)->value('password');

        $this->forgetAuthenticatedUser();
        $response = $this->withToken($token)->patchJson('/api/me/password', ['current_password' => 'password', 'password' => 'Otra-Clave-2026!']);

        $this->assertErrorCode($response, 403, 'USER_INACTIVE');
        $this->assertSame($hash, DB::table('users')->where('id', $user->id)->value('password'));

        $this->forgetAuthenticatedUser();
        $response = $this->withToken($token)->patchJson('/api/me/current-branch', ['branch_id' => $north->id]);

        $this->assertErrorCode($response, 403, 'USER_INACTIVE');
        $this->assertSame($default->id, DB::table('users')->where('id', $user->id)->value('current_branch_id'));
    }
}
