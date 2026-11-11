<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentTransition;
use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use App\Exceptions\AppointmentException;
use App\Exceptions\ScheduleChangedWhileLocking;
use App\Http\Requests\AppointmentMessages;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\ScheduleHour;
use App\Models\Service;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\ApiDateTime;
use App\Support\AppointmentRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

/**
 * Turnos únicos (FASE 3, entrega 3.2a; P2, P4 a P7 y preguntas 15 a 17).
 *
 * Reglas: la agenda activa y, si tiene veterinario, activo y con su usuario activo; la
 * sucursal y el veterinario se copian de la agenda. El servicio, el del pedido o el de la
 * agenda, activo. Paciente (no fallecido; el cliente es su dueño), cliente sin paciente o
 * nombre y teléfono de contacto. ends_at por defecto: la duración del servicio o slot_minutes
 * de la agenda. Desde el comienzo del día de hoy, sin cruzar la medianoche, hasta 720
 * minutos. Dentro del horario semanal, con los tramos que se tocan como uno solo (422
 * APPOINTMENT_OUTSIDE_HOURS), fuera de
 * los bloqueos (422 APPOINTMENT_IN_BLOCK) y sin superponerse con un turno que ocupa el
 * horario en la agenda o del mismo veterinario en otra agenda (409 APPOINTMENT_OVERLAP),
 * salvo un sobreturno (overbook), que saltea solo la superposición y queda con
 * is_overbooked si al guardar se superponía. Se editan y se mueven solo los turnos a
 * confirmar o confirmados (422 APPOINTMENT_NOT_EDITABLE); un turno movido conserva su estado.
 *
 * Concurrencia (orden de la FASE 3: clínica → veterinario → servicio → agenda → paciente →
 * turno; el alta no toma CatalogLock). Se lee la agenda sin bloqueo para saber su
 * veterinario y su servicio; después, el veterinario con lockForUpdate (serializa sus turnos
 * entre todas sus agendas, pregunta 15), el servicio con sharedLock, la agenda (al mover, las
 * dos, por id) con lockForUpdate, el paciente y el cliente con sharedLock y, al editar, el
 * turno con lockForUpdate. Con todo bloqueado se leen los tramos, los bloqueos y los turnos
 * que se superponen. Si entre la lectura y el bloqueo cambió el veterinario o el servicio de
 * la agenda, o la agenda, el paciente, el cliente o el estado del turno, se deshace la
 * transacción y se repite; un deadlock de MySQL (DetectsConcurrencyErrors) también. Después
 * de ATTEMPTS intentos, 409 CONFLICT: nunca un 500. Una espera vencida (1205) no se repite:
 * 409 CONFLICT enseguida.
 */
class AppointmentService
{
    use DetectsConcurrencyErrors;

    public const ATTEMPTS = 3;

    private const EDITABLE = [AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED];

    private const DATA_FIELDS = ['service_id', 'source', 'reason', 'notes'];

    /**
     * Los motivos de una fecha de la serie que no entra (errors.conflicts.*.reason, 3.3).
     */
    public const OUTSIDE_HOURS = 'outside_hours';

    public const IN_BLOCK = 'in_block';

    public const OVERLAP = 'overlap';

    /**
     * @param array<string, mixed> $data los campos validados de StoreAppointmentRequest
     */
    public function create(array $data): Appointment
    {
        $overbook = (bool) ($data['overbook'] ?? false);

        return $this->withRetries(function () use ($data, $overbook) {
            $read = Schedule::query()->whereKey((int) $data['schedule_id'])->first(['id', 'veterinarian_id', 'service_id']);

            if ($read === null) {
                throw ValidationException::withMessages(['schedule_id' => AppointmentMessages::SCHEDULE_NOT_AVAILABLE]);
            }

            return DB::transaction(fn () => $this->createLocked($data, $read, $overbook));
        });
    }

