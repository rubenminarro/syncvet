<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\PatientApplicationException;
use App\Http\Requests\PatientApplicationMessages;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PatientApplication;
use App\Models\User;
use App\Models\Veterinarian;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las aplicaciones (FASE 4, entrega 4.4; pregunta 12 del diseño, R-099): vacunas, desparasitaciones y
 * medicamentos, con o sin consulta.
 *
 * Cargar (POST patients/{patient}/applications): un paciente no fallecido (422 PATIENT_DECEASED); con
 * consulta, la del mismo paciente (si no, 422 en consultation_id), abierta y de la ficha de quien carga
 * o del administrador (ConsultationService::lockEditable: 403 CONSULTATION_NOT_YOURS, 422
 * CONSULTATION_NOT_EDITABLE). El veterinario de la aplicación: el de la consulta; sin consulta, la ficha
 * de quien carga (o null).
 *
 * Anular (POST patient-applications/{application}/cancel; decisión del usuario en la 4.4): con motivo;
 * una ya anulada, 422 PATIENT_APPLICATION_NOT_CANCELLABLE. Con consulta: solo abierta (cerrada, 422
 * CONSULTATION_NOT_EDITABLE: se anula con la consulta), por su veterinario o el administrador. Sin
 * consulta: el administrador; otro, solo la que cargó él el mismo día en Asunción (si no, 403
 * PATIENT_APPLICATION_NOT_YOURS). Al anular la consulta, sus aplicaciones se anulan con ella
 * (ConsultationService::cancel).
 *
 * Concurrencia (§3, el orden de la fase: veterinario → paciente → consulta → aplicación): cargar toma el
 * veterinario y el paciente con sharedLock (su baja los bloquea para escribir) y, con consulta, la
 * consulta con lockForUpdate; anular, la consulta (si tiene) y después la aplicación, las dos con
 * lockForUpdate, antes de leer sus estados. Los reintentos de AppointmentService::withRetries.
 */
class PatientApplicationService
{
    public function __construct(
        protected AppointmentService $appointments,
        protected ConsultationService $consultations
    ) {
    }

    /**
     * @param array<string, mixed> $data los campos validados de StorePatientApplicationRequest
     */
    public function create(Patient $patient, array $data, User $user): PatientApplication
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($patient, $data, $user) {
            $consultationId = isset($data['consultation_id']) ? (int) $data['consultation_id'] : null;
            // patient_id y veterinarian_id de una consulta no cambian nunca: se leen sin bloqueo.
            $consultation = $consultationId === null ? null
                : Consultation::query()->whereKey($consultationId)->first(['id', 'patient_id', 'veterinarian_id']);

            if ($consultation !== null && (int) $consultation->patient_id !== (int) $patient->getKey()) {
                throw ValidationException::withMessages(['consultation_id' => PatientApplicationMessages::CONSULTATION_OF_ANOTHER_PATIENT]);
            }

            $veterinarianId = $consultation?->veterinarian_id ?? Veterinarian::query()->where('user_id', $user->getKey())->value('id');

            if ($veterinarianId !== null) {
                Veterinarian::query()->withTrashed()->whereKey($veterinarianId)->sharedLock()->first(['id']);
            }

            $locked = Patient::query()->whereKey($patient->getKey())->sharedLock()->firstOrFail(['id', 'deceased_at']);

            if ($locked->deceased_at !== null) {
                throw PatientApplicationException::patientDeceased();
            }

            if ($consultation !== null) {
                $this->consultations->lockEditable($consultation, $user);
            }

            return PatientApplication::create([
                ...$data,
                'patient_id' => $patient->getKey(),
                'consultation_id' => $consultation?->getKey(),
                'veterinarian_id' => $veterinarianId,
                'created_by' => $user->getKey(),
            ]);
        }), ConsultationService::CONFLICT);
    }

    public function cancel(PatientApplication $application, string $reason, User $user): PatientApplication
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($application, $reason, $user) {
            // consultation_id no cambia nunca: se lee sin bloqueo, para bloquear la consulta primero.
            $consultationId = PatientApplication::query()->whereKey($application->getKey())->value('consultation_id');
            $consultation = $consultationId === null ? null
                : Consultation::query()->whereKey($consultationId)->lockForUpdate()->firstOrFail();
            $locked = PatientApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->cancelled_at !== null) {
                throw PatientApplicationException::notCancellable();
            }

            if ($consultation !== null) {
                $this->consultations->ensureEditable($consultation, $user);
            } else {
                $this->ensureCanCancelWithoutConsultation($locked, $user);
            }

            $locked->fill(['cancelled_at' => now(), 'cancelled_by' => $user->getKey(), 'cancellation_reason' => $reason]);
            $locked->save();

            return $locked;
        }), ConsultationService::CONFLICT);
    }

    /**
     * El administrador; otro, solo la que cargó él el mismo día en Asunción (decisión del usuario en la
     * 4.4); si no, 403 PATIENT_APPLICATION_NOT_YOURS.
     */
    private function ensureCanCancelWithoutConsultation(PatientApplication $application, User $user): void
    {
        if ($user->hasRole(UserRole::ADMINISTRATOR->value, 'api')) {
            return;
        }

        $timezone = config('app.timezone');
        $loadedToday = $application->created_at !== null
            && $application->created_at->copy()->setTimezone($timezone)->toDateString() === now($timezone)->toDateString();

        if ((int) $application->created_by !== (int) $user->getKey() || ! $loadedToday) {
            throw PatientApplicationException::notYours();
        }
    }
}
