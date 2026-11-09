<?php

namespace Tests\Feature\Veterinarians;

use App\Models\Veterinarian;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * Alta de un veterinario (FASE 2, entrega 2.3; P6 y P7): un usuario activo de la clínica
 * con el rol veterinario y sin ficha; código VET- de la serie; matrícula única en la
 * clínica; comisión de 0 a 100 con hasta 2 decimales.
 */
class VeterinarianStoreTest extends TestCase
{
    use InteractsWithVeterinarians;

    public function test_creates_veterinarians_with_correlative_vet_codes(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $first = $this->veterinarianUser($clinic, [$branch], ['first_name' => 'Marta', 'last_name' => 'Giménez', 'name' => 'Marta Giménez']);
        $second = $this->veterinarianUser($clinic);
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', [
            'user_id' => $first->id,
            'license_number' => 'MV-1001',
            'specialty' => 'Cirugía',
            'commission_percentage' => 12.5,
            'notes' => 'Atiende los sábados.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Veterinario creado correctamente.')
            ->assertJsonPath('data.code', 'VET-000001')
            ->assertJsonPath('data.user.id', $first->id)
            ->assertJsonPath('data.user.name', 'Marta Giménez')
            ->assertJsonPath('data.user.is_active', true)
            ->assertJsonPath('data.license_number', 'MV-1001')
            ->assertJsonPath('data.specialty', 'Cirugía')
            ->assertJsonPath('data.commission_percentage', '12.50')
            ->assertJsonPath('data.status.value', 'active')
            ->assertJsonPath('data.status.label', 'Activo')
            ->assertJsonPath('data.notes', 'Atiende los sábados.')
            ->assertJsonPath('data.branches.0.id', $branch->id);

        // Sin los opcionales: los valores por defecto.
        $this->postJson('/api/veterinarians', ['user_id' => $second->id])
            ->assertCreated()
            ->assertJsonPath('data.code', 'VET-000002')
            ->assertJsonPath('data.license_number', null)
            ->assertJsonPath('data.commission_percentage', '0.00')
            ->assertJsonPath('data.status.value', 'active')
            ->assertJsonPath('data.branches', []);

        $id = CurrentClinic::run($clinic, fn () => Veterinarian::where('user_id', $first->id)->value('id'));
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'veterinarian')->where('auditable_id', $id)->where('event', 'created')->count());
    }

    public function test_the_user_must_have_the_veterinarian_role(): void
    {
        $clinic = $this->createClinic();
        $receptionist = $this->userWithRoles($clinic, ['recepcionista', 'administrador']);
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $receptionist->id])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.user_id.0', 'El usuario no tiene el rol veterinario.');

        $this->assertSame(0, CurrentClinic::run($clinic, fn () => Veterinarian::withTrashed()->count()));
    }

    /**
     * Inactivo, de otra clínica, eliminado o inexistente: el mismo mensaje.
     */
    public static function unavailableUsers(): array
    {
        return [
            'inactivo' => ['inactive'],
            'de otra clínica' => ['foreign'],
            'eliminado' => ['deleted'],
            'inexistente' => ['missing'],
        ];
    }

    #[DataProvider('unavailableUsers')]
    public function test_the_user_must_be_an_active_user_of_the_clinic(string $case): void
    {
        $clinic = $this->createClinic();
        $userId = match ($case) {
            'inactive' => $this->veterinarianUser($clinic, [], ['is_active' => false])->id,
            'foreign' => $this->veterinarianUser($this->createClinic())->id,
            'deleted' => tap($this->veterinarianUser($clinic))->delete()->id,
            'missing' => 999999,
        };
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $userId])
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_id.0', 'El usuario no existe o está inactivo.');

        $this->assertSame(0, DB::table('veterinarians')->count());
    }

    public function test_a_user_with_a_profile_is_not_a_veterinarian_twice(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $this->veterinarianOf($user);
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $user->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_id.0', 'Ese usuario ya es veterinario.');

        $this->assertSame(1, DB::table('veterinarians')->count());
    }

    public function test_the_code_is_assigned_by_the_series(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'code' => 'VET-999999'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'El código del veterinario se asigna automáticamente.');
    }

    /**
     * Única en la clínica: la de otra clínica o la de una ficha eliminada (con su sufijo)
     * no chocan.
     */
    public function test_the_license_number_is_unique_in_the_clinic(): void
    {
        $clinic = $this->createClinic();
        $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => 'MV-1001']);
        $this->veterinarianOf($this->veterinarianUser($this->createClinic()), ['license_number' => 'MV-2002']);
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'license_number' => 'MV-1001'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.license_number.0', 'Ya hay un veterinario con esa matrícula.');

        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'license_number' => str_repeat('1', 31)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.license_number.0', 'La matrícula no debe tener más de 30 caracteres.');

        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'license_number' => 'MV-2002'])
            ->assertCreated();
    }

    public static function invalidCommissions(): array
    {
        return [
            'negativa' => [-1, 'La comisión debe estar entre 0 y 100.'],
            'más de 100' => [100.01, 'La comisión debe estar entre 0 y 100.'],
            'tres decimales' => ['12.345', 'La comisión puede tener hasta 2 decimales.'],
            'texto' => ['diez', 'La comisión debe ser un número.'],
            'nula' => [null, 'La comisión es obligatoria.'],
        ];
    }

    #[DataProvider('invalidCommissions')]
    public function test_the_commission_goes_from_0_to_100_with_up_to_2_decimals(mixed $commission, string $message): void
    {
        $clinic = $this->createClinic();
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'commission_percentage' => $commission])
            ->assertUnprocessable()
            ->assertJsonPath('errors.commission_percentage.0', $message);
    }

    public function test_the_limits_of_the_commission_and_the_status_are_accepted(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVeterinarianManager($clinic);

        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'commission_percentage' => 100, 'status' => 'inactive'])
            ->assertCreated()
            ->assertJsonPath('data.commission_percentage', '100.00')
            ->assertJsonPath('data.status.value', 'inactive');

        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'status' => 'retired'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'El estado no es válido: debe ser active (activo) o inactive (inactivo).');
    }

    /**
     * La recepción y el veterinario solo listan (veterinarians.index): 403 en las demás.
     */
    public function test_the_receptionist_and_the_veterinarian_only_list(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic));

        foreach (['recepcionista', 'veterinario'] as $role) {
            $this->actingAs($this->userWithRoles($clinic, [$role]), 'sanctum');

            $this->getJson('/api/veterinarians')->assertOk()->assertJsonCount(1, 'data');
            $this->assertErrorCode($this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id]), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->getJson("/api/veterinarians/{$veterinarian->id}"), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->patchJson("/api/veterinarians/{$veterinarian->id}", ['status' => 'inactive']), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->deleteJson("/api/veterinarians/{$veterinarian->id}"), 403, 'FORBIDDEN');
        }

        $this->assertSame(1, DB::table('veterinarians')->count());
        $this->assertSame('active', DB::table('veterinarians')->value('status'));
    }
}
