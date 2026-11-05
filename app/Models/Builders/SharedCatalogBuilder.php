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
