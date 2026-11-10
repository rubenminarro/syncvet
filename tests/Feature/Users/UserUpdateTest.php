<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Edición de un usuario (FASE 2, entrega 2.2a): roles, último administrador (P4),
 * sucursales y sucursal actual, contraseña (pregunta 20) y email.
 */
class UserUpdateTest extends TestCase
{
    use InteractsWithUsers;

    public function test_the_roles_are_synced_and_recorded_as_roles_synced(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $this->actAsUserManager($clinic);

        $this->patchJson("/api/users/{$user->id}", ['roles' => ['veterinario', 'recepcionista']])
            ->assertOk()
            ->assertJsonPath('data.roles.*.name', ['recepcionista', 'veterinario']);

        $log = DB::table('audit_logs')->where('auditable_type', 'user')->where('auditable_id', $user->id)->where('event', 'roles_synced')->sole();
        $this->assertSame(['roles' => ['recepcionista']], json_decode($log->old_values, true));
        $this->assertSame(['roles' => ['recepcionista', 'veterinario']], json_decode($log->new_values, true));

        // Los mismos roles en otro orden: sin cambios, sin otro registro.
        $this->patchJson("/api/users/{$user->id}", ['roles' => ['recepcionista', 'veterinario']])->assertOk();
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_id', $user->id)->where('event', 'roles_synced')->count());
    }

    /**
     * P4: nadie deja la clínica sin administrador. No cuentan un administrador
     * eliminado ni el de otra clínica.
     */
    public function test_the_last_administrator_keeps_the_role(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $this->userWithRoles($clinic, ['administrador'])->delete();
        $this->userWithRoles($this->createClinic(), ['administrador']);
        $this->actAsUserManager($clinic);

        $response = $this->patchJson("/api/users/{$admin->id}", ['roles' => ['recepcionista'], 'first_name' => 'Otro']);

        $this->assertErrorCode($response, 409, 'USER_LAST_ADMIN');
        $response->assertJsonPath('message', 'La clínica tiene que tener al menos un administrador activo.');
        $this->assertSame(['administrador'], $admin->fresh()->getRoleNames()->all());
        $this->assertNotSame('Otro', $admin->fresh()->first_name, 'El 409 dejó cambios a medias.');

        // Sumarle otro rol sin quitarle el de administrador, sí.
        $this->patchJson("/api/users/{$admin->id}", ['roles' => ['administrador', 'veterinario']])->assertOk();
    }

    public function test_with_another_administrator_the_role_can_be_removed(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $this->userWithRoles($clinic, ['administrador', 'veterinario']);
        $this->actAsUserManager($clinic);

        $this->patchJson("/api/users/{$admin->id}", ['roles' => ['recepcionista']])
            ->assertOk()
            ->assertJsonPath('data.roles.*.name', ['recepcionista']);
    }

