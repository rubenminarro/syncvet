<?php

namespace App\Services;

use App\Enums\DocumentSeries;
use App\Exceptions\PatientException;
use App\Models\Breed;
use App\Models\Client;
use App\Models\Patient;
use App\Support\DeletedSuffix;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

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
        $this->ensureBreedMatchesSpecies($data['species_id'], $data['breed_id'] ?? null);

        return DB::transaction(fn () => $this->sequences->create(
            DocumentSeries::PATIENT,
            fn (string $code) => $client->patients()->create([...$data, 'code' => $code])
        ));
    }

    public function update(Patient $patient, array $data): Patient
    {
        $this->ensureNotDeceased($patient, $data);

        if (! empty($data['deceased_at'])) {
            $data['deceased_at'] = $this->toAppTimezone($data['deceased_at']);
        }

        $this->ensureBreedMatchesSpecies(
            $data['species_id'] ?? $patient->species_id,
            array_key_exists('breed_id', $data) ? $data['breed_id'] : $patient->breed_id
        );

        $patient->update($data);

        return $patient;
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

    /**
     * La API recibe las fechas con hora en UTC ("Z") o con su desplazamiento; la
     * base guarda la hora de America/Asuncion. Sin convertir, el cast del modelo
     * guardaba la hora UTC como si fuera local (3 horas corrida). Deuda 13: llevar
     * la conversión de todas las fechas con hora a un lugar común antes de la FASE 3.
     */
    private function toAppTimezone(string $value): CarbonInterface
    {
        return Date::parse($value)->setTimezone(config('app.timezone'));
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
     * Un paciente fallecido solo admite corregir la fecha de fallecimiento o quitarla.
     */
    private function ensureNotDeceased(Patient $patient, array $data): void
    {
        if ($patient->deceased_at && array_diff(array_keys($data), ['deceased_at', 'notes']) !== []) {
            throw new PatientException(
                'El paciente figura como fallecido: solo se pueden editar la fecha de fallecimiento y las notas.',
                422,
                'PATIENT_DECEASED'
            );
        }
    }
}
