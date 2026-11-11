<?php

namespace App\Models\Builders;

use App\Exceptions\ClinicContextMissingException;
use App\Support\CurrentClinic;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builder de los catálogos compartidos (especies y razas). SharedCatalogScope
 * muestra los globales y los de la clínica para LEER; las escrituras masivas
 * (update, delete y afines) no disparan los eventos del modelo que protegen a los
 * globales, así que aquí se limitan a los registros de la clínica en contexto.
 * Un global nunca se modifica ni se elimina desde una clínica, y clinic_id no se
 * cambia (un registro propio que pasa a global lo vería toda clínica).
 *
 * Sin clínica en contexto, falla (ClinicContextMissingException).
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class SharedCatalogBuilder extends Builder
{
    public function update(array $values)
    {
        $this->ensureClinicIdUntouched(array_keys($values));
        $this->onlyOwnRecords();

        return parent::update($values);
    }

    public function touch($column = null)
    {
        $this->onlyOwnRecords();

        return parent::touch($column);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->ensureClinicIdUntouched([$column, ...array_keys($extra)]);
        $this->onlyOwnRecords();

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->ensureClinicIdUntouched([$column, ...array_keys($extra)]);
        $this->onlyOwnRecords();

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->ensureClinicIdUntouched([...array_keys($columns), ...array_keys($extra)]);
        $this->onlyOwnRecords();

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->ensureClinicIdUntouched([...array_keys($columns), ...array_keys($extra)]);
        $this->onlyOwnRecords();

        return parent::decrementEach($columns, $extra);
    }

    public function delete()
    {
        $this->onlyOwnRecords();

        return parent::delete();
    }

    /**
     * El forceDelete de Eloquent borra sobre la consulta base, sin los scopes: el
     * filtro de la clínica va igual, porque se agrega a esa consulta.
     */
    public function forceDelete()
    {
        $this->onlyOwnRecords();

        return parent::forceDelete();
    }


    /**
     * Altas masivas (FASE 1, entrega 1.4): no pasan por el evento creating del
     * modelo, así que cada fila recibe aquí la clínica en contexto. Una fila con
     * otra clínica, o con clinic_id nulo de forma explícita (un global), falla.
     * Cubre también fillAndInsert, fillAndInsertOrIgnore y fillAndInsertGetId,
     * que terminan en estos métodos. Sin filas, insert e insertOrIgnore no hacen
     * nada, como en Laravel (revisión de la entrega 1.4): la clínica no se agrega a
     * una fila que no existe.
     */
    public function insert(array $values)
    {
        if ($values === []) {
            return $this->toBase()->insert([]);
        }

        return $this->toBase()->insert($this->withCurrentClinic($values));
    }

    public function insertOrIgnore(array $values)
    {
        if ($values === []) {
            return $this->toBase()->insertOrIgnore([]);
        }

        return $this->toBase()->insertOrIgnore($this->withCurrentClinic($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return $this->toBase()->insertGetId($this->withCurrentClinic($values), $sequence);
    }

    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null)
    {
        return $this->toBase()->insertOrIgnoreReturning($this->withCurrentClinic($values), $returning, $uniqueBy);
    }

    /**
     * Como insert, y además: las columnas a actualizar no incluyen clinic_id, y
     * ni las filas ni la clave de unicidad llevan el id. Un upsert por id
     * actualizaría la fila con ese id aunque fuera un global o de otra clínica
     * (ON DUPLICATE KEY UPDATE no admite un filtro por clínica).
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if ($values === []) {
            return 0;
        }

        $values = $this->withCurrentClinic(is_array(array_first($values)) ? $values : [$values]);
        // Sin columnas a actualizar, Laravel actualiza todas las de la fila: todas menos clinic_id.
        $update ??= array_values(array_diff(array_keys(array_first($values)), ['clinic_id']));
        $key = $this->model->getKeyName();

        $this->ensureClinicIdUntouched(array_map(
            fn ($column, $value) => is_string($column) ? $column : $value,
            array_keys($update),
            $update,
        ));

        $keyColumns = [...array_keys(array_first($values)), ...(array) $uniqueBy];

        if (in_array($key, $keyColumns, true) || in_array($this->model->qualifyColumn($key), $keyColumns, true)) {
            throw new ClinicContextMissingException(
                'Un upsert masivo de ' . class_basename($this->model) . ' no puede usar el id: alcanzaría registros de otra clínica o globales.'
            );
        }

        return parent::upsert($values, $uniqueBy, $update);
    }

    /**
     * Un alta desde una consulta no trae filas que revisar: se rechaza siempre.
     */
    public function insertUsing(array $columns, $query)
    {
        $this->rejectInsertFromQuery();
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        $this->rejectInsertFromQuery();
    }

    /**
     * Cada fila con clinic_id = clínica en contexto (sin clínica, falla).
     *
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    protected function withCurrentClinic(array $values): array
    {
        $clinicId = CurrentClinic::id();
        $single = ! is_array(array_first($values));
        $rows = $single ? [$values] : $values;

        foreach ($rows as $index => $row) {
            if (array_key_exists('clinic_id', $row) && (string) $row['clinic_id'] !== (string) $clinicId) {
                throw new ClinicContextMissingException(
                    'Se intentó crear de forma masiva un registro de ' . class_basename($this->model) . ' global o de otra clínica.'
                );
            }

            $rows[$index] = [...$row, 'clinic_id' => $clinicId];
        }

        return $single ? $rows[0] : $rows;
    }

    protected function rejectInsertFromQuery(): never
    {
        throw new ClinicContextMissingException(
            'Un alta masiva de ' . class_basename($this->model) . ' desde una consulta no puede fijar la clínica de cada fila.'
        );
    }

    /**
     * "clinic_id = clínica en contexto" en la consulta base (llega también a
     * forceDelete). Deja afuera a los globales (clinic_id nulo).
     */
    protected function onlyOwnRecords(): void
    {
        $this->query->where($this->model->qualifyColumn('clinic_id'), CurrentClinic::id());
    }

    /**
     * Columnas que se escriben; una Expression no es un nombre y se ignora.
     *
     * @param array<int, mixed> $columns
     */
    protected function ensureClinicIdUntouched(array $columns): void
    {
        foreach ($columns as $column) {
            if (is_string($column) && ($column === 'clinic_id' || str_ends_with($column, '.clinic_id'))) {
                throw new ClinicContextMissingException(
                    'Se intentó cambiar la clínica de registros de ' . class_basename($this->model) . ' con una actualización masiva.'
                );
            }
        }
    }
}
