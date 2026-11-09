<?php

namespace Tests\Feature\Services;

use App\Models\Service;
use App\Services\ServiceCatalogService;
use App\Support\CurrentClinic;
use App\Support\DeletedSuffix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithServices;
use Tests\TestCase;

/**
 * Baja de un servicio (FASE 2, entrega 2.4): solo sin uso. El nombre lleva el sufijo de
 * baja y queda libre; con uso, 409 SERVICE_IN_USE (se inactiva).
 */
class ServiceDeleteTest extends TestCase
{
    use InteractsWithServices;

    public function test_a_service_without_usage_is_deleted_and_the_name_is_free_again(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['name' => 'Baño medicado']);
        $this->actAsServiceManager($clinic);

        $this->deleteJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Servicio eliminado correctamente.')
            ->assertJsonPath('data', null);

        $deleted = CurrentClinic::run($clinic, fn () => Service::withTrashed()->findOrFail($service->id));
        $this->assertTrue($deleted->trashed());
        $this->assertStringStartsWith('Baño medicado' . DeletedSuffix::PREFIX . $service->id . '_', $deleted->name);
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'service')->where('auditable_id', $service->id)->where('event', 'deleted')->count());

        $this->getJson("/api/services/{$service->id}")->assertNotFound();
        // El mismo nombre, también con otras mayúsculas, queda libre para un alta nueva.
        $this->postJson('/api/services', ['name' => 'BAÑO MEDICADO', 'category' => 'grooming', 'standard_price' => 1])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SRV-000002');
    }

    /**
     * El nombre más largo (120) con el sufijo entero entra en la columna de 150.
     */
    public function test_the_longest_name_keeps_the_whole_suffix(): void
    {
        $clinic = $this->createClinic();
        $name = str_repeat('a', 120);
        $service = $this->serviceIn($clinic, ['name' => $name]);
        $this->actAsServiceManager($clinic);

        $this->deleteJson("/api/services/{$service->id}")->assertOk();

        $stored = DB::table('services')->where('id', $service->id)->value('name');
        $this->assertStringStartsWith($name . DeletedSuffix::PREFIX . $service->id . '_', $stored);
        $this->assertLessThanOrEqual(150, mb_strlen($stored));
    }

    public static function usages(): array
    {
        return array_combine(ServiceCatalogService::USAGE, array_map(fn (string $table) => [$table], ServiceCatalogService::USAGE));
    }

    #[DataProvider('usages')]
    public function test_a_service_in_use_is_not_deleted(string $table): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic, ['name' => 'Consulta general']);
        $this->serviceUsageRowFor($service, $table);
        $this->actAsServiceManager($clinic);

        $response = $this->deleteJson("/api/services/{$service->id}");

        $this->assertErrorCode($response, 409, 'SERVICE_IN_USE');
        $response->assertJsonPath('message', 'El servicio ya se usó y no se puede eliminar: inactivalo.');
        $row = DB::table('services')->where('id', $service->id)->first();
        $this->assertNull($row->deleted_at);
        $this->assertSame('Consulta general', $row->name);
    }

    /**
     * Precisión 2 del usuario: la lista de uso (para la baja y el nombre fijo) cubre toda
     * clave foránea a services.
     */
    public function test_usage_covers_every_foreign_key_to_services(): void
    {
        $references = [];

        foreach (Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if ($foreignKey['foreign_table'] === 'services') {
                    $this->assertSame(['service_id'], $foreignKey['columns'], "{$table} referencia a services con otra columna.");
                    $references[] = $table;
                }
            }
        }

        sort($references);
        $usage = ServiceCatalogService::USAGE;
        sort($usage);

        $this->assertSame($references, $usage);
    }
}
