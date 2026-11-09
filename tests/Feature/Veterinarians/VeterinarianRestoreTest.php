<?php

namespace Tests\Feature\Veterinarians;

use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * Restauración (FASE 2, entrega 2.3; P6): el alta de un usuario con una ficha
 * eliminada la restaura (misma fila y mismo código VET-) con los datos del pedido.
 */
class VeterinarianRestoreTest extends TestCase
{
    use InteractsWithVeterinarians;

    public function test_the_deleted_profile_is_restored_with_the_data_of_the_request_and_the_same_code(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $veterinarian = $this->veterinarianOf($user, ['license_number' => 'MV-1001', 'specialty' => 'Cirugía', 'commission_percentage' => 20, 'notes' => 'Vieja']);
        $this->actAsVeterinarianManager($clinic);
        $this->deleteJson("/api/veterinarians/{$veterinarian->id}")->assertOk();

        $this->postJson('/api/veterinarians', ['user_id' => $user->id, 'license_number' => 'MV-1001', 'commission_percentage' => 5])
            ->assertCreated()
            ->assertJsonPath('data.id', $veterinarian->id)
            ->assertJsonPath('data.code', 'VET-000001')
            ->assertJsonPath('data.license_number', 'MV-1001')
            ->assertJsonPath('data.commission_percentage', '5.00')
            // Lo que no llega vuelve a su valor por defecto, como en un alta.
            ->assertJsonPath('data.specialty', null)
            ->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.status.value', 'active');

        $this->assertSame(1, DB::table('veterinarians')->count());
        $this->assertNull(DB::table('veterinarians')->value('deleted_at'));

        $logs = DB::table('audit_logs')->where('auditable_type', 'veterinarian')->where('auditable_id', $veterinarian->id);
        $this->assertSame(1, (clone $logs)->where('event', 'restored')->count());

        // La restauración no consume la serie: la próxima ficha nueva sigue con VET-000002.
        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id])
            ->assertCreated()
            ->assertJsonPath('data.code', 'VET-000002');
    }

    /**
     * Precisión 2 del usuario: la matrícula del pedido la tiene otro veterinario de la
     * clínica → 422, igual que un alta. La ficha sigue eliminada.
     */
    public function test_restoring_with_a_license_number_of_another_veterinarian_fails_like_an_alta(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $veterinarian = $this->veterinarianOf($user, ['license_number' => 'MV-1001']);
        $this->actAsVeterinarianManager($clinic);
        $this->deleteJson("/api/veterinarians/{$veterinarian->id}")->assertOk();
        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'license_number' => 'MV-1001'])->assertCreated();

        $this->postJson('/api/veterinarians', ['user_id' => $user->id, 'license_number' => 'MV-1001'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.license_number.0', 'Ya hay un veterinario con esa matrícula.');

        $this->assertTrue(CurrentClinic::run($clinic, fn () => Veterinarian::withTrashed()->findOrFail($veterinarian->id)->trashed()));
        $this->assertSame(0, DB::table('audit_logs')->where('auditable_id', $veterinarian->id)->where('auditable_type', 'veterinarian')->where('event', 'restored')->count());
    }

    /**
     * El índice único es el respaldo: con la regla de validación salteada (una matrícula
     * tomada entre la validación y el guardado), el mismo 422 y no un 500.
     */
    public function test_the_unique_index_backs_the_license_number_on_restore(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $veterinarian = $this->veterinarianOf($user);
        $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => 'MV-1001']);
        CurrentClinic::run($clinic, fn () => $veterinarian->delete());

        $this->expectValidationOn('license_number', fn () => CurrentClinic::run(
            $clinic,
            fn () => app(\App\Services\VeterinarianService::class)->create(['user_id' => $user->id, 'license_number' => 'MV-1001'])
        ));

        $this->assertTrue(CurrentClinic::run($clinic, fn () => Veterinarian::withTrashed()->findOrFail($veterinarian->id)->trashed()));
    }

    public function test_a_user_without_the_role_does_not_restore_the_profile(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $veterinarian = $this->veterinarianOf($user);
        CurrentClinic::run($clinic, fn () => $veterinarian->delete());
        $user->syncRoles(['recepcionista']);
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $user->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_id.0', 'El usuario no tiene el rol veterinario.');

        $this->assertTrue(CurrentClinic::run($clinic, fn () => Veterinarian::withTrashed()->findOrFail($veterinarian->id)->trashed()));
    }

    private function expectValidationOn(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail('No respondió un error de validación.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(['Ya hay un veterinario con esa matrícula.'], $e->errors()[$field] ?? null);
        }
    }
}
