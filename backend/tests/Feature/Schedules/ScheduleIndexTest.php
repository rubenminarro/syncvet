<?php

namespace Tests\Feature\Schedules;

use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Listado de agendas (FASE 3, entrega 3.1): las activas de la clínica salvo
 * include_inactive=1; filtros branch_id, veterinarian_id y service_id; search literal en el
 * nombre; orden por nombre e id; paginado con ListFilters.
 */
class ScheduleIndexTest extends TestCase
{
    use InteractsWithSchedules;

    public function test_the_reception_lists_the_active_schedules_of_its_clinic(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $service = $this->serviceIn($clinic);
        $b = $this->scheduleIn($branch, ['name' => 'Peluquería', 'service_id' => $service->id]);
        $a = $this->scheduleIn($branch, ['name' => 'Consultorio', 'veterinarian_id' => $this->veterinarianIn($branch)->id]);
        $this->scheduleIn($branch, ['name' => 'Cirugía', 'service_id' => $service->id, 'is_active' => false]);
        $foreignClinic = $this->createClinic();
        $this->scheduleIn($this->defaultBranchIn($foreignClinic), ['name' => 'Ajena', 'service_id' => $this->serviceIn($foreignClinic)->id]);
        Sanctum::actingAs($this->userWithRoles($clinic, ['recepcionista']));

        $this->getJson('/api/schedules')
            ->assertOk()
            ->assertJsonPath('message', 'Agendas obtenidas correctamente.')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.0.veterinarian.code', 'VET-000001')
            ->assertJsonPath('data.1.id', $b->id)
            ->assertJsonPath('data.1.service.id', $service->id)
            ->assertJsonMissingPath('data.0.hours');

        $this->getJson('/api/schedules?include_inactive=1')->assertOk()->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_the_filters_and_the_literal_search(): void
    {
        $clinic = $this->createClinic();
        $branch = $this->defaultBranchIn($clinic);
        $other = $this->branchIn($clinic);
        $service = $this->serviceIn($clinic);
        $veterinarian = $this->veterinarianIn($branch, $other);
        $byVeterinarian = $this->scheduleIn($branch, ['name' => 'Dr. 100% guardia', 'veterinarian_id' => $veterinarian->id]);
        $byService = $this->scheduleIn($other, ['name' => 'Peluquería_1', 'service_id' => $service->id]);
        $this->scheduleIn($branch, ['name' => 'Peluquería 2', 'service_id' => $this->serviceIn($clinic, ['name' => 'Baño'])->id]);
        $this->actAsScheduleManager($clinic);

        $this->getJson("/api/schedules?branch_id={$other->id}")->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $byService->id);
        $this->getJson("/api/schedules?veterinarian_id={$veterinarian->id}")->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $byVeterinarian->id);
        $this->getJson("/api/schedules?service_id={$service->id}")->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $byService->id);
        $this->getJson('/api/schedules?search=' . urlencode('100%'))->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $byVeterinarian->id);
        $this->getJson('/api/schedules?search=' . urlencode('a_'))->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $byService->id);
        $this->getJson('/api/schedules?per_page=1&page=2')->assertJsonPath('meta.pagination.last_page', 3)->assertJsonCount(1, 'data');
    }

    public function test_malformed_or_foreign_filters_answer_422(): void
    {
        $clinic = $this->createClinic();
        $foreign = $this->createClinic();
        $foreignBranch = $this->defaultBranchIn($foreign);
        $this->actAsScheduleManager($clinic);

        $this->getJson("/api/schedules?branch_id={$foreignBranch->id}")->assertUnprocessable()->assertJsonValidationErrors(['branch_id']);
        $this->getJson('/api/schedules?veterinarian_id=abc')->assertUnprocessable()->assertJsonValidationErrors(['veterinarian_id']);
        $this->getJson("/api/schedules?service_id={$this->serviceIn($foreign)->id}")->assertUnprocessable()->assertJsonValidationErrors(['service_id']);
        $this->getJson('/api/schedules?per_page=101')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
    }
}
