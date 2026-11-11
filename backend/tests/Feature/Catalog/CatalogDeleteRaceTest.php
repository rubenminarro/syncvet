<?php

namespace Tests\Feature\Catalog;

use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * Baja de una especie o una raza frente a un alta o una edición de paciente
 * simultánea (revisión de la entrega 1.4). La baja bloquea la especie y sus razas
 * (lockForUpdate) y revisa el uso dentro de su transacción; el paciente toma un
 * bloqueo compartido (sharedLock) sobre la especie y la raza dentro de la suya y
 * las vuelve a buscar. Orden de bloqueo: especie → raza.
 *
 * Dos pedidos simultáneos no se pueden dar en PHPUnit (una sola conexión, dentro de
 * la transacción de RefreshDatabase): cada caso pone el otro pedido en el único
 * momento en que puede terminar sin esperar el bloqueo. Para la baja, justo antes
 * de su primera consulta dentro de la transacción (un paciente que termina antes):
 * antes de la corrección, el uso se revisaba antes de esa transacción y un alta en
 * ese hueco (la revisión la simulaba en el evento deleting) dejaba al paciente con
 * la especie eliminada. Para el paciente, justo antes de su primera consulta dentro
 * de la transacción (una baja que termina antes). En MySQL se exige además el "for
 * update" y el "lock in share mode"; SQLite bloquea la base entera al escribir.
 */
class CatalogDeleteRaceTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_a_patient_created_just_before_the_species_lock_prevents_the_delete(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $client = $this->clientIn($clinic);
        $ferret = $this->createSpecies('Hurón');
        $this->createBreed($ferret, 'Albino');

        $this->beforeFirstQueryInsideTransaction(fn () => $this->patientOf($client, ['species_id' => $ferret]));

        $this->assertErrorCode($this->deleteJson("/api/species/{$ferret}"), 409, 'SPECIES_IN_USE');
        $this->assertNull($this->speciesRow($ferret)->deleted_at);
    }

    public function test_a_patient_created_just_before_the_breed_lock_prevents_the_delete(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $client = $this->clientIn($clinic);
        $canine = $this->globalSpeciesId();
        $breed = $this->createBreed($canine, 'Fila');

        $this->beforeFirstQueryInsideTransaction(fn () => $this->patientOf($client, ['species_id' => $canine, 'breed_id' => $breed]));

        $this->assertErrorCode($this->deleteJson("/api/species/{$canine}/breeds/{$breed}"), 409, 'BREED_IN_USE');
        $this->assertNull($this->breedRow($breed)->deleted_at);
    }

    public function test_a_species_deleted_after_the_validation_does_not_reach_a_new_patient(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $client = $this->clientIn($clinic);
        $ferret = $this->createSpecies('Hurón');

        $this->beforeFirstQueryInsideTransaction(fn () => $this->softDelete('species', $ferret));

        $response = $this->postJson("/api/clients/{$client->id}/patients", $this->patientPayload(['species_id' => $ferret]));

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['species_id' => 'La especie seleccionada no existe o está inactiva.']);
        $this->assertSame(0, DB::table('patients')->where('species_id', $ferret)->count());
    }

    public function test_a_breed_deleted_after_the_validation_does_not_reach_an_edited_patient(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $patient = $this->patientOf($this->clientIn($clinic));
        $breed = $this->createBreed($this->globalSpeciesId(), 'Fila');

        $this->beforeFirstQueryInsideTransaction(fn () => $this->softDelete('breeds', $breed));

        $response = $this->patchJson("/api/patients/{$patient->id}", ['breed_id' => $breed]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['breed_id' => 'La raza seleccionada no existe o está inactiva.']);
        $this->assertNull(DB::table('patients')->where('id', $patient->id)->value('breed_id'));
    }

    public function test_the_delete_locks_the_species_and_its_breeds_before_reading_the_patients(): void
    {
        $this->actAsVet($this->createClinic());
        $ferret = $this->createSpecies('Hurón');
        $this->createBreed($ferret, 'Albino');

        $queries = $this->queriesInsideTransaction(fn () => $this->deleteJson("/api/species/{$ferret}")->assertOk());

        $species = $this->firstIndex($queries, '/^select .* from [`"]species[`"] where [`"]species[`"]\.[`"]id[`"] = \?/', 'for update');
        $breeds = $this->firstIndex($queries, '/^select .* from [`"]breeds[`"] where [`"]breeds[`"]\.[`"]species_id[`"] = \?/', 'for update');
        $patients = $this->firstIndex($queries, '/^select exists\(select \* from [`"]patients[`"]/');

        $this->assertNotNull($species, "No se bloqueó la especie:\n" . implode("\n", $queries));
        $this->assertNotNull($breeds, "No se bloquearon las razas:\n" . implode("\n", $queries));
        $this->assertNotNull($patients, "No se revisó el uso dentro de la transacción:\n" . implode("\n", $queries));
        $this->assertLessThan($breeds, $species, 'Orden de bloqueo: especie → raza.');
        $this->assertLessThan($patients, $breeds, 'Las razas se bloquearon después de revisar el uso.');
    }

    public function test_the_breed_delete_locks_the_breed_before_reading_the_patients(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();
        $breed = $this->createBreed($canine, 'Fila');

        $queries = $this->queriesInsideTransaction(fn () => $this->deleteJson("/api/species/{$canine}/breeds/{$breed}")->assertOk());

        $lock = $this->firstIndex($queries, '/^select .* from [`"]breeds[`"] where [`"]breeds[`"]\.[`"]id[`"] = \?/', 'for update');
        $patients = $this->firstIndex($queries, '/^select exists\(select \* from [`"]patients[`"]/');

        $this->assertNotNull($lock, "No se bloqueó la raza:\n" . implode("\n", $queries));
        $this->assertNotNull($patients, "No se revisó el uso dentro de la transacción:\n" . implode("\n", $queries));
        $this->assertLessThan($patients, $lock, 'La raza se bloqueó después de revisar el uso.');
    }

    public function test_creating_and_editing_a_patient_share_lock_the_species_and_breed_before_writing(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $client = $this->clientIn($clinic);
        $canine = $this->globalSpeciesId();
        $breed = $this->createBreed($canine, 'Fila');

        $queries = $this->queriesInsideTransaction(fn () => $this->postJson(
            "/api/clients/{$client->id}/patients",
            $this->patientPayload(['species_id' => $canine, 'breed_id' => $breed])
        )->assertCreated());
        $this->assertSharedLocksBefore($queries, '/^insert into [`"]patients[`"]/');

        $patient = Patient::withoutGlobalScopes()->latest('id')->first();
        $other = $this->createBreed($canine, 'Pila');
        $queries = $this->queriesInsideTransaction(fn () => $this->patchJson("/api/patients/{$patient->id}", ['breed_id' => $other])->assertOk());
        $this->assertSharedLocksBefore($queries, '/^update [`"]patients[`"]/');
    }

    /**
     * @param list<string> $queries
     */
    private function assertSharedLocksBefore(array $queries, string $write): void
    {
        $species = $this->firstIndex($queries, '/^select .* from [`"]species[`"] where [`"]species[`"]\.[`"]id[`"] = \?/', 'lock in share mode');
        $breed = $this->firstIndex($queries, '/^select .* from [`"]breeds[`"] where [`"]breeds[`"]\.[`"]id[`"] = \?/', 'lock in share mode');
        $writeIndex = $this->firstIndex($queries, $write);

        $this->assertNotNull($species, "No se bloqueó la especie:\n" . implode("\n", $queries));
        $this->assertNotNull($breed, "No se bloqueó la raza:\n" . implode("\n", $queries));
        $this->assertNotNull($writeIndex, "No se escribió el paciente dentro de la transacción:\n" . implode("\n", $queries));
        $this->assertLessThan($breed, $species, 'Orden de bloqueo: especie → raza.');
        $this->assertLessThan($writeIndex, $breed, 'La raza se bloqueó después de escribir el paciente.');
    }

    /**
     * Corre $callback una vez, justo antes de la primera consulta dentro de una
     * transacción propia del pedido (la del Service).
     */
    private function beforeFirstQueryInsideTransaction(callable $callback): void
    {
        $base = DB::transactionLevel();
        $done = false;

        DB::beforeExecuting(function () use (&$done, $base, $callback) {
            if ($done || DB::transactionLevel() <= $base) {
                return;
            }

            $done = true;
            $callback();
        });
    }

    private function softDelete(string $table, int $id): void
    {
        DB::table($table)->where('id', $id)->update(['deleted_at' => now()]);
    }

    /**
     * @return list<string>
     */
    private function queriesInsideTransaction(callable $request): array
    {
        $base = DB::transactionLevel();
        $queries = [];
        DB::listen(function ($query) use (&$queries, $base) {
            if ($query->connection->transactionLevel() > $base) {
                $queries[] = Str::lower($query->sql);
            }
        });

        $request();

        return $queries;
    }

    /**
     * Primera consulta que coincide con $pattern y, en MySQL, termina con $mysqlLock.
     *
     * @param list<string> $queries
     */
    private function firstIndex(array $queries, string $pattern, ?string $mysqlLock = null): ?int
    {
        foreach ($queries as $index => $sql) {
            if (preg_match($pattern, $sql) === 1
                && ($mysqlLock === null || DB::getDriverName() !== 'mysql' || str_ends_with($sql, $mysqlLock))) {
                return $index;
            }
        }

        return null;
    }
}