    /**
     * Una serie de turnos (FASE 3, entrega 3.3; P1): todos al crearla, con el mismo series_id,
     * o ninguno. Cada fecha pasa por las reglas del alta (el horario semanal, los bloqueos y la
     * superposición, en la agenda y del veterinario); si alguna falla, 409
     * APPOINTMENT_SERIES_CONFLICT con errors.conflicts: cada fecha con su motivo, todas, no solo
     * la primera. overbook vale para todas las fechas y saltea solo la superposición.
     *
     * Concurrencia: la serie entera en una transacción, con el veterinario y la agenda
     * bloqueados una vez, en el orden del alta; los mismos reintentos (withRetries).
     *
     * @param array<string, mixed> $data los campos validados de StoreAppointmentRequest, con recurrence
     * @return Collection<int, Appointment>
     */
    public function createSeries(array $data): Collection
    {
        $overbook = (bool) ($data['overbook'] ?? false);

        return $this->withRetries(function () use ($data, $overbook) {
            $read = Schedule::query()->whereKey((int) $data['schedule_id'])->first(['id', 'veterinarian_id', 'service_id']);

            if ($read === null) {
                throw ValidationException::withMessages(['schedule_id' => AppointmentMessages::SCHEDULE_NOT_AVAILABLE]);
            }

            return DB::transaction(fn () => $this->createSeriesLocked($data, $read, $overbook));
        });
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateAppointmentRequest (semántica parcial)
     */
    public function update(Appointment $appointment, array $data): Appointment
    {
        $overbook = (bool) ($data['overbook'] ?? false);

        return $this->withRetries(function () use ($appointment, $data, $overbook) {
            $read = Appointment::query()->whereKey($appointment->getKey())->firstOrFail(['id', 'schedule_id', 'patient_id', 'client_id', 'status']);
            $targetId = array_key_exists('schedule_id', $data) ? (int) $data['schedule_id'] : $read->schedule_id;
            $target = Schedule::query()->whereKey($targetId)->first(['id', 'veterinarian_id', 'service_id']);

            if ($target === null) {
                throw ValidationException::withMessages(['schedule_id' => AppointmentMessages::SCHEDULE_NOT_AVAILABLE]);
            }

            return DB::transaction(fn () => $this->updateLocked($data, $read, $target, $overbook));
        });
    }

    /**
     * El turno con lo que muestra el detalle.
     */
    public function loadDetail(Appointment $appointment): Appointment
    {
        return $appointment->load([
            'schedule', 'branch', 'veterinarian.user', 'service', 'patient', 'client', 'createdBy', 'cancelledBy',
        ]);
    }

    /**
     * Repite el intento si la agenda o el turno cambiaron entre la lectura y el bloqueo, o si
     * MySQL informa un deadlock; después de ATTEMPTS, 409 CONFLICT. Una espera vencida (1205),
     * 409 CONFLICT enseguida. Lo usan también las transiciones (AppointmentTransitionService).
     *
     * @template T
     * @param callable(): T $attempt
     * @return T
     */
    public function withRetries(callable $attempt): mixed
    {
        for ($try = 1; $try <= self::ATTEMPTS; $try++) {
            try {
                return $attempt();
            } catch (ScheduleChangedWhileLocking) {
                continue;
            } catch (Throwable $e) {
                // Una espera vencida (1205) no se repite: ya esperó innodb_lock_wait_timeout
                // y repetirla podría triplicar la espera. 409 CONFLICT enseguida.
                if ($this->isLockWaitTimeout($e)) {
                    throw $this->conflict();
                }

                if (! $this->causedByConcurrencyError($e)) {
                    throw $e;
                }
            }
        }

        throw $this->conflict();
    }

    private function conflict(): AppointmentException
    {
        return new AppointmentException(
            'La agenda cambió mientras se guardaba el turno: volvé a intentarlo.',
            409,
            'CONFLICT'
        );
    }

    /**
     * MySQL 1205 (Lock wait timeout exceeded), en la excepción o en una anterior.
     */
    private function isLockWaitTimeout(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof PDOException && (int) ($current->errorInfo[1] ?? 0) === 1205) {
                return true;
            }

            if (str_contains($current->getMessage(), 'Lock wait timeout exceeded')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createLocked(array $data, Schedule $read, bool $overbook): Appointment
    {
        [$schedule, $serviceId, $patientId, $clientId, $startsAt, $endsAt] = $this->lockForCreate($data, $read);

        $this->ensureTiming($startsAt, $endsAt);
        $overlaps = $this->ensureFits($schedule, $startsAt, $endsAt, $overbook);

        return $this->newAppointment($data, $schedule, $serviceId, $patientId, $clientId, $startsAt, $endsAt, $overlaps);
    }

    /**
     * La serie, con las filas bloqueadas una vez: el primer turno pasa por ensureTiming (el
     * día y la duración; los demás son días posteriores a la misma hora de reloj y con la
     * misma duración) y cada fecha por las reglas de ensureFits, sin cortar en la primera que
     * falla.
     *
     * @param array<string, mixed> $data
     * @return Collection<int, Appointment>
     */
    private function createSeriesLocked(array $data, Schedule $read, bool $overbook): Collection
    {
        [$schedule, $serviceId, $patientId, $clientId, $startsAt, $endsAt] = $this->lockForCreate($data, $read);

        $this->ensureTiming($startsAt, $endsAt);

        $recurrence = $data['recurrence'];
        $everyWeeks = (int) ($recurrence['every_weeks'] ?? 1);
        $count = AppointmentRecurrence::count(
            $startsAt,
            $everyWeeks,
            isset($recurrence['count']) ? (int) $recurrence['count'] : null,
            $recurrence['until'] ?? null
        );

        $dates = [];
        $conflicts = [];

        for ($index = 0; $index < $count; $index++) {
            $from = AppointmentRecurrence::shift($startsAt, $everyWeeks, $index);
            $to = AppointmentRecurrence::shift($endsAt, $everyWeeks, $index);
            [$reason, $overlaps] = $this->fit($schedule, $from, $to, $overbook);

            if ($reason !== null) {
                $conflicts[] = [
                    'starts_at' => $from->toISOString(),
                    'reason' => $reason,
                    'message' => $this->fitException($reason)->getMessage(),
                ];

                continue;
            }

            $dates[] = [$from, $to, $overlaps];
        }

        if ($conflicts !== []) {
            throw AppointmentException::seriesConflict($conflicts);
        }

        $seriesId = (string) Str::uuid();

        return new Collection(array_map(
            fn (array $date) => $this->newAppointment($data, $schedule, $serviceId, $patientId, $clientId, $date[0], $date[1], $date[2], $seriesId),
            $dates
        ));
    }

    /**
     * Los bloqueos y las revisiones del alta, en el orden de la FASE 3: veterinario →
     * servicio → agenda → paciente y cliente. Devuelve la agenda bloqueada, el servicio, el
     * paciente, el cliente y el inicio y el fin del (primer) turno, en la hora de Asunción.
     *
     * @param array<string, mixed> $data
     * @return array{0: Schedule, 1: ?int, 2: ?int, 3: ?int, 4: CarbonImmutable, 5: CarbonImmutable}
     */
    private function lockForCreate(array $data, Schedule $read): array
    {
        $serviceSent = array_key_exists('service_id', $data) && $data['service_id'] !== null;
        $serviceId = $serviceSent ? (int) $data['service_id'] : $read->service_id;

        // Orden de bloqueo: veterinario → servicio → agenda.
        $veterinarian = $this->lockVeterinarian($this->id($read->veterinarian_id));
        $service = $this->sharedService($this->id($serviceId));
        $schedule = $this->lockSchedules([$read->id])[$read->id];

        if ($this->id($schedule->veterinarian_id) !== $this->id($read->veterinarian_id)
            || (! $serviceSent && $this->id($schedule->service_id) !== $this->id($read->service_id))) {
            throw new ScheduleChangedWhileLocking();
        }

        $this->ensureScheduleIsAvailable($schedule, $veterinarian);

        if ($serviceId !== null) {
            $this->ensureServiceIsAvailable($service);
        }

        // Después de la agenda: el paciente y el cliente.
        [$patientId, $clientId] = $this->lockWho($data['patient_id'] ?? null, $data['client_id'] ?? null);

        $startsAt = ApiDateTime::toLocal($data['starts_at']);
        $endsAt = isset($data['ends_at'])
            ? ApiDateTime::toLocal($data['ends_at'])
            : $startsAt->addMinutes($service?->duration_minutes ?? $schedule->slot_minutes);

        return [$schedule, $this->id($serviceId), $patientId, $clientId, $startsAt, $endsAt];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function newAppointment(
        array $data,
        Schedule $schedule,
        ?int $serviceId,
        ?int $patientId,
        ?int $clientId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $overlaps,
        ?string $seriesId = null
    ): Appointment {
        return Appointment::create([
            ...Arr::only($data, self::DATA_FIELDS),
            'schedule_id' => $schedule->id,
            'branch_id' => $schedule->branch_id,
            'veterinarian_id' => $schedule->veterinarian_id,
            'service_id' => $serviceId,
            'patient_id' => $patientId,
            'client_id' => $clientId,
            'contact_name' => $clientId === null ? ($data['contact_name'] ?? null) : null,
            'contact_phone' => $clientId === null ? ($data['contact_phone'] ?? null) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => AppointmentStatus::PENDING_CONFIRMATION,
            'is_overbooked' => $overlaps,
            'series_id' => $seriesId,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateLocked(array $data, Appointment $read, Schedule $target, bool $overbook): Appointment
    {
        $mayMove = Arr::hasAny($data, ['schedule_id', 'starts_at', 'ends_at']);
        $serviceSent = array_key_exists('service_id', $data) && $data['service_id'] !== null;

        // Orden de bloqueo: veterinario de la agenda de destino → servicio → agendas de origen
        // y destino (por id) → paciente y cliente → turno. Sin mover, ni el veterinario ni las
        // agendas: el horario no cambia.
        $veterinarian = $mayMove ? $this->lockVeterinarian($this->id($target->veterinarian_id)) : null;
        $service = $serviceSent ? $this->sharedService((int) $data['service_id']) : null;
        $schedules = $mayMove ? $this->lockSchedules([(int) $read->schedule_id, (int) $target->id]) : [];

        if ($mayMove && $this->id($schedules[$target->id]->veterinarian_id) !== $this->id($target->veterinarian_id)) {
            throw new ScheduleChangedWhileLocking();
        }

        // El paciente que queda (el que llega o el que el turno ya tiene) con su dueño actual:
        // con paciente, el cliente del turno es su dueño actual. Si es el mismo paciente, se
        // acepta también el client_id guardado en el turno (el formulario lo reenvía después
        // de un cambio de dueño), y queda el dueño actual.
        $patientSent = array_key_exists('patient_id', $data);
        $clientSent = array_key_exists('client_id', $data);
        $patientId = $patientSent ? $this->id($data['patient_id']) : $this->id($read->patient_id);
        [$sentPatientId, $sentClientId] = $patientSent || $clientSent
            ? $this->lockWho(
                $patientId,
                $clientSent ? $data['client_id'] : null,
                $this->id($read->patient_id),
                $patientId !== null && $patientId === $this->id($read->patient_id) ? $this->id($read->client_id) : null
            )
            : [null, null];

        $appointment = Appointment::query()->whereKey($read->getKey())->lockForUpdate()->firstOrFail();

        if ($this->id($appointment->schedule_id) !== $this->id($read->schedule_id)
            || $this->id($appointment->patient_id) !== $this->id($read->patient_id)
            || $this->id($appointment->client_id) !== $this->id($read->client_id)
            || $appointment->status !== $read->status) {
            throw new ScheduleChangedWhileLocking();
        }

        if (! in_array($appointment->status, self::EDITABLE, true)) {
            throw new AppointmentException(
                'Solo se modifican los turnos a confirmar o confirmados.',
                422,
                'APPOINTMENT_NOT_EDITABLE'
            );
        }

        $changes = Arr::only($data, self::DATA_FIELDS);

        if ($serviceSent && (int) $data['service_id'] !== $this->id($appointment->service_id)) {
            // Uno nuevo, activo; el que el turno ya tiene se acepta aunque se haya inactivado.
            $this->ensureServiceIsAvailable($service);
        }

        $changes = [...$changes, ...$this->who($appointment, $data, $patientSent, $clientSent, $sentPatientId, $sentClientId)];

        if ($mayMove) {
            $schedule = $schedules[$target->id];
            $startsAt = array_key_exists('starts_at', $data) ? ApiDateTime::toLocal($data['starts_at']) : $appointment->starts_at->toImmutable();
            // Sin ends_at, conserva la duración del turno.
            $endsAt = array_key_exists('ends_at', $data) && $data['ends_at'] !== null
                ? ApiDateTime::toLocal($data['ends_at'])
                : $startsAt->addSeconds((int) abs($appointment->starts_at->diffInSeconds($appointment->ends_at)));

            $moves = $this->id($schedule->id) !== $this->id($appointment->schedule_id)
                || ! $startsAt->equalTo($appointment->starts_at)
                || ! $endsAt->equalTo($appointment->ends_at);

            if ($moves) {
                $this->ensureScheduleIsAvailable($schedule, $veterinarian);
                $this->ensureTiming($startsAt, $endsAt);
                $overlaps = $this->ensureFits($schedule, $startsAt, $endsAt, $overbook, $appointment);

                $changes = [
                    ...$changes,
                    'schedule_id' => $schedule->id,
                    'branch_id' => $schedule->branch_id,
                    'veterinarian_id' => $schedule->veterinarian_id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'is_overbooked' => $overlaps,
                ];
            }
        }

        $appointment->fill($changes);
        $appointment->save();

        return $appointment;
    }

    /**
     * El paciente, el cliente o el contacto que queda en el turno después de la edición.
     * Pasar de contacto a cliente o de cliente a paciente vacía los datos de contacto.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function who(Appointment $appointment, array $data, bool $patientSent, bool $clientSent, ?int $sentPatientId, ?int $sentClientId): array
    {
        $patientId = $appointment->patient_id;
        $clientId = $appointment->client_id;

        if ($patientSent || $clientSent) {
            // lockWho ya resolvió el paciente que queda y, con paciente, su dueño actual.
            $patientId = $patientSent ? $sentPatientId : $patientId;

            if ($patientId !== null || $clientSent) {
                $clientId = $sentClientId;
            }
        }

        $contactName = array_key_exists('contact_name', $data) ? $data['contact_name'] : $appointment->contact_name;
        $contactPhone = array_key_exists('contact_phone', $data) ? $data['contact_phone'] : $appointment->contact_phone;

        if ($clientId !== null) {
            foreach (['contact_name', 'contact_phone'] as $field) {
                if (($data[$field] ?? null) !== null) {
                    throw ValidationException::withMessages([$field => AppointmentMessages::CONTACT_PROHIBITED]);
                }
            }

            return ['patient_id' => $patientId, 'client_id' => $clientId, 'contact_name' => null, 'contact_phone' => null];
        }

        if ($contactName === null && $contactPhone === null) {
            throw ValidationException::withMessages(['patient_id' => AppointmentMessages::WHO_REQUIRED]);
        }

        if ($contactName === null || $contactPhone === null) {
            throw ValidationException::withMessages([$contactName === null ? 'contact_name' : 'contact_phone' => AppointmentMessages::CONTACT_TOGETHER]);
        }

        return ['patient_id' => null, 'client_id' => null, 'contact_name' => $contactName, 'contact_phone' => $contactPhone];
    }

    /**
     * El paciente y el cliente con sharedLock (su baja los bloquea con lockForUpdate antes de
     * revisar el uso). Con paciente, el cliente es su dueño actual: si llega otro, 422, salvo
     * $storedClientId (al editar, el cliente guardado en el turno del mismo paciente), que se
     * acepta y queda el dueño actual.
     *
     * @return array{0: ?int, 1: ?int} [patient_id, client_id]
     */
    private function lockWho(mixed $patientId, mixed $clientId, ?int $currentPatientId = null, ?int $storedClientId = null): array
    {
        $patientId = $patientId === null ? null : (int) $patientId;
        $clientId = $clientId === null ? null : (int) $clientId;

        if ($patientId !== null) {
            $patient = Patient::query()->whereKey($patientId)->sharedLock()->first();

            if ($patient === null) {
                throw ValidationException::withMessages(['patient_id' => AppointmentMessages::PATIENT_NOT_AVAILABLE]);
            }

            // El paciente que el turno ya tiene se acepta aunque haya fallecido (el formulario lo reenvía).
            if ($patient->deceased_at !== null && $patientId !== $currentPatientId) {
                throw ValidationException::withMessages(['patient_id' => AppointmentMessages::PATIENT_DECEASED]);
            }

            if ($clientId !== null && $clientId !== $this->id($patient->client_id) && $clientId !== $storedClientId) {
                throw ValidationException::withMessages(['patient_id' => AppointmentMessages::PATIENT_NOT_OF_CLIENT]);
            }

            $clientId = $this->id($patient->client_id);
        }

        if ($clientId !== null && Client::query()->whereKey($clientId)->sharedLock()->first() === null) {
            throw ValidationException::withMessages(['client_id' => AppointmentMessages::CLIENT_NOT_AVAILABLE]);
        }

        return [$patientId, $clientId];
    }

    private function id(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function lockVeterinarian(?int $id): ?Veterinarian
    {
        return $id === null ? null : Veterinarian::query()->whereKey($id)->lockForUpdate()->first();
    }

    private function sharedService(?int $id): ?Service
    {
        return $id === null ? null : Service::query()->whereKey($id)->sharedLock()->first();
    }

    /**
     * Las agendas con lockForUpdate, por id ascendente.
     *
     * @param list<int> $ids
     * @return array<int, Schedule>
     */
    private function lockSchedules(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);
        $schedules = [];

        foreach ($ids as $id) {
            $schedule = Schedule::query()->whereKey($id)->lockForUpdate()->first();

            if ($schedule === null) {
                throw ValidationException::withMessages(['schedule_id' => AppointmentMessages::SCHEDULE_NOT_AVAILABLE]);
            }

            $schedules[$id] = $schedule;
        }

        return $schedules;
    }

    /**
     * La agenda activa y, si tiene veterinario, la ficha activa (no eliminada) con su
     * usuario activo y de la clínica.
     */
    private function ensureScheduleIsAvailable(Schedule $schedule, ?Veterinarian $veterinarian): void
    {
        if (! $schedule->is_active) {
            throw ValidationException::withMessages(['schedule_id' => AppointmentMessages::SCHEDULE_NOT_AVAILABLE]);
        }

        if ($schedule->veterinarian_id === null) {
            return;
        }

        $available = $veterinarian !== null
            && $veterinarian->status === VeterinarianStatus::ACTIVE
            && User::query()->inClinic()->active()->whereKey($veterinarian->user_id)->exists();

        if (! $available) {
            throw ValidationException::withMessages(['schedule_id' => AppointmentMessages::VETERINARIAN_INACTIVE]);
        }
    }

    private function ensureServiceIsAvailable(?Service $service): void
    {
        if ($service === null || $service->status !== ServiceStatus::ACTIVE) {
            throw ValidationException::withMessages(['service_id' => AppointmentMessages::SERVICE_NOT_AVAILABLE]);
        }
    }

    /**
     * Desde el comienzo del día de hoy en Asunción (pregunta 16), el fin posterior al inicio,
     * hasta 720 minutos y el mismo día (no cruza la medianoche).
     */
    private function ensureTiming(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if ($startsAt->lessThan(now()->toImmutable()->startOfDay())) {
            throw ValidationException::withMessages(['starts_at' => AppointmentMessages::PAST_DAY]);
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => AppointmentMessages::ENDS_BEFORE_START]);
        }

        if ($endsAt->toDateString() !== $startsAt->toDateString()) {
            throw ValidationException::withMessages(['ends_at' => AppointmentMessages::CROSSES_MIDNIGHT]);
        }

        if ($startsAt->diffInMinutes($endsAt) > AppointmentMessages::MAX_MINUTES) {
            throw ValidationException::withMessages(['ends_at' => AppointmentMessages::TOO_LONG]);
        }
    }

    /**
     * P6: el rango entero dentro del horario del día de la semana de starts_at, en hora de reloj
     * de Asunción. Los tramos que se tocan (08:00–12:00 y 12:00–16:00) cuentan como un horario
     * continuo (decisión del usuario en la revisión de la 3.2a); un hueco entre tramos, no. Los
     * tramos del mismo día no se superponen (3.1) y van en múltiplos de 5 minutos, sin segundos.
     */
    private function insideHours(Schedule $schedule, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $ranges = ScheduleHour::query()
            ->where('schedule_id', $schedule->id)
            ->where('weekday', $startsAt->isoWeekday())
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at'])
            ->map(fn (ScheduleHour $hour) => [substr((string) $hour->starts_at, 0, 5), substr((string) $hour->ends_at, 0, 5)]);

        $start = $startsAt->format('H:i');
        $end = $endsAt->format('H:i');
        $merged = null;

        foreach ($ranges as [$from, $to]) {
            if ($merged !== null && $from === $merged[1]) {
                $merged[1] = $to;

                continue;
            }

            if ($merged !== null && $merged[0] <= $start && $end <= $merged[1]) {
                return true;
            }

            $merged = [$from, $to];
        }

        return $merged !== null && $merged[0] <= $start && $end <= $merged[1];
    }

    /**
     * Con la agenda (y su veterinario) bloqueada: dentro de un tramo del horario semanal,
     * fuera de los bloqueos y sin superposición (salvo un sobreturno). Devuelve si el turno
     * se superpone con otro (is_overbooked).
     */
    private function ensureFits(Schedule $schedule, CarbonImmutable $startsAt, CarbonImmutable $endsAt, bool $overbook, ?Appointment $ignore = null): bool
    {
        [$reason, $overlaps] = $this->fit($schedule, $startsAt, $endsAt, $overbook, $ignore);

        if ($reason !== null) {
            throw $this->fitException($reason);
        }

        return $overlaps;
    }

    /**
     * Las reglas de ensureFits sin lanzar (la serie, 3.3, junta todas las fechas que fallan):
     * el primer motivo por el que el turno no entra (OUTSIDE_HOURS, IN_BLOCK u OVERLAP; null
     * si entra) y si se superpone con otro turno.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function fit(Schedule $schedule, CarbonImmutable $startsAt, CarbonImmutable $endsAt, bool $overbook, ?Appointment $ignore = null): array
    {
        if (! $this->insideHours($schedule, $startsAt, $endsAt)) {
            return [self::OUTSIDE_HOURS, false];
        }

        $inBlock = ScheduleBlock::query()
            ->where('schedule_id', $schedule->id)
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $startsAt->format('Y-m-d H:i:s'))
            ->exists();

        if ($inBlock) {
            return [self::IN_BLOCK, false];
        }

        $overlaps = $this->overlapsAnother($schedule, $startsAt, $endsAt, $ignore);

        return [$overlaps && ! $overbook ? self::OVERLAP : null, $overlaps];
    }

    private function fitException(string $reason): AppointmentException
    {
        return match ($reason) {
            self::OUTSIDE_HOURS => new AppointmentException('El turno está fuera del horario de la agenda.', 422, 'APPOINTMENT_OUTSIDE_HOURS'),
            self::IN_BLOCK => new AppointmentException('La agenda está bloqueada en ese horario.', 422, 'APPOINTMENT_IN_BLOCK'),
            default => self::overlapException(),
        };
    }

    /**
     * Si el rango se superpone con un turno que ocupa el horario: a.starts_at < b.ends_at y
     * a.ends_at > b.starts_at (tocarse no choca), en la agenda y del mismo veterinario en sus
     * otras agendas (pregunta 15), sin contar $ignore. Se llama con la agenda (y su
     * veterinario) bloqueada.
     */
    private function overlapsAnother(Schedule $schedule, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?Appointment $ignore = null): bool
    {
        return Appointment::query()
            ->whereIn('status', AppointmentStatus::occupyingValues())
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $startsAt->format('Y-m-d H:i:s'))
            ->where(fn ($query) => $query
                ->where('schedule_id', $schedule->id)
                ->when($schedule->veterinarian_id !== null, fn ($query) => $query->orWhere('veterinarian_id', $schedule->veterinarian_id)))
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }

    private static function overlapException(): AppointmentException
    {
        return new AppointmentException(
            'El horario ya está ocupado: elegí otro o dalo como sobreturno.',
            409,
            'APPOINTMENT_OVERLAP'
        );
    }

    /**
     * Deshacer el ausente (FASE 3, entrega 3.2b; pregunta 19): vuelve a confirmado el mismo día
     * del turno en Asunción. Vuelve a ocupar el horario, así que bloquea como un alta: se lee el
     * turno y su agenda sin bloqueo; después, el veterinario de la agenda (lockForUpdate) → la
     * agenda (lockForUpdate) → el turno (lockForUpdate), y con eso bloqueado se revisa la
     * superposición sin contarse a sí mismo. Ocupado → 409 APPOINTMENT_OVERLAP, salvo con
     * $overbook (overbook: true, con appointments.overbook: lo revisa el Form Request) o si el
     * turno ya era sobreturno; is_overbooked queda en si se superpone. Un turno que ya era
     * sobreturno exige appointments.overbook (si no, 403), con o sin el horario tomado (decisión
     * del usuario en la revisión). El horario semanal y los bloqueos no se vuelven a revisar.
     * confirmed_at, la hora actual si no tenía.
     */
    public function undoNoShow(Appointment $appointment, bool $overbook = false): Appointment
    {
        return $this->withRetries(function () use ($appointment, $overbook) {
            $read = Appointment::query()->whereKey($appointment->getKey())->firstOrFail(['id', 'schedule_id']);
            $schedule = Schedule::query()->whereKey($read->schedule_id)->firstOrFail(['id', 'veterinarian_id']);

            return DB::transaction(function () use ($read, $schedule, $overbook) {
                // Orden de bloqueo: veterinario → agenda → turno.
                $this->lockVeterinarian($this->id($schedule->veterinarian_id));
                $locked = $this->lockSchedules([(int) $schedule->id])[(int) $schedule->id];
                $appointment = Appointment::query()->whereKey($read->getKey())->lockForUpdate()->firstOrFail();

                if ($this->id($locked->veterinarian_id) !== $this->id($schedule->veterinarian_id)
                    || $this->id($appointment->schedule_id) !== $this->id($read->schedule_id)) {
                    throw new ScheduleChangedWhileLocking();
                }

                if (! AppointmentTransition::UNDO_NO_SHOW->allowsFrom($appointment->status)) {
                    throw AppointmentException::invalidTransition($appointment->status);
                }

                if (! $appointment->startsToday()) {
                    throw new AppointmentException(
                        'El ausente solo se deshace el mismo día del turno.',
                        422,
                        'APPOINTMENT_NO_SHOW_NOT_UNDOABLE'
                    );
                }

                // Un turno que ya era sobreturno vuelve solo con el permiso del sobreturno.
                if ($appointment->is_overbooked && ! Auth::user()?->can('appointments.overbook')) {
                    throw new AuthorizationException();
                }

                $overlaps = $this->overlapsAnother($locked, $appointment->starts_at->toImmutable(), $appointment->ends_at->toImmutable(), $appointment);

                if ($overlaps && ! $overbook && ! $appointment->is_overbooked) {
                    throw self::overlapException();
                }

                $appointment->fill([
                    'status' => AppointmentTransition::UNDO_NO_SHOW->to(),
                    'no_show_at' => null,
                    'confirmed_at' => $appointment->confirmed_at ?? now(),
                    'is_overbooked' => $overlaps,
                ]);
                $appointment->save();

                return $appointment;
            });
        });
    }
}
