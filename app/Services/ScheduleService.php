<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use App\Exceptions\ScheduleException;
use App\Http\Requests\ScheduleMessages;
use App\Models\Branch;
use App\Models\Schedule;
use App\Models\ScheduleHour;
use App\Models\Service;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\CatalogName;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agendas (FASE 3, entrega 3.1; P9 a P11 y preguntas 13, 14 y 24): de una sucursal activa,
 * con un veterinario, un servicio o los dos (al menos uno); el veterinario activo, con su
 * usuario activo y asignado a la sucursal; el servicio activo. Al editar se acepta el
 * veterinario o el servicio que la agenda ya tiene aunque se haya inactivado. Una agenda con
 * turnos no cambia de sucursal ni de veterinario (422 SCHEDULE_NOT_EDITABLE); el servicio sí.
 * Se inactiva aunque tenga turnos futuros; se elimina (sin SoftDeletes) solo sin turnos, con
 * sus tramos y sus bloqueos (409 SCHEDULE_IN_USE).
 *
 * Concurrencia (orden de la FASE 3: clínica → veterinario → servicio → agenda). El alta y el
 * cambio de nombre o de sucursal bloquean la clínica (CatalogLock) antes de leer los nombres;
 * el veterinario y el servicio que llegan se leen con sharedLock antes de la agenda (una baja
 * simultánea del veterinario o del servicio, que los bloquea con lockForUpdate antes de
 * revisar el uso, ve la agenda o la hace esperar); la edición, el horario y la baja bloquean
 * la agenda (lockForUpdate) antes de revisar los turnos o reemplazar el horario.
 */
class ScheduleService
{
    private const FIELDS = ['branch_id', 'name', 'veterinarian_id', 'service_id', 'slot_minutes', 'color', 'is_active'];

