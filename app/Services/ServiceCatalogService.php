<?php

namespace App\Services;

use App\Enums\DocumentSeries;
use App\Exceptions\ServiceException;
use App\Http\Requests\ServiceMessages;
use App\Models\Service;
use App\Rules\WholeAmount;
use App\Support\CatalogName;
use App\Support\DeletedSuffix;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de servicios (FASE 2, entrega 2.4; P8): código SRV- de la serie, nombre único
 * en la clínica sin mayúsculas, acentos ni espacios repetidos (CatalogName) y precio
 * único sin decimales (pregunta 21). Un servicio usado no cambia de nombre (422
 * SERVICE_NAME_NOT_EDITABLE) ni se elimina (409 SERVICE_IN_USE: se inactiva); su precio
 * sí cambia, porque lo usado guarda el suyo.
 *
 * Concurrencia. Orden de bloqueo: clínica → servicio → serie. El alta y el cambio de
 * nombre bloquean la clínica (CatalogLock) antes de leer los nombres; la edición y la
 * baja bloquean el servicio (lockForUpdate) antes de revisar su uso; la serie va última.
 * Las fases 3 y 4 tomarán un sharedLock sobre el servicio al usarlo, como el paciente
 * con la especie.
 */
class ServiceCatalogService
{
    /**
     * Referencias a un servicio que lo dan por usado: cada clave foránea a services,
     * también de filas eliminadas (DB::table no aplica SoftDeletes). ServiceDeleteTest
     * exige que la lista cubra toda clave foránea a services.
     *
     * @var list<string> tablas con service_id
     */
    public const USAGE = [
        'schedules',
        'appointments',
        'consultation_items',
    ];

    private const FIELDS = ['name', 'category', 'description', 'duration_minutes', 'standard_price', 'tax_rate', 'status'];

    public function __construct(
        protected DocumentSequenceService $sequences
    ) {
    }

    /**
     * @param array<string, mixed> $data los campos validados de StoreServiceRequest
     */
    public function create(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            CatalogLock::forCurrentClinic();
            $this->ensureNameIsFree($data['name']);

            return $this->sequences->create(
                DocumentSeries::SERVICE,
                fn (string $code) => $this->withUniqueName(fn () => Service::create([
                    ...$this->fields($data),
                    'code' => $code,
                ]))
            );
        });
    }

    /**
     * @param array<string, mixed> $data los campos validados de UpdateServiceRequest (semántica parcial)
     */
    public function update(Service $service, array $data): Service
    {
        return DB::transaction(function () use ($service, $data) {
            $renames = array_key_exists('name', $data);

            if ($renames) {
                CatalogLock::forCurrentClinic();
            }

            $service = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();

            // Reenviar el mismo nombre (el formulario manda todos los campos) no es un cambio.
            if ($renames && CatalogName::clean($data['name']) !== $service->name) {
                $this->ensureNameIsEditable($service);
                $this->ensureNameIsFree($data['name'], $service);
            }

            $service->fill($this->fields($data));

            return $this->withUniqueName(function () use ($service) {
                $service->save();

                return $service;
            });
        });
    }

    /**
     * Baja sin uso: con uso → 409 SERVICE_IN_USE (se inactiva). Al eliminarlo, el nombre
     * lleva el sufijo de baja y queda libre.
     */
    public function delete(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $service = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();

            if ($this->isInUse($service)) {
                throw new ServiceException('El servicio ya se usó y no se puede eliminar: inactivalo.', 409, 'SERVICE_IN_USE');
            }

            $service->name .= DeletedSuffix::for($service);
            $service->save();
            $service->delete();
        });
    }

    /**
     * Por el id, que es único en toda la base: no hace falta la clínica.
     */
    public function isInUse(Service $service): bool
    {
        foreach (self::USAGE as $table) {
            if (DB::table($table)->where('service_id', $service->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los datos del servicio que llegan, con el precio como lo guarda la columna (BCMath,
     * sin float): "007" → "7.00". La auditoría lo registra igual que la base.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $fields = Arr::only($data, self::FIELDS);

        if (array_key_exists('standard_price', $fields)) {
            $fields['standard_price'] = bcadd((string) WholeAmount::normalize($fields['standard_price']), '0', 2);
        }

        return $fields;
    }

    private function ensureNameIsEditable(Service $service): void
    {
        if ($this->isInUse($service)) {
            throw new ServiceException(
                'El servicio ya se usó y no puede cambiar de nombre: creá otro e inactivá este.',
                422,
                'SERVICE_NAME_NOT_EDITABLE'
            );
        }
    }

    /**
     * Único en la clínica frente a los no eliminados (los eliminados llevan el sufijo de
     * baja). La clínica ya está bloqueada.
     */
    private function ensureNameIsFree(string $name, ?Service $ignore = null): void
    {
        $names = Service::query()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->pluck('name');

        if (CatalogName::isTaken($name, $names)) {
            throw ValidationException::withMessages(['name' => ServiceMessages::NAME_TAKEN]);
        }
    }

    /**
     * El índice único (clinic_id, name) es el respaldo: si lo frena, el mismo 422 en
     * lugar de un 500. Otra violación (el código, en el alta) sigue su camino.
     *
     * @param Closure(): Service $save
     */
    private function withUniqueName(Closure $save): Service
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'name')) {
                throw ValidationException::withMessages(['name' => ServiceMessages::NAME_TAKEN]);
            }

            throw $e;
        }
    }
}
