<?php

namespace Tests\Feature\Veterinarians;

use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * Concurrencia de veterinarios (FASE 2, entrega 2.3). Orden de bloqueo: clínica →
 * usuario → veterinario → serie, en el alta y en la restauración; quitar el rol
 * veterinario (UserService) bloquea la clínica y el mismo usuario antes de buscar la
 * ficha. Como UserLockTest, se verifica el orden de las consultas dentro de la
 * transacción del Service; el "for update" solo lo escribe MySQL.
 */
class VeterinarianLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithVeterinarians;

    public function test_creating_locks_the_clinic_then_the_user_then_the_profile_then_the_series(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $this->actAsVeterinarianManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson('/api/veterinarians', ['user_id' => $user->id])->assertCreated());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('users'), 'El usuario se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->roleCheck(), 'Se revisó el rol antes de bloquear el usuario.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->profileLock(), 'Se buscó la ficha antes de bloquear el usuario.');
        $this->assertBefore($queries, $this->profileLock(), $this->seriesLock(), 'La serie se bloqueó antes que la ficha.');
    }

    public function test_restoring_locks_the_clinic_then_the_user_then_the_profile(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $veterinarian = $this->veterinarianOf($user);
        CurrentClinic::run($clinic, fn () => $veterinarian->delete());
        $this->actAsVeterinarianManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson('/api/veterinarians', ['user_id' => $user->id])->assertCreated());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('users'), 'El usuario se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->profileLock(), 'Se buscó la ficha antes de bloquear el usuario.');
        $this->assertNull($this->firstIndex($queries, $this->seriesLock()), 'La restauración no usa la serie.');
    }

    public function test_removing_the_veterinarian_role_locks_the_user_before_looking_for_the_profile(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $this->actAsUserManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/users/{$user->id}", ['roles' => ['recepcionista']])->assertOk());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('users'), 'El usuario se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('users'), $this->readOf('veterinarians'), 'Se buscó la ficha antes de bloquear el usuario.');
    }

    public function test_deleting_locks_the_profile_before_checking_the_usage(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic));
        $this->actAsVeterinarianManager($clinic);

        $queries = $this->queriesOf(fn () => $this->deleteJson("/api/veterinarians/{$veterinarian->id}")->assertOk());

        $this->assertBefore($queries, $this->lockOf('veterinarians'), $this->readOf('schedules'), 'Se revisó el uso antes de bloquear la ficha.');
    }

    /**
     * La búsqueda de la ficha del usuario (también eliminada), bloqueada.
     */
    private function profileLock(): callable
    {
        return fn (string $sql) => preg_match('/^select \* from [`"]veterinarians[`"] where [`"]user_id[`"] = \?/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update'));
    }

    private function seriesLock(): callable
    {
        return fn (string $sql) => str_starts_with($sql, 'select * from "document_sequences"') || str_starts_with($sql, 'select * from `document_sequences`');
    }

    /**
     * La lectura de los roles del usuario (Spatie).
     */
    private function roleCheck(): callable
    {
        return fn (string $sql) => str_contains($sql, 'from "roles"') || str_contains($sql, 'from `roles`');
    }
}
