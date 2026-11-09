<?php

namespace App\Services;

use App\Enums\DocumentSeries;
use App\Enums\UserRole;
use App\Enums\VeterinarianStatus;
use App\Exceptions\VeterinarianException;
use App\Http\Requests\VeterinarianMessages;
use App\Models\User;
use App\Models\Veterinarian;
use App\Support\DeletedSuffix;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Veterinarios (FASE 2, entrega 2.3): una ficha por usuario, con el rol veterinario
 * (P6), código VET- de la serie y sus sucursales, las de su usuario (P5). En uso se
 * inactiva; sin uso se da de baja, y un alta del mismo usuario restaura la ficha
 * eliminada con los datos del pedido y su mismo código (P6).
 *
 * Concurrencia. Orden de bloqueo: clínica → usuario → veterinario → serie. El alta y
 * la restauración bloquean la clínica (CatalogLock) y el usuario antes de revisar su
 * rol y su ficha (también eliminada); la serie va última. Quitar el rol veterinario
 * (UserService) bloquea la clínica y el mismo usuario: un alta y un cambio de roles
 * simultáneos se ordenan. La baja revisa el uso con la ficha bloqueada.
 */
class VeterinarianService
{
    /**
     * Referencias a una ficha que impiden darla de baja: cada clave foránea a
     * veterinarians, también de filas eliminadas (DB::table no aplica SoftDeletes).
     * VeterinarianDeleteTest exige que la lista cubra toda clave foránea a veterinarians.
     *
     * @var list<string> tablas con veterinarian_id
     */
    public const USAGE = [
        'schedules',
        'appointments',
        'consultations',
        'consultation_items',
        'patient_applications',
        'patient_documents',
    ];

    /**
     * Los datos de la ficha que llegan en el pedido.
     */
    private const FIELDS = ['license_number', 'specialty', 'commission_percentage', 'status', 'notes'];

    public function __construct(
        protected DocumentSequenceService $sequences
    ) {
    }

    /**
     * Alta, o restauración si el usuario tiene una ficha eliminada (P6): misma fila y
     * mismo código, con los datos del pedido (los que no llegan, con su valor por
     * defecto, como en un alta).
     *
     * @param array<string, mixed> $data los campos validados de StoreVeterinarianRequest
     */
    public function create(array $data): Veterinarian
    {
        return DB::transaction(function () use ($data) {
            CatalogLock::forCurrentClinic();
            $user = $this->lockedUser((int) $data['user_id']);

            $existing = Veterinarian::withTrashed()->where('user_id', $user->getKey())->lockForUpdate()->first();

            if ($existing !== null && ! $existing->trashed()) {
                throw ValidationException::withMessages(['user_id' => VeterinarianMessages::USER_ALREADY_VETERINARIAN]);
            }

            $attributes = [
                'license_number' => null,
                'specialty' => null,
                'commission_percentage' => '0.00',
                'status' => VeterinarianStatus::ACTIVE,
                'notes' => null,
                ...$this->fields($data),
            ];

            if ($existing !== null) {
                $existing->fill($attributes);

                return $this->withUniqueLicense(function () use ($existing) {
                    $existing->restore();

                    return $existing;
                });
            }

            return $this->sequences->create(
                DocumentSeries::VETERINARIAN,
                fn (string $code) => $this->withUniqueLicense(fn () => Veterinarian::create([
                    ...$attributes,
                    'user_id' => $user->getKey(),
                    'code' => $code,
                ]))
            );
        });
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateVeterinarianRequest (semántica parcial)
     */
    public function update(Veterinarian $veterinarian, array $data): Veterinarian
    {
        return DB::transaction(function () use ($veterinarian, $data) {
            $veterinarian = Veterinarian::query()->whereKey($veterinarian->getKey())->lockForUpdate()->firstOrFail();
            $veterinarian->fill($this->fields($data));

            return $this->withUniqueLicense(function () use ($veterinarian) {
                $veterinarian->save();

                return $veterinarian;
            });
        });
    }

    /**
     * Baja sin uso (P6): con uso → 409 VETERINARIAN_IN_USE (se inactiva). Al eliminarla,
     * la matrícula lleva el sufijo de baja y queda libre.
     */
    public function delete(Veterinarian $veterinarian): void
    {
        DB::transaction(function () use ($veterinarian) {
            $veterinarian = Veterinarian::query()->whereKey($veterinarian->getKey())->lockForUpdate()->firstOrFail();

            if ($this->isInUse($veterinarian)) {
                throw new VeterinarianException(
                    'El veterinario tiene registros y no se puede eliminar: inactivalo.',
                    409,
                    'VETERINARIAN_IN_USE'
                );
            }

            if ($veterinarian->license_number !== null) {
                $veterinarian->license_number .= DeletedSuffix::for($veterinarian);
                $veterinarian->save();
            }

            $veterinarian->delete();
        });
    }

    /**
     * Los datos de la ficha que llegan, con la matrícula normalizada como la compara la
     * validación (mayúsculas, sin espacios en los extremos): así se guarda aunque el
     * Service se llame sin el Form Request.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $fields = Arr::only($data, self::FIELDS);

        if (array_key_exists('license_number', $fields)) {
            $fields['license_number'] = VeterinarianMessages::normalizeLicense($fields['license_number']);
        }

        return $fields;
    }

    /**
     * El usuario del alta, bloqueado: activo, de la clínica y con el rol veterinario. La
     * validación ya lo revisó; aquí se vuelve a leer con la fila bloqueada, porque un
     * cambio de roles o una inactivación simultáneos pudieron cambiarlo.
     */
    private function lockedUser(int $id): User
    {
        $user = User::query()->inClinic()->active()->whereKey($id)->lockForUpdate()->first();

        if ($user === null) {
            throw ValidationException::withMessages(['user_id' => VeterinarianMessages::USER_NOT_AVAILABLE]);
        }

        if (! $user->hasRole(UserRole::VETERINARIAN->value, 'api')) {
            throw ValidationException::withMessages(['user_id' => VeterinarianMessages::USER_WITHOUT_ROLE]);
        }

        return $user;
    }

    /**
     * Por el id, que es único en toda la base: no hace falta la clínica.
     */
    private function isInUse(Veterinarian $veterinarian): bool
    {
        foreach (self::USAGE as $table) {
            if (DB::table($table)->where('veterinarian_id', $veterinarian->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guarda la ficha. Dos pedidos simultáneos con la misma matrícula pasan la
     * validación: el índice único frena al segundo, que recibe el mismo 422 en lugar de
     * un 500. Otra violación (el código, en el alta) sigue su camino.
     *
     * @param Closure(): Veterinarian $save
     */
    private function withUniqueLicense(Closure $save): Veterinarian
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'license_number')) {
                throw ValidationException::withMessages(['license_number' => VeterinarianMessages::LICENSE_TAKEN]);
            }

            throw $e;
        }
    }
}
