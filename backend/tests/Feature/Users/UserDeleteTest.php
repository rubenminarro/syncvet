<?php

namespace Tests\Feature\Users;

use App\Enums\CashMovementType;
use App\Models\User;
use App\Services\UserService;
use App\Support\DeletedSuffix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Baja de un usuario (FASE 2, entrega 2.2b; P1 y P4): solo sin uso; ni a sí mismo ni
 * al último administrador activo. El email queda libre (sufijo de baja), los tokens se
 * revocan y branch_user se conserva.
 */
class UserDeleteTest extends TestCase
{
    use InteractsWithUsers;

    public function test_a_user_without_usage_is_deleted_and_the_email_is_free_again(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$branch], ['email' => 'lucia.benitez@example.com']);
        $user->createToken('api');
        $this->actAsUserManager($clinic);

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Usuario eliminado correctamente.')
            ->assertJsonPath('data', null);

        $deleted = User::withTrashed()->findOrFail($user->id);
        $this->assertTrue($deleted->trashed());
        $this->assertStringStartsWith('lucia.benitez@example.com' . DeletedSuffix::PREFIX . $user->id . '_', $deleted->email);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, DB::table('branch_user')->where('user_id', $user->id)->count());

        $this->getJson("/api/users/{$user->id}")->assertNotFound();
        $this->postJson('/api/users', $this->userPayload([$branch->id]))->assertCreated();
    }

    public function test_a_user_with_a_veterinarian_profile_is_not_deleted_even_if_the_profile_was_deleted(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userWithRoles($clinic, ['veterinario']);
        DB::table('veterinarians')->insert([
            'clinic_id' => $clinic->id, 'user_id' => $user->id, 'code' => 'VET-000001',
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now(),
        ]);
        $this->actAsUserManager($clinic);

        $response = $this->deleteJson("/api/users/{$user->id}");

        $this->assertErrorCode($response, 409, 'USER_IN_USE');
        $response->assertJsonPath('message', 'El usuario tiene registros a su nombre y no se puede eliminar: inactivalo.');
        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_a_user_with_a_row_in_a_business_table_is_not_deleted(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $user = $this->userWithRoles($clinic, ['recepcionista'], [$branch]);
        $user->createToken('api');
        DB::table('cash_registers')->insert([
            'clinic_id' => $clinic->id, 'branch_id' => $branch->id, 'user_id' => $user->id,
            'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actAsUserManager($clinic);

        $this->assertErrorCode($this->deleteJson("/api/users/{$user->id}"), 409, 'USER_IN_USE');
        $this->assertFalse($user->fresh()->trashed());
        $this->assertSame(1, $user->tokens()->count(), 'El 409 revocó los tokens.');

        // Se inactiva.
        $this->patchJson("/api/users/{$user->id}", ['is_active' => false])->assertOk();
    }

    /**
     * Cada columna de USAGE cuenta, no solo la primera de su tabla: created_by (una
     * agenda bloqueada) y cancelled_by, la segunda de cash_movements (un movimiento de
     * caja que creó otro y anuló el usuario).
     */
    public function test_a_created_by_or_a_second_column_is_usage(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $cashier = $this->userWithRoles($clinic, ['recepcionista'], [$branch]);
        $this->actAsUserManager($clinic);

        $blocker = $this->userWithRoles($clinic, ['recepcionista'], [$branch]);
        $scheduleId = DB::table('schedules')->insertGetId([
            'clinic_id' => $clinic->id, 'branch_id' => $branch->id, 'name' => 'Consultorio 1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('schedule_blocks')->insert([
            'clinic_id' => $clinic->id, 'schedule_id' => $scheduleId, 'starts_at' => now(), 'ends_at' => now()->addHour(),
            'created_by' => $blocker->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $canceller = $this->userWithRoles($clinic, ['recepcionista'], [$branch]);
        $registerId = DB::table('cash_registers')->insertGetId([
            'clinic_id' => $clinic->id, 'branch_id' => $branch->id, 'user_id' => $cashier->id,
            'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cash_movements')->insert([
            'clinic_id' => $clinic->id, 'cash_register_id' => $registerId, 'type' => CashMovementType::EXPENSE->value,
            'amount' => '10000.00', 'concept' => 'Gasto menor', 'created_by' => $cashier->id,
            'cancelled_at' => now(), 'cancelled_by' => $canceller->id, 'cancellation_reason' => 'Carga duplicada',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([$blocker, $canceller] as $user) {
            $this->assertErrorCode($this->deleteJson("/api/users/{$user->id}"), 409, 'USER_IN_USE');
            $this->assertFalse($user->fresh()->trashed());
        }
    }

    public function test_the_administrator_does_not_delete_himself(): void
    {
        $clinic = $this->createClinic();
        [$admin, $token] = $this->administratorWithToken($clinic);
        $this->userWithRoles($clinic, ['administrador']);

        $this->assertErrorCode($this->withRealToken($token)->deleteJson("/api/users/{$admin->id}"), 409, 'USER_SELF_ACTION');
        $this->assertFalse($admin->fresh()->trashed());
        $this->withRealToken($token)->getJson('/api/me')->assertOk();
    }

    /**
     * P4: el último administrador activo no se da de baja; uno inactivo no lo salva
     * (desvío 7 de la 2.2a, R-032).
     */
    public function test_the_last_active_administrator_is_not_deleted(): void
    {
        $clinic = $this->createClinic();
        $admin = $this->userWithRoles($clinic, ['administrador']);
        $this->userWithRoles($clinic, ['administrador'], [], ['is_active' => false]);
        $this->actAsUserManager($clinic);

        $this->assertErrorCode($this->deleteJson("/api/users/{$admin->id}"), 409, 'USER_LAST_ADMIN');
        $this->assertFalse($admin->fresh()->trashed());

        $this->userWithRoles($clinic, ['administrador']);

        $this->deleteJson("/api/users/{$admin->id}")->assertOk();
    }

    /**
     * USAGE cubre toda clave foránea a users, salvo audit_logs (la auditoría no es uso)
     * y branch_user (la asignación se conserva): una tabla nueva que referencie a users
     * hace fallar este test hasta que se sume a la lista.
     */
    public function test_usage_covers_every_foreign_key_to_users(): void
    {
        $references = [];

        // Solo las tablas de la base del test: en MySQL el listado trae también las de las
        // otras bases del servidor.
        foreach (Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if ($foreignKey['foreign_table'] === 'users' && ! in_array($table, ['audit_logs', 'branch_user'], true)) {
                    foreach ($foreignKey['columns'] as $column) {
                        $references[$table][] = $column;
                    }
                }
            }
        }

        $normalize = function (array $map): array {
            ksort($map);

            return array_map(function (array $columns) {
                sort($columns);

                return $columns;
            }, $map);
        };

        $this->assertSame($normalize($references), $normalize(UserService::USAGE));
    }

    public function test_the_receptionist_cannot_delete_users(): void
    {
        $clinic = $this->createClinic();
        $colleague = $this->userWithRoles($clinic, ['recepcionista']);

        foreach (['recepcionista', 'veterinario'] as $role) {
            $this->actingAs($this->userWithRoles($clinic, [$role]), 'sanctum');

            $this->assertErrorCode($this->deleteJson("/api/users/{$colleague->id}"), 403, 'FORBIDDEN');
        }

        $this->assertFalse($colleague->fresh()->trashed());
    }
}
