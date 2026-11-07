<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Alta de un usuario con sus roles y sucursales (FASE 2, entrega 2.2a).
 */
class UserStoreTest extends TestCase
{
    use InteractsWithUsers;

    public function test_a_user_is_created_with_roles_and_branches(): void
    {
        $clinic = $this->createClinic();
        $default = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic, ['code' => 'SUC-02', 'name' => 'Sucursal Luque']);
        $this->actAsUserManager($clinic);

        $response = $this->postJson('/api/users', $this->userPayload([$other->id, $default->id], [
            'email' => 'Lucia.Benitez@Example.com',
            'roles' => ['veterinario', 'recepcionista'],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Lucía')
            ->assertJsonPath('data.last_name', 'Benítez')
            ->assertJsonPath('data.name', 'Lucía Benítez')
            ->assertJsonPath('data.email', 'lucia.benitez@example.com')
            ->assertJsonPath('data.roles', [
                ['name' => 'recepcionista', 'label' => 'Recepcionista'],
                ['name' => 'veterinario', 'label' => 'Veterinario'],
            ])
            ->assertJsonPath('data.branches', [
                ['id' => $default->id, 'code' => 'SUC-01', 'name' => 'Casa central'],
                ['id' => $other->id, 'code' => 'SUC-02', 'name' => 'Sucursal Luque'],
            ])
            // La por defecto, aunque no sea la primera de la lista.
            ->assertJsonPath('data.current_branch_id', $default->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $user = User::findOrFail($response->json('data.id'));
        $this->assertSame($clinic->id, $user->clinic_id);
        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->password));
        $this->assertSame(['recepcionista', 'veterinario'], $user->getRoleNames()->sort()->values()->all());
        // Sin tokens ni sesiones nuevas: el alta no inicia sesión.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_password_never_reaches_the_audit_log_and_the_roles_are_recorded(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);

        $id = $this->postJson('/api/users', $this->userPayload([$branch->id]))->assertCreated()->json('data.id');

        $logs = DB::table('audit_logs')->where('auditable_type', 'user')->where('auditable_id', $id)->get();
        $this->assertStringNotContainsString(self::VALID_PASSWORD, $logs->toJson());
        $this->assertStringNotContainsString('$2y$', $logs->toJson(), 'El hash de la contraseña llegó a la auditoría.');

        $roles = $logs->firstWhere('event', 'roles_synced');
        $this->assertNotNull($roles, 'El alta no registró ROLES_SYNCED.');
        $this->assertSame(['roles' => []], json_decode($roles->old_values, true));
        $this->assertSame(['roles' => ['recepcionista']], json_decode($roles->new_values, true));
    }

    public function test_the_current_branch_is_the_first_active_one_when_the_default_is_not_listed(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $first = $this->branchIn($clinic, ['code' => 'SUC-09']);
        $second = $this->branchIn($clinic, ['code' => 'SUC-03']);
        $this->actAsUserManager($clinic);

        $this->postJson('/api/users', $this->userPayload([$first->id, $second->id]))
            ->assertCreated()
            ->assertJsonPath('data.current_branch_id', $first->id);
    }

    public function test_the_email_must_be_ascii_and_at_most_150_characters(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);

        foreach (['lucía@example.com', 'no-es-un-email', str_repeat('a', 139) . '@example.com'] as $email) {
            $this->postJson('/api/users', $this->userPayload([$branch->id], ['email' => $email]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/users', $this->userPayload([$branch->id], ['email' => 'lucía@example.com']))
            ->assertJsonPath('errors.email.0', 'El email no puede tener acentos ni caracteres especiales.');

        // 150 caracteres justos, sí.
        $this->postJson('/api/users', $this->userPayload([$branch->id], ['email' => str_repeat('a', 138) . '@example.com']))
            ->assertCreated();
    }

    /**
     * El email es el login: único en toda la base, también el de otra clínica o el de
     * un usuario eliminado (R-029), sin distinguir mayúsculas.
     */
    public function test_the_email_is_unique_in_the_whole_database_including_deleted_users(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        User::factory()->for($this->createClinic())->create(['email' => 'ajeno@example.com']);
        User::factory()->for($clinic)->create(['email' => 'eliminado@example.com'])->delete();
        User::factory()->for($clinic)->create(['email' => 'propio@example.com']);
        $this->actAsUserManager($clinic);

        // El chequeo corre antes de escribir: el índice único es solo el respaldo de dos
        // altas simultáneas (abajo), no la regla.
        $inserts = 0;
        User::creating(function () use (&$inserts) {
            $inserts++;
        });

        foreach (['ajeno@example.com', 'ELIMINADO@example.com', 'propio@example.com'] as $email) {
            $response = $this->postJson('/api/users', $this->userPayload([$branch->id], ['email' => $email]));

            $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
            $response->assertJsonPath('errors.email.0', 'Ya hay un usuario con ese email.');
        }

        $this->assertSame(0, $inserts, 'Se intentó guardar un usuario con un email tomado.');

        $this->assertSame(0, User::withTrashed()->where('first_name', 'Lucía')->count());
    }

    /**
     * Dos altas simultáneas del mismo email en clínicas distintas no se ordenan con la
     * fila de la clínica: la segunda choca con el índice único y recibe el mismo 422,
     * no un 500.
     */
    public function test_an_email_taken_between_the_check_and_the_insert_answers_422(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->createClinic();
        $this->actAsUserManager($clinic);

        User::creating(function (User $user) use ($other) {
            if ($user->email === 'lucia.benitez@example.com') {
                DB::table('users')->insert([
                    'clinic_id' => $other->id, 'name' => 'Otra', 'first_name' => 'Otra', 'last_name' => 'Persona',
                    'email' => 'lucia.benitez@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $response = $this->postJson('/api/users', $this->userPayload([$branch->id]));

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.email.0', 'Ya hay un usuario con ese email.');
    }

    public function test_a_weak_or_leaked_password_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);
        $this->compromisedPasswords = ['Filtrada-2026!x'];

        foreach (['corta1!A', 'sinsimbolos2026AB', 'Filtrada-2026!x', ''] as $password) {
            $this->postJson('/api/users', $this->userPayload([$branch->id], ['password' => $password]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $this->postJson('/api/users', $this->userPayload([$branch->id], ['password' => 'Filtrada-2026!x']))
            ->assertJsonPath('errors.password.0', 'Esa contraseña aparece en filtraciones de datos conocidas: elegí otra.');
    }

    public function test_the_branches_must_be_active_branches_of_the_clinic(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $inactive = $this->branchIn($clinic, ['is_active' => false]);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $this->actAsUserManager($clinic);

        foreach ([[$foreign->id], [$inactive->id], [$branch->id, $foreign->id], [999999]] as $branchIds) {
            $this->postJson('/api/users', $this->userPayload($branchIds))
                ->assertUnprocessable()
                ->assertJsonPath('errors', fn (array $errors) => collect($errors)->keys()->every(fn ($key) => str_starts_with($key, 'branch_ids.')))
                ->assertJsonFragment(['La sucursal no existe o está inactiva.']);
        }

        foreach ([[], null, [$branch->id, $branch->id], 'x'] as $branchIds) {
            $this->postJson('/api/users', $this->userPayload([], ['branch_ids' => $branchIds]))->assertUnprocessable();
        }

        $this->assertSame(0, User::where('first_name', 'Lucía')->count());
    }

    public function test_the_roles_are_one_or_more_valid_roles_without_repetition(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);

        foreach ([[], ['superusuario'], ['recepcionista', 'recepcionista'], 'administrador', null] as $roles) {
            $this->postJson('/api/users', $this->userPayload([$branch->id], ['roles' => $roles]))->assertUnprocessable();
        }

        $this->postJson('/api/users', $this->userPayload([$branch->id], ['roles' => ['superusuario']]))
            ->assertJsonPath('errors', fn (array $errors) => str_starts_with($errors['roles.0'][0], 'El rol no es válido'));

        $this->postJson('/api/users', $this->userPayload([$branch->id], ['roles' => ['administrador']]))
            ->assertCreated()
            ->assertJsonPath('data.roles.0.name', 'administrador');
    }

    /**
     * Una lista vacía responde el mensaje de la lista, no el de un elemento (el
     * comodín de "roles.*" no alcanza a "roles").
     */
    public function test_an_empty_list_answers_the_message_of_the_list(): void
    {
        $clinic = $this->createClinic();
        $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);

        $this->postJson('/api/users', $this->userPayload([], ['roles' => []]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.roles.0', 'Elegí al menos un rol.')
            ->assertJsonPath('errors.branch_ids.0', 'Elegí al menos una sucursal.');

        $this->postJson('/api/users', $this->userPayload([], ['roles' => 'recepcionista', 'branch_ids' => 5]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.roles.0', 'Los roles deben ser una lista.')
            ->assertJsonPath('errors.branch_ids.0', 'Las sucursales deben ser una lista.');
    }

    /**
     * Nombre y apellido con la regla de los clientes: apóstrofo y guion entre letras,
     * nunca al principio, al final ni repetidos.
     */
    public function test_person_names_take_an_apostrophe_or_a_hyphen_between_letters(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);

        $this->postJson('/api/users', $this->userPayload([$branch->id], ['first_name' => 'Ana-María', 'last_name' => "D'Alessandro"]))
            ->assertCreated()
            ->assertJsonPath('data.name', "Ana-María D'Alessandro");

        foreach (["'Ana", "Ana'", '-Ana', 'Ana-', 'Ana--María', "D''Alessandro", 'Ana -María', 'Ana1'] as $name) {
            $this->postJson('/api/users', $this->userPayload([$branch->id], ['email' => 'otro@example.com', 'first_name' => $name]))
                ->assertUnprocessable()
                ->assertJsonPath('errors', ['first_name' => ['El nombre solo puede contener letras y espacios, y apóstrofos o guiones entre letras.']]);
        }
    }

    public function test_clinic_id_in_the_body_is_ignored(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->createClinic();
        $this->actAsUserManager($clinic);

        $id = $this->postJson('/api/users', $this->userPayload([$branch->id], ['clinic_id' => $other->id]))->assertCreated()->json('data.id');

        $this->assertSame($clinic->id, User::findOrFail($id)->clinic_id);
    }

    public function test_the_receptionist_and_the_veterinarian_cannot_manage_users(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $colleague = User::factory()->for($clinic)->create();

        foreach (['recepcionista', 'veterinario'] as $role) {
            $this->actingAs($this->userWithRoles($clinic, [$role]), 'sanctum');

            $this->assertErrorCode($this->postJson('/api/users', $this->userPayload([$branch->id])), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->getJson('/api/users'), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->getJson("/api/users/{$colleague->id}"), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->patchJson("/api/users/{$colleague->id}", ['first_name' => 'Otro']), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->getJson('/api/roles'), 403, 'FORBIDDEN');
        }
    }
}
