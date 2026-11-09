<?php

namespace Tests\Feature\Services;

use Tests\Concerns\InteractsWithServices;
use Tests\TestCase;

/**
 * Listado de servicios (FASE 2, entrega 2.4): los activos de la clínica por defecto,
 * ordenados por nombre; status manda; include_inactive=1 sin status trae activos e
 * inactivos (precisión 1 del usuario, como veterinarios); category; search literal en
 * código y nombre.
 */
class ServiceIndexTest extends TestCase
{
    use InteractsWithServices;

    public function test_lists_the_active_services_of_the_clinic_by_name_with_pagination(): void
    {
        $clinic = $this->createClinic();
        $this->serviceIn($clinic, ['name' => 'Vacuna séxtuple', 'category' => 'vaccination']);
        $this->serviceIn($clinic, ['name' => 'Consulta general']);
        $this->serviceIn($clinic, ['name' => 'Baño', 'category' => 'grooming', 'status' => 'inactive']);
        $this->serviceIn($this->createClinic(), ['name' => 'Ajeno']);
        $deleted = $this->serviceIn($clinic, ['name' => 'Eliminado']);
        $this->actAsServiceManager($clinic);
        $this->deleteJson("/api/services/{$deleted->id}")->assertOk();

        $this->getJson('/api/services?per_page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Servicios obtenidos correctamente.')
            ->assertJsonPath('data.0.name', 'Consulta general')
            ->assertJsonPath('data.0.code', 'SRV-000002')
            ->assertJsonPath('data.0.category', ['value' => 'consultation', 'label' => 'Consulta'])
            ->assertJsonPath('data.0.tax_rate', 10)
            ->assertJsonPath('data.0.standard_price', '150000.00')
            // La descripción, solo en el detalle.
            ->assertJsonMissingPath('data.0.description')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.perPage', 1)
            ->assertJsonPath('meta.pagination.lastPage', 2);

        $this->getJson('/api/services')->assertOk()->assertJsonPath('data.*.name', ['Consulta general', 'Vacuna séxtuple']);
    }

    public function test_status_include_inactive_and_category(): void
    {
        $clinic = $this->createClinic();
        $this->serviceIn($clinic, ['name' => 'Consulta general']);
        $this->serviceIn($clinic, ['name' => 'Baño', 'category' => 'grooming', 'status' => 'inactive']);
        $this->serviceIn($clinic, ['name' => 'Corte de pelo', 'category' => 'grooming']);
        $this->actAsServiceManager($clinic);

        $this->getJson('/api/services?status=inactive')->assertOk()->assertJsonPath('data.*.name', ['Baño']);
        $this->getJson('/api/services?include_inactive=1')->assertOk()->assertJsonPath('data.*.name', ['Baño', 'Consulta general', 'Corte de pelo']);
        $this->getJson('/api/services?include_inactive=0')->assertOk()->assertJsonPath('data.*.name', ['Consulta general', 'Corte de pelo']);
        // status manda sobre include_inactive.
        $this->getJson('/api/services?include_inactive=1&status=active')->assertOk()->assertJsonPath('data.*.name', ['Consulta general', 'Corte de pelo']);
        $this->getJson('/api/services?category=grooming')->assertOk()->assertJsonPath('data.*.name', ['Corte de pelo']);
        $this->getJson('/api/services?category=grooming&include_inactive=1')->assertOk()->assertJsonPath('data.*.name', ['Baño', 'Corte de pelo']);

        $this->getJson('/api/services?include_inactive=x')
            ->assertUnprocessable()
            ->assertJsonPath('errors.include_inactive.0', 'El campo incluir los inactivos debe ser verdadero o falso.');
    }

    public function test_search_finds_by_code_and_name_literally(): void
    {
        $clinic = $this->createClinic();
        $this->serviceIn($clinic, ['name' => 'Ecografía abdominal']);
        $this->serviceIn($clinic, ['name' => 'Análisis 100% completo']);
        $this->serviceIn($clinic, ['name' => 'Rayos X_tórax']);
        $this->actAsServiceManager($clinic);

        $this->getJson('/api/services?search=abdominal')->assertOk()->assertJsonPath('data.*.code', ['SRV-000001']);
        $this->getJson('/api/services?search=SRV-000002')->assertOk()->assertJsonPath('data.*.code', ['SRV-000002']);
        // "%" y "_" son literales (R-021).
        $this->getJson('/api/services?search=%25')->assertOk()->assertJsonPath('data.*.code', ['SRV-000002']);
        $this->getJson('/api/services?search=X_t')->assertOk()->assertJsonPath('data.*.code', ['SRV-000003']);
    }

    public function test_malformed_filters_answer_422(): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $this->getJson('/api/services?status=retired&category=spa&per_page=0&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'category', 'per_page', 'page']);
    }
}
