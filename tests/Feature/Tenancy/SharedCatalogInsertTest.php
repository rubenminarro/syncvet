<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\ClinicContextMissingException;
use App\Models\Breed;
use App\Models\Species;
use App\Support\CurrentClinic;
use Closure;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * Altas masivas de los catálogos compartidos (SharedCatalogBuilder; FASE 1,
 * entrega 1.4). No pasan por el evento creating del modelo: el builder fija la
 * clínica en contexto en cada fila, rechaza otra clínica o un global explícito,
 * y falla sin clínica. Un alta desde una consulta (insertUsing) falla siempre.
 */
class SharedCatalogInsertTest extends TestCase
{
    use InteractsWithClinics;

    /**
     * Cada método de alta masiva con una fila sin clinic_id.
     *
     * @return array<string, array{0: Closure(array<string, mixed>): mixed}>
     */
    public static function insertMethods(): array
    {
        return [
            'insert' => [fn (array $row) => Species::query()->insert([$row])],
            'insert de una fila' => [fn (array $row) => Species::query()->insert($row)],
            'insertOrIgnore' => [fn (array $row) => Species::query()->insertOrIgnore([$row])],
            'insertGetId' => [fn (array $row) => Species::query()->insertGetId($row)],
            'fillAndInsert' => [fn (array $row) => Species::query()->fillAndInsert([$row])],
            'fillAndInsertOrIgnore' => [fn (array $row) => Species::query()->fillAndInsertOrIgnore([$row])],
            'fillAndInsertGetId' => [fn (array $row) => Species::query()->fillAndInsertGetId($row)],
            'upsert' => [fn (array $row) => Species::query()->upsert([$row], ['clinic_id', 'name'], ['is_active'])],
            'upsert sin columnas' => [fn (array $row) => Species::query()->upsert([$row], ['clinic_id', 'name'])],
        ];
    }

    #[DataProvider('insertMethods')]
    public function test_each_bulk_insert_sets_the_clinic_in_context(Closure $insert): void
    {
        $own = $this->createClinic();

        CurrentClinic::run($own, fn () => $insert(['name' => 'Hurón', 'is_active' => true]));

        $this->assertSame([$own->id], DB::table('species')->where('name', 'Hurón')->pluck('clinic_id')->all());
    }

    #[DataProvider('insertMethods')]
    public function test_each_bulk_insert_accepts_the_clinic_in_context(Closure $insert): void
    {
        $own = $this->createClinic();

        CurrentClinic::run($own, fn () => $insert(['clinic_id' => $own->id, 'name' => 'Hurón', 'is_active' => true]));

        $this->assertSame([$own->id], DB::table('species')->where('name', 'Hurón')->pluck('clinic_id')->all());
    }

    #[DataProvider('insertMethods')]
    public function test_each_bulk_insert_rejects_another_clinic(Closure $insert): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();

        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => $insert(['clinic_id' => $other->id, 'name' => 'Hurón', 'is_active' => true])));
    }

    #[DataProvider('insertMethods')]
    public function test_each_bulk_insert_rejects_an_explicit_global(Closure $insert): void
    {
        $own = $this->createClinic();

        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => $insert(['clinic_id' => null, 'name' => 'Hurón', 'is_active' => true])));
    }

    #[DataProvider('insertMethods')]
    public function test_each_bulk_insert_fails_without_a_clinic(Closure $insert): void
    {
        $this->assertRejected(fn () => $insert(['name' => 'Hurón', 'is_active' => true]));
    }

    public function test_bulk_insert_of_several_rows_rejects_them_all_if_one_is_foreign(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();

        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => Breed::query()->insert([
            ['species_id' => $this->globalSpeciesId(), 'name' => 'Propia'],
            ['species_id' => $this->globalSpeciesId(), 'name' => 'Ajena', 'clinic_id' => $other->id],
        ])));

        $this->assertFalse(DB::table('breeds')->where('name', 'Propia')->exists());
    }

    public function test_upsert_updates_an_own_record_and_never_changes_clinic_id(): void
    {
        $own = $this->createClinic();
        CurrentClinic::run($own, fn () => Species::create(['name' => 'Hurón']));

        CurrentClinic::run($own, fn () => Species::query()->upsert([['name' => 'Hurón', 'is_active' => false]], ['clinic_id', 'name'], ['is_active']));
        $this->assertFalse((bool) DB::table('species')->where('clinic_id', $own->id)->where('name', 'Hurón')->value('is_active'));

        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => Species::query()->upsert([['name' => 'Hurón', 'is_active' => true]], ['clinic_id', 'name'], ['is_active', 'clinic_id'])));
        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => Species::query()->upsert([['name' => 'Hurón']], ['clinic_id', 'name'], ['clinic_id' => null])));
    }

    /**
     * Un upsert por id actualizaría la fila con ese id aunque fuera global: ON
     * DUPLICATE KEY UPDATE no filtra por clínica. El id no se acepta.
     */
    public function test_upsert_cannot_use_the_id_to_reach_a_global(): void
    {
        $own = $this->createClinic();
        $canine = $this->globalSpeciesId();

        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => Species::query()->upsert([['id' => $canine, 'name' => 'Perro']], ['id'], ['name'])));
        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => Species::query()->upsert([['name' => 'Perro']], 'id', ['name'])));

        $this->assertSame('Canino', DB::table('species')->where('id', $canine)->value('name'));
    }

    /**
     * Revisión de la entrega 1.4: sin filas no se inserta nada, como en Laravel
     * (antes se armaba una fila con solo clinic_id y fallaba en la base).
     */
    public function test_an_empty_insert_does_nothing(): void
    {
        $own = $this->createClinic();
        $before = DB::table('species')->count();

        CurrentClinic::run($own, function () {
            $this->assertTrue(Species::query()->insert([]));
            $this->assertSame(0, Species::query()->insertOrIgnore([]));
            $this->assertTrue(Breed::query()->insert([]));
        });

        $this->assertSame($before, DB::table('species')->count());
    }

    public function test_insert_from_a_query_always_fails(): void
    {
        $own = $this->createClinic();
        $globals = DB::table('species')->whereNull('clinic_id')->select('name');

        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => Species::query()->insertUsing(['name'], $globals)));
        $this->assertRejected(fn () => CurrentClinic::run($own, fn () => Species::query()->insertOrIgnoreUsing(['name'], $globals)));
        $this->assertSame(0, DB::table('species')->where('clinic_id', $own->id)->count());
    }

    private function assertRejected(Closure $action): void
    {
        $before = DB::table('species')->count();

        try {
            $action();
            $this->fail('Un alta masiva del catálogo no fijó la clínica en contexto.');
        } catch (ClinicContextMissingException) {
            $this->assertSame($before, DB::table('species')->count());
        }
    }
}
