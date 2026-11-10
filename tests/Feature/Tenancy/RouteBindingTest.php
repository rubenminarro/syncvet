<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use App\Models\Veterinarian;
use App\Models\Warehouse;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithBranches;
use Tests\TestCase;

/**
 * El route model binding corre con la clínica ya fijada (SetCurrentClinic antes
 * de SubstituteBindings): un id de otra clínica da 404, como si no existiera.
 */
class RouteBindingTest extends TestCase
{
    use InteractsWithBranches;

    private const PERMISSIONS = [
        'clients.show', 'clients.update', 'clients.destroy',
        'patients.index', 'patients.store', 'patients.show', 'patients.update', 'patients.destroy', 'patients.change-owner',
    ];

    public static function clientRoutes(): array
    {
        return [
            'GET clients/{client}' => ['get', '/api/clients/%d'],
            'PATCH clients/{client}' => ['patch', '/api/clients/%d'],
            'DELETE clients/{client}' => ['delete', '/api/clients/%d'],
            'GET clients/{client}/patients' => ['get', '/api/clients/%d/patients'],
            'POST clients/{client}/patients' => ['post', '/api/clients/%d/patients'],
        ];
    }

    public static function patientRoutes(): array
    {
        return [
            'GET patients/{patient}' => ['get', '/api/patients/%d'],
            'PATCH patients/{patient}' => ['patch', '/api/patients/%d'],
            'DELETE patients/{patient}' => ['delete', '/api/patients/%d'],
            'POST patients/{patient}/change-owner' => ['post', '/api/patients/%d/change-owner'],
        ];
    }

