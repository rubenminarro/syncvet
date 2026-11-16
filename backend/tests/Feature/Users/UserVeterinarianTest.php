<?php

namespace Tests\Feature\Users;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Entrega front-02 (pregunta 1 de la F2 del frontend, R-074): el listado de usuarios
 * trae la ficha de veterinario de cada uno (id y código; null sin ficha o con la ficha
 * eliminada), cargada sin N+1. El detalle, el alta y la edición no cambian sus claves.
 */
class UserVeterinarianTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithUsers;

    /** Las claves del listado, en orden. */
    private const LIST_KEYS = [
        'id', 'first_name', 'last_name', 'name', 'email', 'roles', 'branches', 'current_branch_id', 'is_active', 'veterinarian',
    ];

    /** Las claves del detalle (también el alta y la edición), en orden: las de antes de front-02. */
    private const DETAIL_KEYS = [
        'id', 'first_name', 'last_name', 'name', 'email', 'roles', 'branches', 'current_branch_id', 'is_active', 'veterinarian',
        'created_at', 'updated_at',
    ];

    public function test_the_list_shows_the_veterinarian_profile_or_null(): void
    {
        $clinic = $this->createClinic();
        $with = $this->userWithRoles($clinic, ['veterinario'], [], ['first_name' => 'Ana', 'last_name' => 'Acosta']);
        $this->userWithRoles($clinic, ['veterinario'], [], ['first_name' => 'Bea', 'last_name' => 'Benítez']);
        $deleted = $this->userWithRoles($clinic, ['veterinario'], [], ['first_name' => 'Ceci', 'last_name' => 'Cabrera']);
        $id = $this->profileFor($clinic, $with, 'VET-000001');
        $this->profileFor($clinic, $deleted, 'VET-000002', deleted: true);
        $this->actAsUserManager($clinic);

        $this->getJson('/api/users?role=veterinario')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.veterinarian', ['id' => $id, 'code' => 'VET-000001'])
            // Sin ficha.
            ->assertJsonPath('data.1.veterinarian', null)
            // Con la ficha eliminada, como el detalle: null.
            ->assertJsonPath('data.2.veterinarian', null);

        $this->getJson("/api/users/{$deleted->id}")->assertOk()->assertJsonPath('data.veterinarian', null);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_users(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsUserManager($clinic);
        $this->profileFor($clinic, $this->userWithRoles($clinic, ['veterinario'], [$branch]), 'VET-000001');
        // El primer pedido llena la caché de permisos de Spatie: no se cuenta.
        $this->getJson('/api/users?role=veterinario')->assertOk();

        $one = $this->countQueries(fn () => $this->getJson('/api/users?role=veterinario')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.veterinarian.code', 'VET-000001'));

        for ($index = 2; $index <= 5; $index++) {
            $this->profileFor($clinic, $this->userWithRoles($clinic, ['veterinario'], [$branch]), sprintf('VET-%06d', $index));
        }

        $many = $this->countQueries(fn () => $this->getJson('/api/users?role=veterinario')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonMissing(['veterinarian' => null]));

        $this->assertSame($one, $many, 'La cantidad de consultas crece con los usuarios (N+1).');
    }

    /**
     * Las claves del listado suman veterinarian; las del detalle, el alta y la edición
     * siguen siendo las de antes, en el mismo orden.
     */
    public function test_the_keys_of_the_list_and_of_the_detail(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $user = $this->userWithRoles($clinic, ['veterinario'], [$branch]);
        $id = $this->profileFor($clinic, $user, 'VET-000001');
        $this->actAsUserManager($clinic);

        $list = $this->getJson('/api/users?role=veterinario')->assertOk();
        $this->assertSame(self::LIST_KEYS, array_keys($list->json('data.0')));

        $detail = $this->getJson("/api/users/{$user->id}")->assertOk()
            ->assertJsonPath('data.veterinarian', ['id' => $id, 'code' => 'VET-000001']);
        $this->assertSame(self::DETAIL_KEYS, array_keys($detail->json('data')));

        $update = $this->patchJson("/api/users/{$user->id}", ['first_name' => 'Otra'])->assertOk()
            ->assertJsonPath('data.veterinarian', ['id' => $id, 'code' => 'VET-000001']);
        $this->assertSame(self::DETAIL_KEYS, array_keys($update->json('data')));

        $store = $this->postJson('/api/users', $this->userPayload([$branch->id]))->assertCreated()
            ->assertJsonPath('data.veterinarian', null);
        $this->assertSame(self::DETAIL_KEYS, array_keys($store->json('data')));
    }

    private function profileFor(Clinic $clinic, User $user, string $code, bool $deleted = false): int
    {
        return DB::table('veterinarians')->insertGetId([
            'clinic_id' => $clinic->id, 'user_id' => $user->id, 'code' => $code,
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deleted ? now() : null,
        ]);
    }
}
