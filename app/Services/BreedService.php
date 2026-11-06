<?php

namespace App\Services;

use App\Exceptions\CatalogException;
use App\Models\Breed;
use App\Models\Species;
use App\Support\CatalogName;
use App\Support\DeletedSuffix;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Razas propias de la clínica (FASE 1, entrega 1.4), en una especie global o
 * propia. El nombre es único dentro de su especie, sin distinguir mayúsculas ni
 * acentos, frente a las globales y a las propias. Una raza no cambia de especie.
 */
class BreedService
{
    public const NAME_TAKEN = 'Ya existe una raza con ese nombre en esta especie.';

    public function create(Species $species, array $data): Breed
    {
        return DB::transaction(function () use ($species, $data) {
            CatalogLock::forCurrentClinic();
            $this->ensureNameIsFree($species, $data['name']);

            return Breed::create([...$data, 'species_id' => $species->id]);
        });
    }

    public function update(Breed $breed, array $data): Breed
    {
        // Antes que el nombre: una global responde 403, no 422.
        $breed->ensureNotGlobal();

        return DB::transaction(function () use ($breed, $data) {
            if (array_key_exists('name', $data)) {
                CatalogLock::forCurrentClinic();
                $this->ensureNameIsFree($breed->species, $data['name'], $breed);
            }

            $breed->update($data);

            return $breed;
        });
    }

    public function delete(Breed $breed): void
    {
        $breed->ensureNotGlobal();

        // Incluye a los pacientes eliminados: su historia sigue apuntando a la raza.
        if ($breed->patients()->withTrashed()->exists()) {
            throw new CatalogException(
                'La raza está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
                409,
                'BREED_IN_USE'
            );
        }

        DB::transaction(function () use ($breed) {
            // Sin cortar: la columna (110) admite el nombre (80) y el sufijo con un id de
            // hasta 9 dígitos (21 + id).
            $breed->name = $breed->name . DeletedSuffix::for($breed);
            $breed->save();
            $breed->delete();
        });
    }

    /**
     * Dentro de la especie, frente a las globales y a las propias no eliminadas.
     */
    private function ensureNameIsFree(Species $species, string $name, ?Breed $ignore = null): void
    {
        $names = $species->breeds()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->pluck('name');

        if (CatalogName::isTaken($name, $names)) {
            throw ValidationException::withMessages(['name' => self::NAME_TAKEN]);
        }
    }
}