    public function test_changing_the_branches_moves_the_current_branch_when_it_leaves_the_list(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $south = $this->branchIn($clinic, ['code' => 'SUC-03']);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$north, $south], ['current_branch_id' => $north->id]);
        $this->actAsUserManager($clinic);

        // La actual sigue en la lista: no cambia.
        $this->patchJson("/api/users/{$user->id}", ['branch_ids' => [$south->id, $north->id, $default->id]])
            ->assertOk()
            ->assertJsonPath('data.current_branch_id', $north->id)
            ->assertJsonCount(3, 'data.branches');

        // Sale de la lista: la por defecto, si está.
        $this->patchJson("/api/users/{$user->id}", ['branch_ids' => [$south->id, $default->id]])
            ->assertOk()
            ->assertJsonPath('data.current_branch_id', $default->id);

        // Sale otra vez, sin la por defecto: la primera de la lista.
        $this->patchJson("/api/users/{$user->id}", ['branch_ids' => [$north->id, $south->id]])
            ->assertOk()
            ->assertJsonPath('data.current_branch_id', $north->id);

        $this->assertSame([$north->id, $south->id], DB::table('branch_user')->where('user_id', $user->id)->orderBy('branch_id')->pluck('branch_id')->map(fn ($id) => (int) $id)->all());
        $this->assertGreaterThan(0, DB::table('audit_logs')->where('auditable_id', $user->id)->where('event', 'updated')->where('new_values', 'like', '%branch_ids%')->count());
    }

    /**
     * Al editar se acepta una sucursal inactiva que el usuario ya tiene (el
     * formulario la reenvía); otra inactiva, no.
     */
    public function test_an_inactive_branch_the_user_already_has_is_accepted(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $kept = $this->branchIn($clinic);
        $other = $this->branchIn($clinic, ['is_active' => false]);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$default, $kept], ['current_branch_id' => $default->id]);
        DB::table('branches')->where('id', $kept->id)->update(['is_active' => false]);
        $this->actAsUserManager($clinic);

        $this->patchJson("/api/users/{$user->id}", ['branch_ids' => [$default->id, $kept->id]])->assertOk();

        $this->patchJson("/api/users/{$user->id}", ['branch_ids' => [$default->id, $other->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['branch_ids.1' => ['La sucursal no existe o está inactiva.']]);

        // Si la actual sale y solo queda la inactiva, se queda sin sucursal actual.
        $this->patchJson("/api/users/{$user->id}", ['branch_ids' => [$kept->id]])
            ->assertOk()
            ->assertJsonPath('data.current_branch_id', null);
    }

    public function test_a_password_change_revokes_the_tokens_of_the_user(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $user->createToken('api');
        $user->createToken('api');
        $colleague = $this->userWithRoles($clinic, ['recepcionista']);
        $colleague->createToken('api');
        $this->actAsUserManager($clinic);

        // Sin contraseña, las sesiones siguen.
        $this->patchJson("/api/users/{$user->id}", ['first_name' => 'Marta'])->assertOk();
        $this->assertSame(2, $user->tokens()->count());

        $this->patchJson("/api/users/{$user->id}", ['password' => 'Otra-Clave-2026!'])->assertOk()->assertJsonMissingPath('data.password');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $colleague->tokens()->count());
        $this->assertTrue(Hash::check('Otra-Clave-2026!', $user->fresh()->password));

        $this->patchJson("/api/users/{$user->id}", ['password' => 'corta'])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    /**
     * Con tokens reales: el cambio de contraseña de otro usuario borra los tokens de ese
     * usuario, y el del administrador que lo hace sigue sirviendo. La contraseña y su
     * hash no llegan a la auditoría de la edición.
     */
    public function test_a_password_change_keeps_the_token_of_the_administrator_and_stays_out_of_the_audit(): void
    {
        $clinic = $this->createClinic();
        [$admin, $token] = $this->administratorWithToken($clinic);
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $userToken = $user->createToken('api')->plainTextToken;

        $this->withRealToken($userToken)->getJson('/api/me')->assertOk();
        $this->withRealToken($token)->patchJson("/api/users/{$user->id}", ['password' => 'Otra-Clave-2026!'])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $admin->tokens()->count());
        $this->withRealToken($token)->getJson('/api/me')->assertOk();
        $this->assertErrorCode($this->withRealToken($userToken)->getJson('/api/me'), 401, 'UNAUTHENTICATED');

        $log = DB::table('audit_logs')->where('auditable_type', 'user')->where('auditable_id', $user->id)->where('event', 'updated')->get();
        $this->assertNotEmpty($log, 'La edición de la contraseña no quedó en la auditoría.');
        $this->assertStringNotContainsString('Otra-Clave-2026!', $log->toJson());
        $this->assertStringNotContainsString('$2y$', $log->toJson(), 'El hash de la contraseña llegó a la auditoría.');
        $this->assertStringNotContainsString($user->fresh()->password, $log->toJson());
    }

    /**
     * La contraseña propia se cambia desde el perfil (PATCH me/password, 2.2b), que pide
     * la actual y conserva el token en uso: aquí → 422, y la sesión sigue.
     */
    public function test_the_administrator_does_not_change_the_own_password_here(): void
    {
        $clinic = $this->createClinic();
        [$admin, $token] = $this->administratorWithToken($clinic);
        $hash = $admin->password;

        $this->withRealToken($token)->patchJson("/api/users/{$admin->id}", ['first_name' => 'Ramona'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Ramona');
        $this->withRealToken($token)->getJson('/api/me')->assertOk();

        foreach (['Nueva-Clave-2026!', '', null] as $password) {
            $response = $this->withRealToken($token)->patchJson("/api/users/{$admin->id}", ['password' => $password, 'last_name' => 'Cambiado']);

            $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
            $response->assertJsonPath('errors', ['password' => ['Para cambiar tu contraseña usá tu perfil.']]);
        }

        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertNotSame('Cambiado', $admin->fresh()->last_name);
        $this->assertSame(1, $admin->tokens()->count());
        $this->withRealToken($token)->getJson('/api/me')->assertOk();

        // La de otro usuario, sí.
        $other = $this->userWithRoles($clinic, ['recepcionista']);
        $this->withRealToken($token)->patchJson("/api/users/{$other->id}", ['password' => 'Nueva-Clave-2026!'])->assertOk();
    }

    /**
     * P4 con un administrador de verdad (rol administrador, no permisos sueltos) que se
     * quita su propio rol: solo, 409; con otro administrador, 200.
     */
    public function test_a_real_administrator_removes_the_own_role(): void
    {
        $clinic = $this->createClinic();
        [$admin, $token] = $this->administratorWithToken($clinic);

        $response = $this->withRealToken($token)->patchJson("/api/users/{$admin->id}", ['roles' => ['recepcionista']]);

        $this->assertErrorCode($response, 409, 'USER_LAST_ADMIN');
        $this->assertSame(['administrador'], $admin->fresh()->getRoleNames()->all());

        $this->userWithRoles($clinic, ['administrador']);

        $this->withRealToken($token)->patchJson("/api/users/{$admin->id}", ['roles' => ['recepcionista']])
            ->assertOk()
            ->assertJsonPath('data.roles.*.name', ['recepcionista']);
        $this->assertSame(['recepcionista'], $admin->fresh()->getRoleNames()->all());
    }

    /**
     * Un usuario sin sucursal actual que recibe sucursales toma una con la regla del
     * alta: la por defecto si está en la lista; si no, la primera activa.
     */
    public function test_a_user_without_current_branch_gets_one_with_the_rule_of_the_store(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $north = $this->branchIn($clinic, ['code' => 'SUC-02']);
        $south = $this->branchIn($clinic, ['code' => 'SUC-03']);
        $inactive = $this->branchIn($clinic, ['code' => 'SUC-04']);
        $withDefault = $this->userWithRoles($clinic, ['recepcionista']);
        $withoutDefault = $this->userWithRoles($clinic, ['recepcionista'], [$inactive]);
        DB::table('branches')->where('id', $inactive->id)->update(['is_active' => false]);
        $this->actAsUserManager($clinic);

        $this->patchJson("/api/users/{$withDefault->id}", ['branch_ids' => [$north->id, $default->id]])
            ->assertOk()
            ->assertJsonPath('data.current_branch_id', $default->id);

        $this->patchJson("/api/users/{$withoutDefault->id}", ['branch_ids' => [$inactive->id, $south->id, $north->id]])
            ->assertOk()
            ->assertJsonPath('data.current_branch_id', $south->id);
    }

    public function test_the_email_stays_unique_and_its_own_is_accepted(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['recepcionista'], [], ['email' => 'marta@example.com']);
        User::factory()->for($this->createClinic())->create(['email' => 'tomado@example.com']);
        $this->actAsUserManager($clinic);

        $this->patchJson("/api/users/{$user->id}", ['email' => 'TOMADO@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Ya hay un usuario con ese email.');

        $this->patchJson("/api/users/{$user->id}", ['email' => 'Marta@Example.com'])->assertOk()->assertJsonPath('data.email', 'marta@example.com');
        $this->patchJson("/api/users/{$user->id}", ['email' => 'marta.nueva@example.com'])->assertOk()->assertJsonPath('data.email', 'marta.nueva@example.com');
    }

    public static function verbs(): array
    {
        return ['PATCH' => ['patch'], 'PUT' => ['put']];
    }

    /**
     * PUT con la semántica parcial de PATCH (HttpVerbsTest); el nombre completo se
     * vuelve a armar.
     */
    #[DataProvider('verbs')]
    public function test_a_partial_update_keeps_the_fields_not_sent(string $verb): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $user = $this->userWithRoles($clinic, ['veterinario'], [$branch], [
            'first_name' => 'Marta', 'last_name' => 'Gómez', 'name' => 'Marta Gómez', 'email' => 'marta@example.com', 'current_branch_id' => $branch->id,
        ]);
        $this->actAsUserManager($clinic);

        $this->json($verb, "/api/users/{$user->id}", ['last_name' => 'Ortiz'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Marta')
            ->assertJsonPath('data.last_name', 'Ortiz')
            ->assertJsonPath('data.name', 'Marta Ortiz')
            ->assertJsonPath('data.email', 'marta@example.com')
            ->assertJsonPath('data.roles.0.name', 'veterinario')
            ->assertJsonPath('data.branches.0.id', $branch->id)
            ->assertJsonPath('data.current_branch_id', $branch->id);

        $this->assertSame(['veterinario'], $user->fresh()->getRoleNames()->all());
    }

    public function test_an_empty_list_or_a_null_value_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['recepcionista']);
        $this->actAsUserManager($clinic);

        foreach ([['roles' => []], ['branch_ids' => []], ['first_name' => ''], ['email' => null], ['password' => null]] as $payload) {
            $this->patchJson("/api/users/{$user->id}", $payload)->assertUnprocessable();
        }
    }

    public function test_the_detail_shows_the_user_without_secrets(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $user = $this->userWithRoles($clinic, ['veterinario'], [$branch], ['current_branch_id' => $branch->id]);
        $this->actAsUserManager($clinic);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.roles', [['name' => 'veterinario', 'label' => 'Veterinario']])
            ->assertJsonPath('data.branches.0.code', 'SUC-01')
            ->assertJsonStructure(['data' => ['created_at', 'updated_at']])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            // Sin ficha de veterinario (2.3).
            ->assertJsonPath('data.veterinarian', null);
    }

    /**
     * Entrega 2.3 (pregunta 30): con una ficha de veterinario no eliminada, el usuario no
     * pierde el rol veterinario (409 USER_IS_VETERINARIAN, sin cambios a medias); con la
     * ficha eliminada, sí. El detalle muestra la ficha.
     */
    public function test_a_user_with_a_veterinarian_profile_keeps_the_veterinarian_role(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['veterinario']);
        $id = DB::table('veterinarians')->insertGetId([
            'clinic_id' => $clinic->id, 'user_id' => $user->id, 'code' => 'VET-000001',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actAsUserManager($clinic);

        $this->getJson("/api/users/{$user->id}")->assertOk()->assertJsonPath('data.veterinarian', ['id' => $id, 'code' => 'VET-000001']);

        $response = $this->patchJson("/api/users/{$user->id}", ['roles' => ['recepcionista'], 'first_name' => 'Otra']);

        $this->assertErrorCode($response, 409, 'USER_IS_VETERINARIAN');
        $response->assertJsonPath('message', 'El usuario tiene una ficha de veterinario: eliminala antes de quitarle el rol.');
        $this->assertSame(['veterinario'], $user->fresh()->getRoleNames()->all());
        $this->assertNotSame('Otra', $user->fresh()->first_name, 'El 409 dejó cambios a medias.');

        // Sumarle otro rol sin quitarle el de veterinario, sí.
        $this->patchJson("/api/users/{$user->id}", ['roles' => ['veterinario', 'recepcionista']])->assertOk();

        // Con la ficha eliminada, el rol se quita.
        DB::table('veterinarians')->where('id', $id)->update(['deleted_at' => now()]);
        $this->patchJson("/api/users/{$user->id}", ['roles' => ['recepcionista']])
            ->assertOk()
            ->assertJsonPath('data.roles.*.name', ['recepcionista'])
            ->assertJsonPath('data.veterinarian', null);
    }
}
