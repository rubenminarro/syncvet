<?php

namespace Tests\Feature\Veterinarians;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * Edición de un veterinario (FASE 2, entrega 2.3): semántica parcial, se inactiva aunque
 * esté en uso, el usuario y el código no cambian, y el usuario no pierde el rol
 * veterinario mientras tenga la ficha (USER_IS_VETERINARIAN, pregunta 30).
 */
class VeterinarianUpdateTest extends TestCase
{
    use InteractsWithVeterinarians;

    public function test_the_data_is_updated_and_a_veterinarian_in_use_is_deactivated(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => 'MV-1001', 'specialty' => 'Clínica']);
        $this->usageRowFor($veterinarian, 'consultations');
        $this->actAsVeterinarianManager($clinic);

        $this->patchJson("/api/veterinarians/{$veterinarian->id}", ['status' => 'inactive', 'commission_percentage' => '7.25'])
            ->assertOk()
            ->assertJsonPath('message', 'Veterinario actualizado correctamente.')
            ->assertJsonPath('data.status.value', 'inactive')
            ->assertJsonPath('data.status.label', 'Inactivo')
            ->assertJsonPath('data.commission_percentage', '7.25')
            // Semántica parcial: lo que no llega no cambia.
            ->assertJsonPath('data.license_number', 'MV-1001')
            ->assertJsonPath('data.specialty', 'Clínica')
            ->assertJsonPath('data.code', 'VET-000001');

        // PUT, con la misma semántica parcial (HttpVerbsTest), y la misma matrícula: no choca consigo misma.
        $this->putJson("/api/veterinarians/{$veterinarian->id}", ['license_number' => 'MV-1001', 'specialty' => null])
            ->assertOk()
            ->assertJsonPath('data.specialty', null)
            ->assertJsonPath('data.status.value', 'inactive');

        $log = DB::table('audit_logs')->where('auditable_type', 'veterinarian')->where('auditable_id', $veterinarian->id)->where('event', 'updated')->first();
        $this->assertEquals(['status' => 'inactive', 'commission_percentage' => '7.25'], json_decode($log->new_values, true));
    }

    public function test_the_user_and_the_code_do_not_change(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic));
        $other = $this->veterinarianUser($clinic);
        $this->actAsVeterinarianManager($clinic);

        $this->patchJson("/api/veterinarians/{$veterinarian->id}", ['user_id' => $other->id, 'code' => 'VET-000099'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_id.0', 'El usuario del veterinario no se cambia.')
            ->assertJsonPath('errors.code.0', 'El código del veterinario se asigna automáticamente.');

        $this->assertSame($veterinarian->user_id, (int) DB::table('veterinarians')->where('id', $veterinarian->id)->value('user_id'));
    }

    public function test_the_license_number_of_another_veterinarian_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic));
        $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => 'MV-1001']);
        $this->actAsVeterinarianManager($clinic);

        $this->patchJson("/api/veterinarians/{$veterinarian->id}", ['license_number' => 'MV-1001'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.license_number.0', 'Ya hay un veterinario con esa matrícula.');

        // Normalizada antes de validar (desvío 7): en minúsculas también choca, en SQLite también.
        $this->patchJson("/api/veterinarians/{$veterinarian->id}", ['license_number' => ' mv-1001 '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.license_number.0', 'Ya hay un veterinario con esa matrícula.');

        $this->patchJson("/api/veterinarians/{$veterinarian->id}", ['license_number' => 'mv-3003'])
            ->assertOk()
            ->assertJsonPath('data.license_number', 'MV-3003');

        $this->patchJson("/api/veterinarians/{$veterinarian->id}", ['commission_percentage' => 101, 'status' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['commission_percentage', 'status']);
    }

    /**
     * Pregunta 30: quitarle el rol veterinario al usuario de una ficha no eliminada → 409.
     * Eliminada la ficha, sí.
     */
    public function test_the_veterinarian_role_is_not_removed_while_the_user_has_a_profile(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $veterinarian = $this->veterinarianOf($user);
        $this->actingIn($clinic, [...self::USER_PERMISSIONS, ...self::VETERINARIAN_PERMISSIONS]);

        $response = $this->patchJson("/api/users/{$user->id}", ['roles' => ['recepcionista']]);

        $this->assertErrorCode($response, 409, 'USER_IS_VETERINARIAN');
        $response->assertJsonPath('message', 'El usuario tiene una ficha de veterinario: eliminala antes de quitarle el rol.');
        $this->assertSame(['veterinario'], $user->fresh()->getRoleNames()->all());

        $this->deleteJson("/api/veterinarians/{$veterinarian->id}")->assertOk();
        $this->patchJson("/api/users/{$user->id}", ['roles' => ['recepcionista']])->assertOk();
    }

    /**
     * Un usuario inactivo no cambia la ficha; la respuesta lo muestra.
     */
    public function test_an_inactive_user_keeps_the_profile_and_the_response_shows_it(): void
    {
        $clinic = $this->createClinic();
        $user = $this->veterinarianUser($clinic);
        $veterinarian = $this->veterinarianOf($user);
        $user->is_active = false;
        $user->save();
        $this->actAsVeterinarianManager($clinic);

        $this->getJson("/api/veterinarians/{$veterinarian->id}")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'active')
            ->assertJsonPath('data.user.is_active', false);
    }
}
