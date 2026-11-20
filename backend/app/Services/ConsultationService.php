<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentTransition;
use App\Enums\ConsultationItemBillingStatus;
use App\Enums\ConsultationStatus;
use App\Enums\DocumentSeries;
use App\Enums\UserRole;
use App\Enums\VeterinarianStatus;
use App\Exceptions\AppointmentException;
use App\Exceptions\ConsultationException;
use App\Exceptions\ScheduleChangedWhileLocking;
use App\Http\Requests\ConsultationMessages;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationAmendment;
use App\Models\ConsultationItem;
use App\Models\Patient;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\ApiDateTime;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La consulta (FASE 4, entrega 4.1; diseño R-099, "El vínculo con el turno" e "Integridad
 * clínica").
 *
 * Abrir:
 * - Desde un turno fichado: lo pasa a atendido (attended_at = ahora) en la misma transacción, con
 *   su auditoría. Desde uno ya atendido: solo el día del turno en Asunción (si no, 422
 *   CONSULTATION_APPOINTMENT_NOT_TODAY), y el turno no cambia. En otro estado, 422
 *   APPOINTMENT_INVALID_TRANSITION. Las reglas de attend (AppointmentOwnership): 403 APPOINTMENT_NOT_YOURS, antes que el estado. Un turno con consulta,
 *   409 CONSULTATION_ALREADY_EXISTS, también cuando salta el índice único (UNIQUE_APPOINTMENT).
 *   Toma el paciente, la sucursal y el motivo del turno.
 * - Sin turno: con patient_id; la sucursal actual de quien abre (si no tiene, 422
 *   CONSULTATION_NO_BRANCH). No crea un turno.
 * - client_id: el dueño actual del paciente. El código, CON- (DocumentSequenceService). Un
 *   paciente fallecido, 422 PATIENT_DECEASED.
 * - El veterinario: el del turno; si el turno no tiene, la ficha de quien abre; si no tiene ficha,
 *   el del pedido (obligatorio, activo). Uno distinto en el pedido, 422.
 * - consulted_at: el del pedido (no futuro, lo revisa el Form Request) o ahora; nunca anterior al
 *   fichado del turno.
 *
 * Editar (parcial): solo abierta (422 CONSULTATION_NOT_EDITABLE); la de su ficha o el
 * administrador (otro, 403 CONSULTATION_NOT_YOURS, antes que el estado), en lockEditable(), el único
 * lugar de la inmutabilidad. Cerrar, enmendar y anular (4.2): close(), amend() y cancel().
 *
 * Concurrencia (§3, el orden de la FASE 3 más la consulta: veterinario → paciente → cliente →
 * turno → consulta → serie): abrir toma el veterinario, el paciente y el cliente con sharedLock (la
 * baja de la ficha, del paciente y del cliente, y el cambio de dueño, los bloquean para escribir) y
 * el turno con lockForUpdate, antes de revisar su estado y si ya tiene consulta; si el turno cambió
 * de paciente o de veterinario entre la lectura y el bloqueo, se repite. Editar toma la consulta con
 * lockForUpdate antes de leer su estado. Los reintentos de AppointmentService::withRetries (un
 * deadlock, hasta 3 intentos; una espera vencida, 409 CONFLICT enseguida).
 */
class ConsultationService
{
    /**
     * El índice único de appointment_id (migración adjust_consultations_for_phase_4): su nombre en
     * MySQL y la columna en SQLite (§3, nunca la columna en el mensaje de MySQL).
     */
    public const UNIQUE_APPOINTMENT = ['consultations_appointment_unique', 'consultations.appointment_id'];

    public const CONFLICT = 'La consulta cambió mientras se guardaba: volvé a intentarlo.';

    /**
     * Los campos que se copian del pedido a la consulta (al abrir y al editar).
     */
    public const CLINICAL_FIELDS = [
        'consulted_at', 'reason', 'history', 'weight_kg', 'temperature_c', 'heart_rate', 'respiratory_rate',
        'mucous_membranes', 'capillary_refill_time', 'body_condition_score', 'physical_exam', 'diagnosis',
        'treatment', 'instructions',
    ];

    public function __construct(
        protected AppointmentService $appointments,
        protected AppointmentOwnership $ownership,
        protected DocumentSequenceService $sequences
    ) {
    }

    /**
     * @param array<string, mixed> $data los campos validados de StoreConsultationRequest
     */
    public function open(array $data, User $user): Consultation
    {
        return $this->appointments->withRetries(function () use ($data, $user) {
            try {
                return DB::transaction(fn () => isset($data['appointment_id'])
                    ? $this->openFromAppointment($data, $user)
                    : $this->openWithoutAppointment($data, $user));
            } catch (UniqueConstraintViolationException $e) {
                if ($this->isAppointmentViolation($e)) {
                    throw ConsultationException::alreadyExists();
                }

                throw $e;
            }
        }, self::CONFLICT);
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateConsultationRequest (semántica parcial)
     */
    public function update(Consultation $consultation, array $data, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($consultation, $data, $user) {
            $locked = $this->lockEditable($consultation, $user);
            $fields = Arr::only($data, self::CLINICAL_FIELDS);

            if (array_key_exists('consulted_at', $fields)) {
                $checkedInAt = $locked->appointment_id === null
                    ? null
                    : Appointment::query()->whereKey($locked->appointment_id)->first(['id', 'checked_in_at'])?->checked_in_at;
                $this->ensureNotBeforeCheckIn($fields['consulted_at'], $checkedInAt);
            }

            $locked->fill($fields);
            $locked->save();

            return $locked;
        }), self::CONFLICT);
    }

    /**
     * La inmutabilidad (entrega 4.2), en un solo lugar: toda escritura sobre una consulta (editar y,
     * desde la 4.3 a la 4.5, los ítems, las aplicaciones y los adjuntos) la llama dentro de su
     * transacción. Bloquea la consulta (lockForUpdate) antes de leer su estado; la ficha va antes
     * que el estado (403 CONSULTATION_NOT_YOURS: un veterinario no averigua el estado de una
     * consulta ajena por el error); cerrada o anulada, 422 CONSULTATION_NOT_EDITABLE.
     */
    public function lockEditable(Consultation $consultation, User $user): Consultation
    {
        $locked = Consultation::query()->whereKey($consultation->getKey())->lockForUpdate()->firstOrFail();

        $this->ensureOwnConsultation($locked, $user);

        if ($locked->status !== ConsultationStatus::OPEN) {
            throw ConsultationException::notEditable();
        }

        return $locked;
    }

    /**
     * Cerrar (entrega 4.2; preguntas 5, 8 y 14): la de su ficha o el administrador; solo abierta
     * (422 CONSULTATION_NOT_CLOSABLE); con el motivo y el diagnóstico (si falta alguno, 422
     * CONSULTATION_CLOSE_INCOMPLETE con errors por campo). Fija closed_at y closed_by y copia el peso
     * al paciente si la consulta lo tiene (con su auditoría), salvo un paciente fallecido (solo
     * cambian su fecha y sus notas, §6.2).
     *
     * Concurrencia (§3, paciente → consulta): el paciente con lockForUpdate (su edición y su cambio de
     * dueño lo bloquean primero) y después la consulta, antes de leer su estado.
     */
    public function close(Consultation $consultation, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($consultation, $user) {
            // patient_id no cambia nunca: se lee sin bloqueo.
            $patientId = Consultation::query()->whereKey($consultation->getKey())->value('patient_id');
            $patient = Patient::query()->withTrashed()->whereKey($patientId)->lockForUpdate()->first();
            $locked = Consultation::query()->whereKey($consultation->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureOwnConsultation($locked, $user);

            if ($locked->status !== ConsultationStatus::OPEN) {
                throw ConsultationException::notClosable();
            }

            $missing = array_filter([
                'reason' => blank($locked->reason) ? [ConsultationMessages::CLOSE_REASON_REQUIRED] : null,
                'diagnosis' => blank($locked->diagnosis) ? [ConsultationMessages::CLOSE_DIAGNOSIS_REQUIRED] : null,
            ]);

            if ($missing !== []) {
                throw ConsultationException::closeIncomplete($missing);
            }

            $locked->fill(['status' => ConsultationStatus::CLOSED, 'closed_at' => now(), 'closed_by' => $user->getKey()]);
            $locked->save();

            if ($locked->weight_kg !== null && $patient !== null && $patient->deceased_at === null) {
                $patient->weight_kg = $locked->weight_kg;
                $patient->save();
            }

            return $locked;
        }), self::CONFLICT);
    }

    /**
     * Enmendar una cerrada (entrega 4.2; pregunta 4 y D4): la de su ficha o el administrador (403
     * CONSULTATION_NOT_YOURS); abierta o anulada, 422 CONSULTATION_NOT_AMENDABLE. La enmienda es
     * inmutable; la consulta no cambia.
     *
     * @param array{content: string, reason: string, field?: ?string} $data
     */
    public function amend(Consultation $consultation, array $data, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($consultation, $data, $user) {
            $locked = Consultation::query()->whereKey($consultation->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureOwnConsultation($locked, $user);

            if ($locked->status !== ConsultationStatus::CLOSED) {
                throw ConsultationException::notAmendable();
            }

            ConsultationAmendment::create([
                'consultation_id' => $locked->getKey(),
                'field' => $data['field'] ?? null,
                'content' => $data['content'],
                'reason' => $data['reason'],
                'created_by' => $user->getKey(),
            ]);

            return $locked;
        }), self::CONFLICT);
    }

    /**
     * Anular (entrega 4.2; pregunta 12): solo el administrador (el permiso consultations.cancel),
     * con motivo; abierta o cerrada (anulada, 422 CONSULTATION_NOT_CANCELLABLE). La consulta queda
     * visible, con todo su contenido; sus ítems pendientes pasan a no_charge (no se cobran); el turno
     * sigue atendido. Las aplicaciones se anulan con ella desde la 4.4, cuando exista su anulación.
     *
     * Concurrencia (§3, consulta → ítem): la consulta con lockForUpdate antes de leer su estado y
     * después sus ítems, por id.
     */
    public function cancel(Consultation $consultation, string $reason, User $user): Consultation
    {
        return $this->appointments->withRetries(fn () => DB::transaction(function () use ($consultation, $reason, $user) {
            $locked = Consultation::query()->whereKey($consultation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === ConsultationStatus::CANCELLED) {
                throw ConsultationException::notCancellable();
            }

            $locked->fill([
                'status' => ConsultationStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);
            $locked->save();

            $items = ConsultationItem::query()->where('consultation_id', $locked->getKey())->orderBy('id')->lockForUpdate()->get();

            // Desde la FASE 8, un ítem facturado impide anular (422 CONSULTATION_NOT_CANCELLABLE).
            foreach ($items as $item) {
                if ($item->billing_status === ConsultationItemBillingStatus::PENDING) {
                    $item->billing_status = ConsultationItemBillingStatus::NO_CHARGE;
                    $item->save();
                }
            }

            return $locked;
        }), self::CONFLICT);
    }

    /**
     * La consulta con lo que muestra el detalle (ShowConsultationResource).
     */
    public function loadDetail(Consultation $consultation): Consultation
    {
        return $consultation->load([
            'branch', 'patient', 'client', 'veterinarian.user', 'appointment', 'createdBy', 'closedBy', 'cancelledBy',
            // Entrega 4.2: las enmiendas, con su autor.
            'amendments.createdBy',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function openFromAppointment(array $data, User $user): Consultation
    {
        $read = Appointment::query()->whereKey((int) $data['appointment_id'])
            ->firstOrFail(['id', 'patient_id', 'veterinarian_id']);
        $veterinarianId = $this->resolveVeterinarian($read->veterinarian_id === null ? null : (int) $read->veterinarian_id, $data, $user);
        $patient = $this->lockPatientAndOwner($read->patient_id === null ? null : (int) $read->patient_id);

        $appointment = Appointment::query()->whereKey($read->getKey())->lockForUpdate()->firstOrFail();

        // Lo que se bloqueó antes salió de la lectura sin bloqueo: si el turno cambió de paciente
        // o de veterinario en el medio, se repite.
        if ((int) $appointment->patient_id !== (int) $read->patient_id
            || (int) $appointment->veterinarian_id !== (int) $read->veterinarian_id) {
            throw new ScheduleChangedWhileLocking();
        }

        $this->ownership->ensure($appointment);

        if ($appointment->status === AppointmentStatus::ATTENDED) {
            if (! $appointment->startsToday()) {
                throw ConsultationException::appointmentNotToday();
            }
        } elseif (! AppointmentTransition::ATTEND->allowsFrom($appointment->status)) {
            throw AppointmentException::invalidTransition($appointment->status);
        }

        if (Consultation::query()->where('appointment_id', $appointment->getKey())->exists()) {
            throw ConsultationException::alreadyExists();
        }

        // Un turno fichado siempre tiene paciente (fichar lo exige).
        if ($patient === null) {
            throw AppointmentException::invalidTransition($appointment->status);
        }

        $this->ensureAlive($patient);

        $consultedAt = $data['consulted_at'] ?? now();
        $this->ensureNotBeforeCheckIn($consultedAt, $appointment->checked_in_at);

        if ($appointment->status === AppointmentStatus::CHECKED_IN) {
            $appointment->fill(['status' => AppointmentTransition::ATTEND->to(), 'attended_at' => now()]);
            $appointment->save();
        }

        return $this->create([
            ...Arr::only($data, self::CLINICAL_FIELDS),
            'consulted_at' => $consultedAt,
            'reason' => array_key_exists('reason', $data) ? $data['reason'] : $appointment->reason,
            'appointment_id' => $appointment->getKey(),
            'branch_id' => $appointment->branch_id,
            'patient_id' => $patient->getKey(),
            'client_id' => $patient->client_id,
            'veterinarian_id' => $veterinarianId,
        ], $user);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function openWithoutAppointment(array $data, User $user): Consultation
    {
        if ($user->current_branch_id === null) {
            throw ConsultationException::noBranch();
        }

        $veterinarianId = $this->resolveVeterinarian(null, $data, $user);
        $patient = $this->lockPatientAndOwner((int) $data['patient_id']);

        if ($patient === null) {
            throw ValidationException::withMessages(['patient_id' => 'El paciente no existe.']);
        }

        $this->ensureAlive($patient);

        return $this->create([
            ...Arr::only($data, self::CLINICAL_FIELDS),
            'consulted_at' => $data['consulted_at'] ?? now(),
            'branch_id' => $user->current_branch_id,
            'patient_id' => $patient->getKey(),
            'client_id' => $patient->client_id,
            'veterinarian_id' => $veterinarianId,
        ], $user);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function create(array $attributes, User $user): Consultation
    {
        // La serie, siempre al final del orden de bloqueo (§3).
        return $this->sequences->create(DocumentSeries::CONSULTATION, fn (string $code) => Consultation::create([
            ...$attributes,
            'code' => $code,
            'status' => ConsultationStatus::OPEN,
            'created_by' => $user->getKey(),
        ]));
    }

    /**
     * El veterinario de la consulta: el del turno; si no tiene, la ficha (no eliminada) de quien
     * abre; si no tiene ficha, el del pedido, obligatorio y activo. Uno del pedido distinto del que
     * corresponde, 422. Lo toma con sharedLock: la baja de la ficha la bloquea para escribir antes
     * de revisar el uso (§3).
     *
     * @param array<string, mixed> $data
     */
    private function resolveVeterinarian(?int $fromAppointment, array $data, User $user): int
    {
        $requested = isset($data['veterinarian_id']) ? (int) $data['veterinarian_id'] : null;
        $resolved = $fromAppointment
            ?? Veterinarian::query()->where('user_id', $user->getKey())->value('id')
            ?? $requested;

        if ($resolved === null) {
            throw ValidationException::withMessages(['veterinarian_id' => ConsultationMessages::VETERINARIAN_REQUIRED]);
        }

        if ($requested !== null && $requested !== (int) $resolved) {
            throw ValidationException::withMessages(['veterinarian_id' => ConsultationMessages::VETERINARIAN_FIXED]);
        }

        $veterinarian = Veterinarian::query()->withTrashed()->whereKey($resolved)->sharedLock()->first(['id', 'status', 'deleted_at']);

        // El del pedido, activo y no eliminado; el del turno o la ficha propia, como están.
        if ($requested !== null && ($veterinarian === null || $veterinarian->trashed() || $veterinarian->status !== VeterinarianStatus::ACTIVE)) {
            throw ValidationException::withMessages(['veterinarian_id' => ConsultationMessages::VETERINARIAN_INACTIVE]);
        }

        return (int) $resolved;
    }

    /**
     * El paciente y su dueño actual, con sharedLock, en ese orden (§3): el cambio de dueño y las
     * bajas los bloquean para escribir. null sin paciente.
     */
    private function lockPatientAndOwner(?int $patientId): ?Patient
    {
        if ($patientId === null) {
            return null;
        }

        $patient = Patient::query()->whereKey($patientId)->sharedLock()->first(['id', 'client_id', 'deceased_at']);

        if ($patient !== null) {
            Client::query()->withTrashed()->whereKey($patient->client_id)->sharedLock()->first(['id']);
        }

        return $patient;
    }

    private function ensureAlive(Patient $patient): void
    {
        if ($patient->deceased_at !== null) {
            throw ConsultationException::patientDeceased();
        }
    }

    private function ensureNotBeforeCheckIn(mixed $consultedAt, ?CarbonInterface $checkedInAt): void
    {
        if ($checkedInAt === null) {
            return;
        }

        $at = $consultedAt instanceof CarbonInterface ? $consultedAt : ApiDateTime::toLocal((string) $consultedAt);

        if (ApiDateTime::floorSecond($at->toImmutable())->lessThan($checkedInAt->copy()->startOfSecond())) {
            throw ValidationException::withMessages(['consulted_at' => ConsultationMessages::BEFORE_CHECK_IN]);
        }
    }

    /**
     * Editan la consulta abierta su veterinario (la ficha no eliminada de quien edita) y el
     * administrador; otro, 403 CONSULTATION_NOT_YOURS (pregunta 8).
     */
    private function ensureOwnConsultation(Consultation $consultation, User $user): void
    {
        if ($user->hasRole(UserRole::ADMINISTRATOR->value, 'api')) {
            return;
        }

        $ownId = Veterinarian::query()->where('user_id', $user->getKey())->value('id');

        if ($ownId === null || (int) $ownId !== (int) $consultation->veterinarian_id) {
            throw ConsultationException::notYours();
        }
    }

    private function isAppointmentViolation(UniqueConstraintViolationException $e): bool
    {
        foreach (self::UNIQUE_APPOINTMENT as $index) {
            if (str_contains($e->getMessage(), $index)) {
                return true;
            }
        }

        return false;
    }
}