    #[DataProvider('clientRoutes')]
    public function test_client_of_another_clinic_is_not_found(string $method, string $uri): void
    {
        $own = $this->createClinic();
        $foreign = $this->clientIn($this->createClinic(), ['first_name' => 'Ajeno']);

        $this->actingIn($own, self::PERMISSIONS);

        $response = $this->json($method, sprintf($uri, $foreign->id), ['first_name' => 'Cambiado', 'name' => 'Nuevo']);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreign->clinic, function () use ($foreign) {
            $this->assertSame('Ajeno', Client::find($foreign->id)?->first_name, 'El cliente ajeno cambió o se eliminó.');
            $this->assertSame(0, Patient::count());
        });
    }

    #[DataProvider('patientRoutes')]
    public function test_patient_of_another_clinic_is_not_found(string $method, string $uri): void
    {
        $own = $this->createClinic();
        $foreign = $this->patientOf($this->clientIn($this->createClinic()), ['name' => 'Ajeno']);
        $newOwner = $this->clientIn($own);

        $this->actingIn($own, self::PERMISSIONS);

        $response = $this->json($method, sprintf($uri, $foreign->id), ['name' => 'Cambiado', 'client_id' => $newOwner->id]);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreign->clinic, function () use ($foreign) {
            $patient = Patient::find($foreign->id);

            $this->assertSame('Ajeno', $patient?->name, 'El paciente ajeno cambió o se eliminó.');
            $this->assertSame($foreign->client_id, $patient->client_id);
        });
    }

    /**
     * Sucursales y depósitos (FASE 2, entrega 2.1): una sucursal o un depósito de otra
     * clínica responde 404 en cada ruta y no cambia.
     */
    public static function branchRoutes(): array
    {
        return [
            'GET branches/{branch}' => ['get', '/api/branches/%d'],
            'PATCH branches/{branch}' => ['patch', '/api/branches/%d'],
            'PUT branches/{branch}' => ['put', '/api/branches/%d'],
            'GET branches/{branch}/warehouses' => ['get', '/api/branches/%d/warehouses'],
            'POST branches/{branch}/warehouses' => ['post', '/api/branches/%d/warehouses'],
        ];
    }

    #[DataProvider('branchRoutes')]
    public function test_branch_of_another_clinic_is_not_found(string $method, string $uri): void
    {
        $own = $this->createClinic();
        $this->defaultBranchIn($own);
        $foreign = $this->branchIn($this->createClinic(), ['name' => 'Ajena']);

        $this->actAsBranchManager($own);

        $response = $this->json($method, sprintf($uri, $foreign->id), ['name' => 'Cambiada', 'code' => 'DEP-99', 'is_default' => true]);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreign->clinic, function () use ($foreign) {
            $branch = Branch::find($foreign->id);

            $this->assertSame('Ajena', $branch?->name, 'La sucursal ajena cambió.');
            $this->assertSame(1, Warehouse::count());
        });
    }

    public function test_warehouse_of_another_clinic_is_not_found(): void
    {
        $own = $this->createClinic();
        $this->defaultBranchIn($own);
        $foreign = $this->defaultBranchIn($this->createClinic());
        $warehouse = CurrentClinic::run($foreign->clinic, fn () => Warehouse::firstOrFail());

        $this->actAsBranchManager($own);

        foreach (['patch', 'put'] as $method) {
            $this->assertErrorCode($this->json($method, "/api/warehouses/{$warehouse->id}", ['name' => 'Cambiado']), 404, 'RESOURCE_NOT_FOUND');
        }

        $this->assertSame('Principal', CurrentClinic::run($foreign->clinic, fn () => Warehouse::find($warehouse->id)?->name));
    }

    /**
     * Usuarios (FASE 2, entrega 2.2a): User no tiene el scope de la clínica; su route
     * binding (inClinic()) da 404 a un usuario de otra clínica en cada ruta, y no lo
     * cambia.
     */
    public static function userRoutes(): array
    {
        return [
            'GET users/{user}' => ['get'],
            'PATCH users/{user}' => ['patch'],
            'PUT users/{user}' => ['put'],
            'DELETE users/{user}' => ['delete'],
        ];
    }

    #[DataProvider('userRoutes')]
    public function test_user_of_another_clinic_is_not_found(string $method): void
    {
        $own = $this->createClinic();
        $foreign = User::factory()->for($this->createClinic())->create(['first_name' => 'Ajena']);
        $foreign->syncRoles(['recepcionista']);

        $this->actingIn($own, ['users.show', 'users.update', 'users.destroy']);

        $response = $this->json($method, "/api/users/{$foreign->id}", ['first_name' => 'Cambiada', 'roles' => ['veterinario'], 'password' => 'Otra-Clave-2026!']);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');
        $this->assertSame('Ajena', $foreign->fresh()->first_name);
        $this->assertSame(['recepcionista'], $foreign->fresh()->getRoleNames()->all());
        $this->assertFalse($foreign->fresh()->trashed());
    }

    /**
     * Veterinarios (FASE 2, entrega 2.3): una ficha de otra clínica responde 404 en cada
     * ruta y no cambia. Un usuario de otra clínica en el alta responde 422 (VeterinarianStoreTest).
     */
    public static function veterinarianRoutes(): array
    {
        return [
            'GET veterinarians/{veterinarian}' => ['get'],
            'PATCH veterinarians/{veterinarian}' => ['patch'],
            'PUT veterinarians/{veterinarian}' => ['put'],
            'DELETE veterinarians/{veterinarian}' => ['delete'],
        ];
    }

    #[DataProvider('veterinarianRoutes')]
    public function test_veterinarian_of_another_clinic_is_not_found(string $method): void
    {
        $foreignClinic = $this->createClinic();
        $foreign = CurrentClinic::run($foreignClinic, fn () => Veterinarian::create([
            'user_id' => User::factory()->for($foreignClinic)->create()->id,
            'code' => 'VET-000001',
            'license_number' => 'MV-1001',
        ]));

        $this->actingIn($this->createClinic(), ['veterinarians.show', 'veterinarians.update', 'veterinarians.destroy']);

        $response = $this->json($method, "/api/veterinarians/{$foreign->id}", ['license_number' => 'MV-9999', 'status' => 'inactive']);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreignClinic, function () use ($foreign) {
            $veterinarian = Veterinarian::find($foreign->id);

            $this->assertSame('MV-1001', $veterinarian?->license_number, 'La ficha ajena cambió o se eliminó.');
            $this->assertSame('active', $veterinarian->status->value);
        });
    }

    /**
     * Servicios (FASE 2, entrega 2.4): un servicio de otra clínica responde 404 en cada
     * ruta y no cambia.
     */
    public static function serviceRoutes(): array
    {
        return [
            'GET services/{service}' => ['get'],
            'PATCH services/{service}' => ['patch'],
            'PUT services/{service}' => ['put'],
            'DELETE services/{service}' => ['delete'],
        ];
    }

    #[DataProvider('serviceRoutes')]
    public function test_service_of_another_clinic_is_not_found(string $method): void
    {
        $foreignClinic = $this->createClinic();
        $foreign = CurrentClinic::run($foreignClinic, fn () => Service::create([
            'code' => 'SRV-000001',
            'name' => 'Consulta general',
            'standard_price' => '150000.00',
        ]));

        $this->actingIn($this->createClinic(), ['services.show', 'services.update', 'services.destroy']);

        $response = $this->json($method, "/api/services/{$foreign->id}", ['standard_price' => 1, 'status' => 'inactive']);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreignClinic, function () use ($foreign) {
            $service = Service::find($foreign->id);

            $this->assertSame('150000.00', $service?->standard_price, 'El servicio ajeno cambió o se eliminó.');
            $this->assertSame('active', $service->status->value);
        });
    }

    /**
     * Agendas y bloqueos (FASE 3, entrega 3.1): una agenda o un bloqueo de otra clínica
     * responde 404 en cada ruta y no cambia.
     */
    public static function scheduleRoutes(): array
    {
        return [
            'GET schedules/{schedule}' => ['get', '/api/schedules/%d'],
            'PATCH schedules/{schedule}' => ['patch', '/api/schedules/%d'],
            'PUT schedules/{schedule}' => ['put', '/api/schedules/%d'],
            'DELETE schedules/{schedule}' => ['delete', '/api/schedules/%d'],
            'GET schedules/{schedule}/blocks' => ['get', '/api/schedules/%d/blocks'],
            'POST schedules/{schedule}/blocks' => ['post', '/api/schedules/%d/blocks'],
            'DELETE schedule-blocks/{scheduleBlock}' => ['delete', '/api/schedule-blocks/%d'],
        ];
    }

    #[DataProvider('scheduleRoutes')]
    public function test_schedule_or_block_of_another_clinic_is_not_found(string $method, string $uri): void
    {
        $foreignClinic = $this->createClinic();
        $foreignBranch = $this->defaultBranchIn($foreignClinic);
        $foreignCreator = User::factory()->for($foreignClinic)->create();

        [$schedule, $block] = CurrentClinic::run($foreignClinic, function () use ($foreignBranch, $foreignCreator) {
            $service = Service::create(['code' => 'SRV-000001', 'name' => 'Peluquería']);
            $schedule = Schedule::factory()->create(['branch_id' => $foreignBranch->id, 'name' => 'Ajena', 'service_id' => $service->id]);

            return [$schedule, ScheduleBlock::factory()->for($schedule)->create(['created_by' => $foreignCreator->id])];
        });

        $this->actingIn($this->createClinic(), [
            'schedules.show', 'schedules.update', 'schedules.destroy',
            'schedule-blocks.index', 'schedule-blocks.store', 'schedule-blocks.destroy',
        ]);

        $id = str_starts_with($uri, '/api/schedule-blocks') ? $block->id : $schedule->id;
        $response = $this->json($method, sprintf($uri, $id), [
            'name' => 'Cambiada', 'is_active' => false,
            'starts_at' => '2030-01-01T10:00:00Z', 'ends_at' => '2030-01-01T11:00:00Z',
        ]);

        $this->assertErrorCode($response, 404, 'RESOURCE_NOT_FOUND');

        CurrentClinic::run($foreignClinic, function () use ($schedule) {
            $found = Schedule::find($schedule->id);

            $this->assertSame('Ajena', $found?->name, 'La agenda ajena cambió o se eliminó.');
            $this->assertTrue($found->is_active);
            $this->assertSame(1, ScheduleBlock::query()->count(), 'Los bloqueos de la agenda ajena cambiaron.');
        });
    }

    /**
     * Turnos (FASE 3, entrega 3.2a): uno de otra clínica responde 404 en cada ruta y no cambia.
     */
    public static function appointmentRoutes(): array
    {
        return [
            'GET appointments/{appointment}' => ['get'],
            'PATCH appointments/{appointment}' => ['patch'],
            'PUT appointments/{appointment}' => ['put'],
        ];
    }

    #[DataProvider('appointmentRoutes')]
    public function test_appointment_of_another_clinic_is_not_found(string $method): void
    {
        $foreignClinic = $this->createClinic();
        $foreignBranch = $this->defaultBranchIn($foreignClinic);
        $patient = $this->patientOf($this->clientIn($foreignClinic));
        $schedule = CurrentClinic::run($foreignClinic, fn () => Schedule::factory()->create([
            'branch_id' => $foreignBranch->id,
            'service_id' => Service::create(['code' => 'SRV-000001', 'name' => 'Peluquería'])->id,
        ]));
        $id = DB::table('appointments')->insertGetId([
            'clinic_id' => $foreignClinic->id,
            'branch_id' => $foreignBranch->id,
            'schedule_id' => $schedule->id,
            'client_id' => $patient->client_id,
            'patient_id' => $patient->id,
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-01 10:30:00',
            'notes' => 'Ajeno',
            'created_by' => User::factory()->for($foreignClinic)->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingIn($this->createClinic(), ['appointments.show', 'appointments.update']);

        $this->assertErrorCode($this->json($method, "/api/appointments/{$id}", ['notes' => 'Cambiado']), 404, 'RESOURCE_NOT_FOUND');
        $this->assertSame('Ajeno', DB::table('appointments')->where('id', $id)->value('notes'));
    }

    public function test_own_records_are_found(): void
    {
        $own = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($own));

        $this->actingIn($own, self::PERMISSIONS);

        $this->getJson("/api/clients/{$patient->client_id}")->assertOk();
        $this->getJson("/api/clients/{$patient->client_id}/patients")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/patients/{$patient->id}")->assertOk();

        $branch = $this->defaultBranchIn($own);
        $this->actAsBranchManager($own);

        $this->getJson("/api/branches/{$branch->id}")->assertOk();
        $this->getJson("/api/branches/{$branch->id}/warehouses")->assertOk()->assertJsonCount(1, 'data');
    }
}
