<?php

namespace Tests\Feature\Veterinarians;

use App\Models\Veterinarian;
use App\Services\VeterinarianService;
use App\Support\CurrentClinic;
use App\Support\DeletedSuffix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithVeterinarians;
use Tests\TestCase;

/**
 * Baja de un veterinario (FASE 2, entrega 2.3; P6): solo sin uso. La matrícula lleva el
 * sufijo de baja y queda libre; con uso, 409 VETERINARIAN_IN_USE (se inactiva).
 */
class VeterinarianDeleteTest extends TestCase
{
    use InteractsWithVeterinarians;

    public function test_a_veterinarian_without_usage_is_deleted_and_the_license_number_is_free_again(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => 'MV-1001']);
        $this->actAsVeterinarianManager($clinic);

        $this->deleteJson("/api/veterinarians/{$veterinarian->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Veterinario eliminado correctamente.')
            ->assertJsonPath('data', null);

        $deleted = CurrentClinic::run($clinic, fn () => Veterinarian::withTrashed()->findOrFail($veterinarian->id));
        $this->assertTrue($deleted->trashed());
        $this->assertStringStartsWith('MV-1001' . DeletedSuffix::PREFIX . $veterinarian->id . '_', $deleted->license_number);
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'veterinarian')->where('auditable_id', $veterinarian->id)->where('event', 'deleted')->count());

        $this->getJson("/api/veterinarians/{$veterinarian->id}")->assertNotFound();
        $this->postJson('/api/veterinarians', ['user_id' => $this->veterinarianUser($clinic)->id, 'license_number' => 'MV-1001'])->assertCreated();
    }

    /**
     * La matrícula más larga (30) con el sufijo entero entra en la columna de 60.
     */
    public function test_the_longest_license_number_keeps_the_whole_suffix(): void
    {
        $clinic = $this->createClinic();
        $license = str_repeat('7', 30);
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => $license]);
        $this->actAsVeterinarianManager($clinic);

        $this->deleteJson("/api/veterinarians/{$veterinarian->id}")->assertOk();

        $stored = DB::table('veterinarians')->where('id', $veterinarian->id)->value('license_number');
        $this->assertSame($license . DeletedSuffix::PREFIX . $veterinarian->id . '_', substr($stored, 0, 30 + strlen(DeletedSuffix::PREFIX . $veterinarian->id . '_')));
        $this->assertLessThanOrEqual(60, mb_strlen($stored));
    }

    public function test_a_veterinarian_without_license_number_is_deleted(): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic));
        $this->actAsVeterinarianManager($clinic);

        $this->deleteJson("/api/veterinarians/{$veterinarian->id}")->assertOk();

        $this->assertNull(DB::table('veterinarians')->where('id', $veterinarian->id)->value('license_number'));
        $this->assertNotNull(DB::table('veterinarians')->where('id', $veterinarian->id)->value('deleted_at'));
    }

    public static function usages(): array
    {
        return array_combine(VeterinarianService::USAGE, array_map(fn (string $table) => [$table], VeterinarianService::USAGE));
    }

    #[DataProvider('usages')]
    public function test_a_veterinarian_in_use_is_not_deleted(string $table): void
    {
        $clinic = $this->createClinic();
        $veterinarian = $this->veterinarianOf($this->veterinarianUser($clinic), ['license_number' => 'MV-1001']);
        $this->usageRowFor($veterinarian, $table);
        $this->actAsVeterinarianManager($clinic);

        $response = $this->deleteJson("/api/veterinarians/{$veterinarian->id}");

        $this->assertErrorCode($response, 409, 'VETERINARIAN_IN_USE');
        $response->assertJsonPath('message', 'El veterinario tiene registros y no se puede eliminar: inactivalo.');
        $row = DB::table('veterinarians')->where('id', $veterinarian->id)->first();
        $this->assertNull($row->deleted_at);
        $this->assertSame('MV-1001', $row->license_number);
    }

    public function test_usage_covers_every_foreign_key_to_veterinarians(): void
    {
        $references = [];

        foreach (Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if ($foreignKey['foreign_table'] === 'veterinarians') {
                    $this->assertSame(['veterinarian_id'], $foreignKey['columns'], "{$table} referencia a veterinarians con otra columna.");
                    $references[] = $table;
                }
            }
        }

        sort($references);
        $usage = VeterinarianService::USAGE;
        sort($usage);

        $this->assertSame($references, $usage);
    }
}
