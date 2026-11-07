<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * Roles que se asignan a un usuario (FASE 2, entrega 2.2a).
 */
class RolesIndexTest extends TestCase
{
    use InteractsWithUsers;

    public function test_the_three_roles_with_their_spanish_label(): void
    {
        $this->actAsUserManager($this->createClinic());

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonPath('data', [
                ['name' => 'administrador', 'label' => 'Administrador'],
                ['name' => 'recepcionista', 'label' => 'Recepcionista'],
                ['name' => 'veterinario', 'label' => 'Veterinario'],
            ])
            ->assertJsonPath('meta', null);
    }

    /**
     * Cada rol de la lista existe en Spatie (el seeder), y los roles de la clínica del
     * seeder están en la lista: un rol nuevo en un lado y no en el otro hace fallar.
     */
    public function test_the_list_matches_the_seeded_roles(): void
    {
        $seeded = ['administrador', ...array_keys(RolesAndPermissionsSeeder::clinicRoles())];
        sort($seeded);

        $this->assertSame($seeded, collect(UserRole::values())->sort()->values()->all());

        foreach ($seeded as $name) {
            $this->assertNotNull(Role::findByName($name, 'api'));
        }
    }
}
