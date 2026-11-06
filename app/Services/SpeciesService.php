<?php

namespace App\Services;

use App\Exceptions\CatalogException;
use App\Models\Breed;
use App\Models\Patient;
use App\Models\Species;
use App\Support\CatalogName;
use App\Support\DeletedSuffix;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Especies propias de la clínica (FASE 1, entrega 1.4). El nombre es único sin
 * distinguir mayúsculas ni acentos frente a las globales y a las propias. Las
 * globales no se editan ni se eliminan (403). La baja, solo sin uso, se lleva
 * las razas de la especie.
 */
class SpeciesService
{
    public const NAME_TAKEN = 'Ya existe una especie con ese nombre.';

    public function create(array $data): Species
    {
        return DB::transaction(function () use ($data) {
            CatalogLock::forCurrentClinic();
            $this->ensureNameIsFree($data['name']);

            return Species::create($data);
        });
    }

    public function update(Species $species, array $data): Species
    {
        // Antes que el nombre: una global responde 403, no 422.
        $species->ensureNotGlobal();

        return DB::transaction(function () use ($species, $data) {
            if (array_key_exists('name', $data)) {
                CatalogLock::forCurrentClinic();
                $this->ensureNameIsFree($data['name'], $species);
            }

            $species->update($data);

            return $species;
        });
    }

    public function delete(Species $species): void
    {
        $species->ensureNotGlobal();

        // Incluye a los pacientes eliminados: su historia sigue apuntando a la especie.
        if ($species->patients()->withTrashed()->exists()
            || Patient::withTrashed()->whereIn('breed_id', $species->breeds()->select('id'))->exists()) {
            throw new CatalogException(
                'La especie está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
                409,
                'SPECIES_IN_USE'
            );
        }

        DB::transaction(function () use ($species) {
            // Las razas de una especie propia son de la clínica: se eliminan con ella,
            // una por una (auditoría y sufijo de cada nombre).
            $species->breeds()->get()->each(function (Breed $breed) {
                $breed->name = $breed->name . DeletedSuffix::for($breed);
                $breed->save();
                $breed->delete();
            });

            // Sin cortar: la columna (90) admite el nombre (60) y el sufijo con un id de
            // hasta 9 dígitos (21 + id).
            $species->name = $species->name . DeletedSuffix::for($species);
            $species->save();
            $species->delete();
        });
    }

    /**
     * Frente a las globales y a las propias no eliminadas (el scope ve las dos).
     */
    private function ensureNameIsFree(string $name, ?Species $ignore = null): void
    {
        $names = Species::query()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->pluck('name');

        if (CatalogName::isTaken($name, $names)) {
            throw ValidationException::withMessages(['name' => self::NAME_TAKEN]);
        }
    }
}
