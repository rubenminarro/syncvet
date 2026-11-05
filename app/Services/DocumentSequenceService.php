<?php

namespace App\Services;

use App\Enums\DocumentSeries;
use App\Exceptions\AppException;
use App\Support\CurrentClinic;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Códigos internos por serie (BUD-000001…). El contador de la serie se bloquea
 * dentro de la transacción del alta: dos altas simultáneas no calculan el mismo
 * código, y si el alta falla el incremento se revierte. Puede haber huecos (no
 * son documentos fiscales). Orden de bloqueo: documento → serie (la serie,
 * siempre última).
 *
 * Cada clínica tiene su propia serie (document_sequences por clinic_id) y empieza
 * en 1. Usa el query builder, al que no llegan los scopes de Eloquent: filtra
 * clinic_id de forma explícita (D1). Sin clínica en contexto, falla.
 */
class DocumentSequenceService
{
    /**
     * Crea el documento con el próximo código de la serie. Debe llamarse dentro
     * de una transacción. Un código repetido igual (algo que lo cargó sin pasar
     * por la serie) responde 409 DOCUMENT_CODE_CONFLICT, nunca 500.
     *
     * @template T of Model
     * @param Closure(string): T $create
     * @return T
     */
    public function create(DocumentSeries $series, Closure $create): Model
    {
        $code = $this->next($series);

        try {
            return $create($code);
        } catch (UniqueConstraintViolationException $e) {
            if ($this->isCodeViolation($series, $e)) {
                throw new AppException(
                    "El código {$code} ya está en uso. Intentá de nuevo.",
                    409,
                    'DOCUMENT_CODE_CONFLICT'
                );
            }

            throw $e;
        }
    }

    /**
     * Próximo código libre de la serie. Salta los códigos ya usados (también los
     * de registros eliminados).
     */
    public function next(DocumentSeries $series): string
    {
        $clinicId = CurrentClinic::id();
        $sequence = $this->lockedSequence($series, $clinicId);
        $value = (int) $sequence->next_value;

        do {
            $code = $series->prefix() . str_pad((string) $value, 6, '0', STR_PAD_LEFT);
            $value++;
        } while (DB::table($series->table())->where('clinic_id', $clinicId)->where('code', $code)->exists());

        DB::table('document_sequences')
            ->where('id', $sequence->id)
            ->update(['next_value' => $value, 'updated_at' => now()]);

        return $code;
    }

    /**
     * Fila de la serie de la clínica, bloqueada. En el primer uso se crea en 1; los
     * códigos ya usados se saltan en next().
     */
    private function lockedSequence(DocumentSeries $series, int $clinicId): object
    {
        $query = fn () => DB::table('document_sequences')
            ->where('clinic_id', $clinicId)
            ->where('key', $series->value)
            ->lockForUpdate()
            ->first();

        $sequence = $query();

        if ($sequence === null) {
            DB::table('document_sequences')->insertOrIgnore([
                'clinic_id' => $clinicId,
                'key' => $series->value,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = $query();
        }

        return $sequence;
    }

    private function isCodeViolation(DocumentSeries $series, UniqueConstraintViolationException $e): bool
    {
        $table = $series->table();
        $message = $e->getMessage();

        // SQLite: "UNIQUE constraint failed: patients.clinic_id, patients.code";
        // MySQL: "Duplicate entry '…' for key 'patients.patients_clinic_id_code_unique'".
        return str_contains($message, "{$table}.code") || str_contains($message, "{$table}_clinic_id_code_unique");
    }
}
