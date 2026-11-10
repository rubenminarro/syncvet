<?php

namespace Tests\Feature\Schedules;

use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Alta de una agenda (FASE 3, entrega 3.1; P9 y P10): de una sucursal activa, con un
 * veterinario, un servicio o los dos; el veterinario activo, con su usuario activo y en la
 * sucursal; el servicio activo; nombre único en la sucursal.
 */
class ScheduleStoreTest extends TestCase
{
    use InteractsWithSchedules;

    public function test_a_schedule_with_a_veterinarian_and_hours_is_created(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $veterinarian = $this->veterinarianIn($branch);
        $this->actAsScheduleManager($clinic);

        $response = $this->postJson('/api/schedules', $this->schedulePayload($branch, [
            'veterinarian_id' => $veterinarian->id,
            'slot_minutes' => 20,
            'color' => '#1a2b3c',
            'hours' => [
                ['weekday' => 2, 'starts_at' => '14:00', 'ends_at' => '18:00'],
                ['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('message', 'Agenda creada correctamente.')
            ->assertJsonPath('data.name', 'Consultorio 1')
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.veterinarian.id', $veterinarian->id)
            ->assertJsonPath('data.veterinarian.code', $veterinarian->code)
            ->assertJsonPath('data.veterinarian.name', $veterinarian->user->name)
            ->assertJsonPath('data.service', null)
            ->assertJsonPath('data.slot_minutes', 20)
            ->assertJsonPath('data.color', '#1A2B3C')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.hours.0.weekday', 1)
            ->assertJsonPath('data.hours.0.starts_at', '08:00')
            ->assertJsonPath('data.hours.0.ends_at', '12:00')
            ->assertJsonPath('data.hours.1.weekday', 2)
            ->assertJsonPath('data.upcoming_blocks', [])
            ->assertJsonMissingPath('data.online_booking');

        $id = $response->json('data.id');
        $this->assertSame(2, DB::table('schedule_hours')->where('schedule_id', $id)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'schedule')->where('auditable_id', $id)->where('event', 'created')->count());
        $this->assertSame(2, DB::table('audit_logs')->where('auditable_type', 'schedule_hour')->where('event', 'created')->count());
    }

    public function test_a_schedule_with_only_a_service_takes_the_default_values(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic, ['name' => 'Peluquería']);
        $this->actAsScheduleManager($clinic);

        $this->postJson('/api/schedules', $this->schedulePayload($branch, ['service_id' => $service->id, 'online_booking' => true]))
            ->assertCreated()
            ->assertJsonPath('data.veterinarian', null)
            ->assertJsonPath('data.service.id', $service->id)
            ->assertJsonPath('data.service.name', 'Peluquería')
            ->assertJsonPath('data.slot_minutes', 30)
            ->assertJsonPath('data.color', null)
            ->assertJsonPath('data.hours', []);

        // online_booking no forma parte de la API: se ignora.
        $this->assertSame(0, (int) DB::table('schedules')->value('online_booking'));
    }

    public function test_a_schedule_without_veterinarian_nor_service_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $this->actAsScheduleManager($clinic);

        $this->postJson('/api/schedules', $this->schedulePayload($branch, ['veterinarian_id' => null, 'service_id' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.veterinarian_id.0', 'Elegí un veterinario, un servicio o los dos.');

        $this->assertSame(0, DB::table('schedules')->count());
    }

    public static function unavailableVeterinarians(): array
    {
        return [
            'ficha inactiva' => ['inactive', 'El veterinario no existe o está inactivo.'],
            'usuario inactivo' => ['user_inactive', 'El veterinario no existe o está inactivo.'],
            'eliminado' => ['deleted', 'El veterinario no existe o está inactivo.'],
            'de otra clínica' => ['foreign', 'El veterinario no existe o está inactivo.'],
            'sin la sucursal' => ['other_branch', 'El veterinario no trabaja en esa sucursal.'],
        ];
    }

    #[DataProvider('unavailableVeterinarians')]
    public function test_an_unavailable_veterinarian_is_rejected(string $case, string $message): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);

        $veterinarian = match ($case) {
            'inactive', 'user_inactive', 'deleted' => $this->veterinarianIn($branch),
            'foreign' => $this->veterinarianIn($this->defaultBranchIn($this->createClinic())),
            'other_branch' => $this->veterinarianIn($this->branchIn($clinic)),
        };

        match ($case) {
            'inactive' => DB::table('veterinarians')->where('id', $veterinarian->id)->update(['status' => VeterinarianStatus::INACTIVE->value]),
            'user_inactive' => DB::table('users')->where('id', $veterinarian->user_id)->update(['is_active' => false]),
            'deleted' => DB::table('veterinarians')->where('id', $veterinarian->id)->update(['deleted_at' => now()]),
            default => null,
        };

        $this->actAsScheduleManager($clinic);

        $this->postJson('/api/schedules', $this->schedulePayload($branch, ['veterinarian_id' => $veterinarian->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.veterinarian_id.0', $message);

        $this->assertSame(0, DB::table('schedules')->count());
    }

    public function test_an_inactive_or_foreign_service_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $inactive = $this->serviceIn($clinic, ['name' => 'Baño', 'status' => ServiceStatus::INACTIVE]);
        $foreign = $this->serviceIn($this->createClinic());
        $this->actAsScheduleManager($clinic);

        foreach ([$inactive, $foreign] as $service) {
            $this->postJson('/api/schedules', $this->schedulePayload($branch, ['service_id' => $service->id]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.service_id.0', 'El servicio no existe o está inactivo.');
        }

        $this->assertSame(0, DB::table('schedules')->count());
    }

    public function test_an_inactive_or_foreign_branch_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $inactive = $this->branchIn($clinic, ['is_active' => false]);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $service = $this->serviceIn($clinic);
        $this->actAsScheduleManager($clinic);

        foreach ([$inactive, $foreign] as $branch) {
            $this->postJson('/api/schedules', $this->schedulePayload($branch, ['service_id' => $service->id]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.branch_id.0', 'La sucursal no existe o está inactiva.');
        }
    }

    public function test_the_name_is_unique_within_the_branch(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $service = $this->serviceIn($clinic);
        $this->scheduleIn($branch, ['name' => 'Consultorio Ángel', 'service_id' => $service->id]);
        $this->actAsScheduleManager($clinic);

        $this->postJson('/api/schedules', $this->schedulePayload($branch, ['name' => '  consultorio   ANGEL ', 'service_id' => $service->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ya hay una agenda con ese nombre en la sucursal.');

        $this->postJson('/api/schedules', $this->schedulePayload($other, ['name' => 'Consultorio Ángel', 'service_id' => $service->id]))
            ->assertCreated();
    }

    public static function invalidFields(): array
    {
        return [
            'slot_minutes menor a 5' => [['slot_minutes' => 0], 'slot_minutes'],
            'slot_minutes mayor a 240' => [['slot_minutes' => 245], 'slot_minutes'],
            'slot_minutes fuera de la grilla' => [['slot_minutes' => 22], 'slot_minutes'],
            'color sin numeral' => [['color' => '1A2B3C'], 'color'],
            'color corto' => [['color' => '#FFF'], 'color'],
            'nombre sin letras' => [['name' => '123'], 'name'],
            'nombre corto' => [['name' => 'A'], 'name'],
            'nombre largo' => [['name' => str_repeat('a', 101)], 'name'],
            'sin sucursal' => [['branch_id' => null], 'branch_id'],
            'horario no es una lista' => [['hours' => 'lunes'], 'hours'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_rejected(array $overrides, string $field): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic);
        $this->actAsScheduleManager($clinic);

        $this->postJson('/api/schedules', $this->schedulePayload($branch, ['service_id' => $service->id, ...$overrides]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, DB::table('schedules')->count());
    }

    public function test_the_reception_cannot_create_schedules(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic);
        Sanctum::actingAs($this->userWithRoles($clinic, ['recepcionista']));

        $this->postJson('/api/schedules', $this->schedulePayload($branch, ['service_id' => $service->id]))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FORBIDDEN');
    }
}
