<?php

namespace App\Services;

use App\Enums\DocumentSeries;
use App\Exceptions\PatientException;
use App\Http\Requests\PatientMessages;
use App\Models\Breed;
use App\Models\Client;
use App\Models\Patient;
use App\Models\Species;
use App\Support\DeletedSuffix;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pacientes. El código PAC-000001 sale de la serie (DocumentSequenceService).
 * El paciente puede cambiar de dueño; sus consultas conservan el dueño de ese momento.
 */
class PatientService
{
    public function __construct(
        protected DocumentSequenceService $sequences
    ) {
    }

    public function create(Client $client, array $data): Patient
    {
        return DB::transaction(function () use ($client, $data) {
            // Orden de bloqueo: especie → raza → serie (la serie, siempre última).
            $this->lockCatalog($data['species_id'], $data['breed_id'] ?? null);

            return $this->sequences->create(
                DocumentSeries::PATIENT,
                fn (string $code) => $client->patients()->create([...$data, 'code' => $code])
            );
        });
    }

    public function update(Patient $patient, array $data): Patient
    {
        $this->ensureNotDeceased($patient, $data);

        // deceased_at llega en UTC (o con su desplazamiento): el cast AppDateTime lo guarda
        // en la hora de Asunción (FASE 2, entrega 2.6; deuda 13).
        return DB::transaction(function () use ($patient, $data) {
            $this->lockCatalog(
                $data['species_id'] ?? $patient->species_id,
                array_key_exists('breed_id', $data) ? $data['breed_id'] : $patient->breed_id
            );

            $patient->update($data);

            return $patient;
        });
    }

    /**
     * Bloqueo compartido (sharedLock) de la especie y la raza que el paciente va a
     * usar, dentro de la transacción del alta o de la edición. La baja de una especie
     * o una raza las bloquea con lockForUpdate antes de revisar si están en uso
     * (SpeciesService, BreedService): si la baja terminó primero, aquí ya no se
     * encuentran (el scope de SoftDeletes) y el pedido responde 422; si el paciente
     * terminó primero, la baja lo ve y responde 409. El Form Request ya las validó,
     * pero sin bloqueo: una baja podía entrar entre su validación y el alta.
     */
    private function lockCatalog(int $speciesId, ?int $breedId): void
    {
        if (Species::query()->whereKey($speciesId)->sharedLock()->first() === null) {
            throw ValidationException::withMessages(['species_id' => PatientMessages::SPECIES_UNAVAILABLE]);
        }

        if ($breedId !== null && Breed::query()->whereKey($breedId)->sharedLock()->first() === null) {
            throw ValidationException::withMessages(['breed_id' => PatientMessages::BREED_UNAVAILABLE]);
        }

        $this->ensureBreedMatchesSpecies($speciesId, $breedId);
    }

    /**
     * Cambio de dueño (venta, adopción, reasignación por error de carga).
     */
    public function changeOwner(Patient $patient, Client $newOwner): Patient
    {
        if ($patient->client_id === $newOwner->id) {
            throw new PatientException(
                'El paciente ya pertenece a ese cliente.',
                422,
                'PATIENT_SAME_OWNER'
            );
        }

        $patient->update(['client_id' => $newOwner->id]);

        return $patient;
    }

    public function delete(Patient $patient): void
    {
        if ($patient->consultations()->exists()
            || $patient->applications()->exists()
            || $patient->appointments()->exists()) {
            throw new PatientException(
                'El paciente tiene consultas, aplicaciones o turnos y no puede eliminarse. Si falleció, registrá la fecha de fallecimiento.',
                409,
                'PATIENT_IN_USE'
            );
        }

        DB::transaction(function () use ($patient) {
            if ($patient->microchip) {
                // La columna tiene 60 caracteres (entrega 1.3): 30 del chip más el
                // sufijo entero, con el id; sin cortar, dos bajas del mismo chip no chocan.
                $patient->microchip .= DeletedSuffix::for($patient);
                $patient->save();
            }

            $patient->delete();
        });
    }

    private function ensureBreedMatchesSpecies(int $speciesId, ?int $breedId): void
    {
        if ($breedId !== null && ! Breed::whereKey($breedId)->where('species_id', $speciesId)->exists()) {
            throw new PatientException(
                'La raza no corresponde a la especie del paciente.',
                422,
                'PATIENT_BREED_SPECIES_MISMATCH'
            );
        }
    }

    /**
     * Un paciente fallecido solo admite cambiar la fecha de fallecimiento (o quitarla)
     * y las notas. Los demás campos pueden llegar si no cambian: el frontend manda el
     * formulario completo (revisión de la entrega 1.3, R-021). Se comparan con los
     * casts del modelo, así "12.5" y "12.50" o "male" y PatientSex::MALE son iguales.
     */
    private function ensureNotDeceased(Patient $patient, array $data): void
    {
        if (! $patient->deceased_at) {
            return;
        }

        $probe = clone $patient;
        $probe->fill(Arr::except($data, ['deceased_at', 'notes']));

        if ($probe->isDirty()) {
            throw new PatientException(
                'El paciente figura como fallecido: solo se pueden editar la fecha de fallecimiento y las notas.',
                422,
                'PATIENT_DECEASED'
            );
        }
    }
}