    /**
     * @param array<string, mixed> $data los campos validados de StoreScheduleRequest
     */
    public function create(array $data): Schedule
    {
        return DB::transaction(function () use ($data) {
            CatalogLock::forCurrentClinic();

            $branchId = (int) $data['branch_id'];
            $veterinarianId = $this->id($data['veterinarian_id'] ?? null);
            $serviceId = $this->id($data['service_id'] ?? null);

            $this->ensureHasOwner($veterinarianId, $serviceId);
            $veterinarian = $this->sharedVeterinarian($veterinarianId);
            $service = $this->sharedService($serviceId);

            $this->ensureBranchIsActive($branchId);

            if ($veterinarianId !== null) {
                $this->ensureVeterinarianIsAvailable($veterinarian);
                $this->ensureVeterinarianWorksIn($veterinarian, $branchId);
            }

            if ($serviceId !== null) {
                $this->ensureServiceIsAvailable($service);
            }

            $this->ensureNameIsFree($data['name'], $branchId);

            $schedule = Schedule::create($this->fields($data));

            if (array_key_exists('hours', $data)) {
                $this->replaceHours($schedule, $data['hours']);
            }

            return $schedule;
        });
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateScheduleRequest (semántica parcial)
     */
    public function update(Schedule $schedule, array $data): Schedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            if (array_key_exists('name', $data) || array_key_exists('branch_id', $data)) {
                CatalogLock::forCurrentClinic();
            }

            // Antes que la agenda (orden de bloqueo): los que llegan en el pedido.
            $sentVeterinarian = array_key_exists('veterinarian_id', $data)
                ? $this->sharedVeterinarian($this->id($data['veterinarian_id']))
                : null;
            $sentService = array_key_exists('service_id', $data)
                ? $this->sharedService($this->id($data['service_id']))
                : null;

            $schedule = Schedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            $branchId = array_key_exists('branch_id', $data) ? (int) $data['branch_id'] : $schedule->branch_id;
            $veterinarianId = array_key_exists('veterinarian_id', $data) ? $this->id($data['veterinarian_id']) : $schedule->veterinarian_id;
            $serviceId = array_key_exists('service_id', $data) ? $this->id($data['service_id']) : $schedule->service_id;

            $branchChanges = $branchId !== $schedule->branch_id;
            $veterinarianChanges = $veterinarianId !== $schedule->veterinarian_id;
            $serviceChanges = $serviceId !== $schedule->service_id;

            $this->ensureHasOwner($veterinarianId, $serviceId);

            // Pregunta 14: los turnos guardan la sucursal y el veterinario de la agenda.
            if (($branchChanges || $veterinarianChanges) && $this->hasAppointments($schedule)) {
                throw new ScheduleException(
                    'La agenda ya tiene turnos y no puede cambiar de sucursal ni de veterinario: creá otra agenda e inactivá esta.',
                    422,
                    'SCHEDULE_NOT_EDITABLE'
                );
            }

            if ($branchChanges) {
                $this->ensureBranchIsActive($branchId);
            }

            if ($veterinarianId !== null && ($veterinarianChanges || $branchChanges)) {
                $veterinarian = $sentVeterinarian ?? Veterinarian::query()->whereKey($veterinarianId)->sharedLock()->first();

                // El veterinario que la agenda ya tiene se acepta aunque se haya inactivado (el
                // formulario lo reenvía); uno nuevo, solo activo.
                if ($veterinarianChanges) {
                    $this->ensureVeterinarianIsAvailable($veterinarian);
                }

                $this->ensureVeterinarianWorksIn($veterinarian, $branchId);
            }

            if ($serviceId !== null && $serviceChanges) {
                $this->ensureServiceIsAvailable($sentService);
            }

            if ($branchChanges || (array_key_exists('name', $data) && CatalogName::clean($data['name']) !== $schedule->name)) {
                $this->ensureNameIsFree($data['name'] ?? $schedule->name, $branchId, $schedule);
            }

            $schedule->fill($this->fields($data));
            $schedule->save();

            if (array_key_exists('hours', $data)) {
                $this->replaceHours($schedule, $data['hours']);
            }

            return $schedule;
        });
    }

    /**
     * Baja física (pregunta 13), solo sin turnos (también cancelados): con turnos, 409
     * SCHEDULE_IN_USE (se inactiva). Los tramos y los bloqueos se eliminan modelo por modelo,
     * para la auditoría.
     */
    public function delete(Schedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $schedule = Schedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasAppointments($schedule)) {
                throw new ScheduleException('La agenda tiene turnos y no se puede eliminar: inactivala.', 409, 'SCHEDULE_IN_USE');
            }

            $schedule->hours()->orderBy('id')->get()->each->delete();
            $schedule->blocks()->orderBy('id')->get()->each->delete();
            $schedule->delete();
        });
    }

    /**
     * La agenda con lo que muestra el detalle: sus tramos (por día y hora) y los bloqueos que
     * terminan desde hoy (hasta 20).
     */
    public function loadDetail(Schedule $schedule): Schedule
    {
        return $schedule->load([
            'branch', 'veterinarian.user', 'service',
            'hours' => fn ($query) => $query->orderBy('weekday')->orderBy('starts_at')->orderBy('id'),
            'blocks' => fn ($query) => $query
                ->where('ends_at', '>=', now()->startOfDay()->format('Y-m-d H:i:s'))
                ->orderBy('starts_at')
                ->orderBy('id')
                ->limit(20)
                ->with('createdBy'),
        ]);
    }

    /**
     * Por el id, que es único en toda la base: no hace falta la clínica.
     */
    public function hasAppointments(Schedule $schedule): bool
    {
        return DB::table('appointments')->where('schedule_id', $schedule->getKey())->exists();
    }

    /**
     * Reemplaza el horario entero: borra los tramos que no están, crea los nuevos y deja los
     * que no cambian (cada uno con su auditoría). Los turnos que ya existen no se tocan.
     *
     * @param list<array{weekday: int|string, starts_at: string, ends_at: string}> $hours
     */
    private function replaceHours(Schedule $schedule, array $hours): void
    {
        $key = fn ($weekday, string $startsAt, string $endsAt) => (int) $weekday . '|'
            . ScheduleHour::clockTime(substr($startsAt, 0, 5)) . '|' . ScheduleHour::clockTime(substr($endsAt, 0, 5));

        $wanted = [];

        foreach ($hours as $hour) {
            $wanted[$key($hour['weekday'], $hour['starts_at'], $hour['ends_at'])] = $hour;
        }

        foreach ($schedule->hours()->orderBy('id')->get() as $existing) {
            $existingKey = $key($existing->weekday, $existing->starts_at, $existing->ends_at);

            if (array_key_exists($existingKey, $wanted)) {
                unset($wanted[$existingKey]);
            } else {
                $existing->delete();
            }
        }

        foreach ($wanted as $hour) {
            $schedule->hours()->create([
                'weekday' => (int) $hour['weekday'],
                'starts_at' => $hour['starts_at'],
                'ends_at' => $hour['ends_at'],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return Arr::only($data, self::FIELDS);
    }

    private function id(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * P10: al menos un veterinario o un servicio.
     */
    private function ensureHasOwner(?int $veterinarianId, ?int $serviceId): void
    {
        if ($veterinarianId === null && $serviceId === null) {
            throw ValidationException::withMessages(['veterinarian_id' => ScheduleMessages::OWNER_REQUIRED]);
        }
    }

    private function sharedVeterinarian(?int $id): ?Veterinarian
    {
        return $id === null ? null : Veterinarian::query()->whereKey($id)->sharedLock()->first();
    }

    private function sharedService(?int $id): ?Service
    {
        return $id === null ? null : Service::query()->whereKey($id)->sharedLock()->first();
    }

    /**
     * La sucursal activa de la clínica. La clínica está bloqueada en el alta y en el cambio
     * de sucursal, y la inactivación de una sucursal también la bloquea primero.
     */
    private function ensureBranchIsActive(int $branchId): void
    {
        if (! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => ScheduleMessages::BRANCH_NOT_AVAILABLE]);
        }
    }

    /**
     * La ficha activa (no eliminada: la consulta no trae las eliminadas) y su usuario activo
     * y de la clínica.
     */
    private function ensureVeterinarianIsAvailable(?Veterinarian $veterinarian): void
    {
        $available = $veterinarian !== null
            && $veterinarian->status === VeterinarianStatus::ACTIVE
            && User::query()->inClinic()->active()->whereKey($veterinarian->user_id)->exists();

        if (! $available) {
            throw ValidationException::withMessages(['veterinarian_id' => ScheduleMessages::VETERINARIAN_NOT_AVAILABLE]);
        }
    }

    /**
     * Su usuario tiene la sucursal de la agenda (branch_user; P5 de la FASE 2).
     */
    private function ensureVeterinarianWorksIn(?Veterinarian $veterinarian, int $branchId): void
    {
        if ($veterinarian === null) {
            throw ValidationException::withMessages(['veterinarian_id' => ScheduleMessages::VETERINARIAN_NOT_AVAILABLE]);
        }

        $works = DB::table('branch_user')
            ->where('user_id', $veterinarian->user_id)
            ->where('branch_id', $branchId)
            ->exists();

        if (! $works) {
            throw ValidationException::withMessages(['veterinarian_id' => ScheduleMessages::VETERINARIAN_NOT_IN_BRANCH]);
        }
    }

    private function ensureServiceIsAvailable(?Service $service): void
    {
        if ($service === null || $service->status !== ServiceStatus::ACTIVE) {
            throw ValidationException::withMessages(['service_id' => ScheduleMessages::SERVICE_NOT_AVAILABLE]);
        }
    }

    /**
     * Único dentro de la sucursal, sin mayúsculas, acentos ni espacios repetidos
     * (CatalogName). La clínica ya está bloqueada.
     */
    private function ensureNameIsFree(string $name, int $branchId, ?Schedule $ignore = null): void
    {
        $names = Schedule::query()
            ->where('branch_id', $branchId)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->pluck('name');

        if (CatalogName::isTaken($name, $names)) {
            throw ValidationException::withMessages(['name' => ScheduleMessages::NAME_TAKEN]);
        }
    }
}
