<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\User;

/**
 * Usuarios (FASE 2, entrega 2.2a). Los tests usan un usuario con los permisos de
 * usuarios y roles, no el administrador, que pasa todo por Gate::before: así el
 * que hace el pedido no cuenta como administrador de la clínica.
 */
trait InteractsWithUsers
{
    use InteractsWithBranches;

    protected const USER_PERMISSIONS = ['users.index', 'users.store', 'users.show', 'users.update', 'roles.index'];

    /**
     * Contraseña válida para la regla del proyecto (R-009); el Have I Been Pwned
     * simulado no la informa como filtrada.
     */
    protected const VALID_PASSWORD = 'Clave-Segura-2026!';

    /**
     * El que hace el pedido sale del listado de la clínica: con nombre, apellido y email
     * fijos (al final del orden y sin coincidir con las búsquedas de los tests), no los
     * de la factory, que al azar podían ordenarse primero.
     */
    protected function actAsUserManager(Clinic $clinic): User
    {
        static $sequence = 0;
        $sequence++;

        $user = $this->actingIn($clinic, self::USER_PERMISSIONS);
        $user->forceFill(['first_name' => 'Zoe', 'last_name' => 'Zubizarreta', 'name' => 'Zoe Zubizarreta', 'email' => "gestor{$sequence}@example.com"])->save();

        return $user;
    }

    /**
     * Un administrador de verdad (rol administrador, Gate::before) con un token guardado
     * en la base: el pedido pasa por Sanctum como en producción.
     *
     * @return array{0: User, 1: string}
     */
    protected function administratorWithToken(Clinic $clinic): array
    {
        $admin = $this->userWithRoles($clinic, ['administrador']);

        return [$admin, $admin->createToken('api')->plainTextToken];
    }

    /**
     * Un pedido con el token, sin el usuario que el guard recuerda del pedido anterior.
     */
    protected function withRealToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /**
     * Usuario con roles de la clínica y, si se indican, sus sucursales.
     *
     * @param list<string> $roles
     * @param list<Branch> $branches
     */
    protected function userWithRoles(Clinic $clinic, array $roles, array $branches = [], array $attributes = []): User
    {
        $user = User::factory()->for($clinic)->create($attributes);
        $user->syncRoles($roles);

        if ($branches !== []) {
            $user->branches()->attach(array_map(fn (Branch $branch) => $branch->id, $branches));
        }

        return $user;
    }

    /**
     * Datos válidos para el alta de un usuario por la API.
     *
     * @param list<int> $branchIds
     */
    protected function userPayload(array $branchIds, array $overrides = []): array
    {
        return [
            'first_name' => 'Lucía',
            'last_name' => 'Benítez',
            'email' => 'lucia.benitez@example.com',
            'password' => self::VALID_PASSWORD,
            'roles' => ['recepcionista'],
            'branch_ids' => $branchIds,
            ...$overrides,
        ];
    }
}
