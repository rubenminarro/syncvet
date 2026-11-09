<?php

namespace Tests\Feature\Services;

use App\Services\ServiceCatalogService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithServices;
use Tests\TestCase;

/**
 * Edición de un servicio (FASE 2, entrega 2.4): semántica parcial; el precio se edita
 * siempre (lo usado guarda el suyo); un servicio usado no cambia de nombre (422
 * SERVICE_NAME_NOT_EDITABLE) y se inactiva aunque esté en uso.
 */
class ServiceUpdateTest extends TestCase
{
    use InteractsWithServices;

    public function test_the_data_is_updated_partially(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['name' => 'Consulta general', 'description' => 'Control', 'duration_minutes' => 30]);
        $this->actAsServiceManager($clinic);

        $this->patchJson("/api/services/{$service->id}", ['standard_price' => '175000', 'tax_rate' => 0, 'category' => 'other'])
            ->assertOk()
            ->assertJsonPath('message', 'Servicio actualizado correctamente.')
            ->assertJsonPath('data.standard_price', '175000.00')
            ->assertJsonPath('data.tax_rate', 0)
            ->assertJsonPath('data.category', ['value' => 'other', 'label' => 'Otro'])
            // Semántica parcial: lo que no llega no cambia.
            ->assertJsonPath('data.name', 'Consulta general')
            ->assertJsonPath('data.description', 'Control')
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.code', 'SRV-000001');

        // PUT, con la misma semántica parcial (HttpVerbsTest), y un nombre nuevo sin uso.
        $this->putJson("/api/services/{$service->id}", ['name' => 'Consulta clínica', 'description' => null])
            ->assertOk()
            ->assertJsonPath('data.name', 'Consulta clínica')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.standard_price', '175000.00');

        $log = DB::table('audit_logs')->where('auditable_type', 'service')->where('auditable_id', $service->id)->where('event', 'updated')->first();
        $this->assertEquals(['standard_price' => '175000.00', 'tax_rate' => 0, 'category' => 'other'], json_decode($log->new_values, true));
    }

    public function test_the_code_does_not_change_and_the_rules_of_the_store_apply(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic);
        $this->actAsServiceManager($clinic);

        $this->patchJson("/api/services/{$service->id}", ['code' => 'SRV-000099', 'standard_price' => '1.5', 'duration_minutes' => 3, 'name' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'El código del servicio se asigna automáticamente.')
            ->assertJsonPath('errors.standard_price.0', 'El precio debe ser un número entero, sin decimales, de 0 a 999.999.999.999.')
            ->assertJsonPath('errors.duration_minutes.0', 'La duración debe estar entre 5 y 720 minutos.')
            ->assertJsonPath('errors.name.0', 'El nombre del servicio es obligatorio.');

        $this->assertSame('SRV-000001', DB::table('services')->where('id', $service->id)->value('code'));
    }

    /**
     * El nombre de otro (sin mayúsculas ni acentos) → 422; el propio con otras mayúsculas,
     * sin uso, sí cambia (no choca consigo mismo).
     */
    public function test_the_name_of_another_service_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['name' => 'Consulta general']);
        $this->serviceIn($clinic, ['name' => 'Ecografía']);
        $this->actAsServiceManager($clinic);

        $this->patchJson("/api/services/{$service->id}", ['name' => 'ECOGRAFIA'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ya hay un servicio con ese nombre.');

        $this->patchJson("/api/services/{$service->id}", ['name' => 'CONSULTA GENERAL'])
            ->assertOk()
            ->assertJsonPath('data.name', 'CONSULTA GENERAL');
    }

    public static function usages(): array
    {
        return array_combine(ServiceCatalogService::USAGE, array_map(fn (string $table) => [$table], ServiceCatalogService::USAGE));
    }

    /**
     * Un servicio usado no cambia de nombre (422, sin cambios a medias), aunque solo
     * cambien las mayúsculas; reenviar el mismo nombre (el formulario completo) sí pasa, y
     * el precio, la categoría y el estado se editan.
     */
    #[DataProvider('usages')]
    public function test_a_used_service_keeps_its_name_and_changes_its_price(string $table): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['name' => 'Consulta general']);
        $this->serviceUsageRowFor($service, $table);
        $this->actAsServiceManager($clinic);

        foreach (['Consulta clínica', 'CONSULTA GENERAL'] as $name) {
            $response = $this->patchJson("/api/services/{$service->id}", ['name' => $name, 'standard_price' => 1]);

            $this->assertErrorCode($response, 422, 'SERVICE_NAME_NOT_EDITABLE');
            $response->assertJsonPath('message', 'El servicio ya se usó y no puede cambiar de nombre: creá otro e inactivá este.');
        }

        $this->assertSame('Consulta general', DB::table('services')->where('id', $service->id)->value('name'));
        $this->assertEquals(150000, DB::table('services')->where('id', $service->id)->value('standard_price'), 'El 422 dejó cambios a medias.');

        $this->patchJson("/api/services/{$service->id}", ['name' => ' Consulta  general ', 'standard_price' => 200000, 'status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Consulta general')
            ->assertJsonPath('data.standard_price', '200000.00')
            ->assertJsonPath('data.status.value', 'inactive');
    }
}
