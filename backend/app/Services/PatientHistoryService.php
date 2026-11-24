<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PatientApplication;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * El historial del paciente (FASE 4, entrega 4.6; pregunta 18): sus consultas y sus aplicaciones juntas,
 * de la más reciente a la más vieja, paginadas. También las anuladas (marcadas) y también de un paciente
 * fallecido. Es de toda la clínica (no de la sucursal actual) y solo lectura: no bloquea nada.
 *
 * La página se arma en la base con un UNION ALL de dos consultas livianas (tipo, id y fecha), cada una con
 * el scope de la clínica (toBase() aplica los scopes globales); después se cargan los modelos de la
 * página, con sus relaciones, en dos consultas más (sin N+1). Orden: la fecha (consulted_at o
 * applied_at) descendente, y en la misma fecha la consulta antes que la aplicación y el id descendente.
 */
class PatientHistoryService
{
    public const CONSULTATION = 'consultation';

    public const APPLICATION = 'application';

    public const CONSULTATION_RELATIONS = ['branch', 'patient', 'client', 'veterinarian.user'];

    public const APPLICATION_RELATIONS = ['consultation', 'veterinarian.user', 'createdBy', 'cancelledBy'];

    /**
     * @return LengthAwarePaginator<int, Consultation|PatientApplication> la página, con los modelos en orden
     */
    public function paginate(Patient $patient, int $perPage): LengthAwarePaginator
    {
        $consultations = Consultation::query()
            ->where('patient_id', $patient->getKey())
            ->selectRaw("'" . self::CONSULTATION . "' as kind, id, consulted_at as occurred_at")
            ->toBase();

        $applications = PatientApplication::query()
            ->where('patient_id', $patient->getKey())
            ->selectRaw("'" . self::APPLICATION . "' as kind, id, applied_at as occurred_at")
            ->toBase();

        $page = DB::query()
            ->fromSub($consultations->unionAll($applications), 'history')
            ->orderByDesc('occurred_at')
            // 'consultation' > 'application': en la misma fecha, la consulta primero.
            ->orderByDesc('kind')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = collect($page->items());
        $ids = fn (string $kind) => $rows->where('kind', $kind)->pluck('id')->all();

        $models = [
            self::CONSULTATION => Consultation::query()->with(self::CONSULTATION_RELATIONS)->whereKey($ids(self::CONSULTATION))->get()->keyBy('id'),
            self::APPLICATION => PatientApplication::query()->with(self::APPLICATION_RELATIONS)->whereKey($ids(self::APPLICATION))->get()->keyBy('id'),
        ];

        return $page->setCollection($rows->map(fn ($row) => $models[$row->kind][$row->id])->values());
    }
}
