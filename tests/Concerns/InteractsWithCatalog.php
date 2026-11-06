<?php

namespace Tests\Concerns;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Especies y razas por la API (FASE 1, entrega 1.4). El catálogo lo administra el
 * veterinario (R-015): los tests usan ese rol, no el administrador, que pasa todo
 * por Gate::before.
 */
trait InteractsWithCatalog
{
    use InteractsWithAuth;

    protected function actAsVet(Clinic $clinic): User
    {
        $user = $this->userWithRole($clinic, 'veterinario');
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Alta de una especie propia por la API; devuelve su id.
     */
    protected function createSpecies(string $name, array $extra = []): int
    {
        return $this->postJson('/api/species', ['name' => $name, ...$extra])->assertCreated()->json('data.id');
    }

    /**
     * Alta de una raza propia por la API; devuelve su id.
     */
    protected function createBreed(int $speciesId, string $name, array $extra = []): int
    {
        return $this->postJson("/api/species/{$speciesId}/breeds", ['name' => $name, ...$extra])->assertCreated()->json('data.id');
    }

    protected function speciesRow(int $id): object
    {
        return DB::table('species')->where('id', $id)->first();
    }

    protected function breedRow(int $id): object
    {
        return DB::table('breeds')->where('id', $id)->first();
    }
}
