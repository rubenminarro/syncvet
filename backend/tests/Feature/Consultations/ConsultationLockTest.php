<?php

namespace Tests\Feature\Consultations;

use App\Services\AppointmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithConsultations;
use Tests\TestCase;

/**
 * El orden de bloqueo de la consulta (FASE 4, entrega 4.1; §3: veterinario → paciente → cliente →
 * turno → consulta → serie): abrir desde un turno toma el veterinario, el paciente y el cliente
 * antes que el turno, y el turno antes que la serie de códigos; editar toma la consulta antes de
 * leer su estado. Un deadlock se repite hasta 3 veces y una espera vencida no se repite: 409
 * CONFLICT, nunca 500. En MySQL se ve el "for update" o "for share"/"lock in share mode".
 */
class ConsultationLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithConsultations;

    private const DEADLOCK = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';

    private const LOCK_WAIT_TIMEOUT = 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpClinic();
    }

    /**
     * Una lectura de la tabla por su id; en MySQL, con su bloqueo (for update o compartido).
     */
    private function lockedRead(string $table, bool $exclusive): callable
    {
        return function (string $sql) use ($table, $exclusive) {
            // El bloqueo exclusivo lee la fila entera (select *); las lecturas sin bloqueo y los
            // compartidos, solo sus columnas: en SQLite, sin "for update", así se distinguen.
            $columns = $exclusive ? '\*' : '[^*]+';

            if (! preg_match('/^select ' . $columns . ' from [`"]' . $table . '[`"] where .*[`"]' . $table . '[`"]\.[`"]id[`"] = \?/', $sql)) {
                return false;
            }

            if (DB::getDriverName() !== 'mysql') {
                return true;
            }

            return $exclusive
                ? str_ends_with($sql, 'for update')
                : str_ends_with($sql, 'lock in share mode') || str_ends_with($sql, 'for share');
        };
    }

    private function sequence(): callable
    {
        return fn (string $sql) => str_contains($sql, 'document_sequences');
    }

    public function test_opening_from_an_appointment_locks_in_order(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();

        $queries = $this->queriesOf(fn () => $this->openConsultation(['appointment_id' => $appointment])->assertCreated());

        $this->assertBefore($queries, $this->lockedRead('veterinarians', false), $this->lockedRead('patients', false), 'El veterinario va antes que el paciente.');
        $this->assertBefore($queries, $this->lockedRead('patients', false), $this->lockedRead('clients', false), 'El paciente va antes que el cliente.');
        $this->assertBefore($queries, $this->lockedRead('clients', false), $this->lockedRead('appointments', true), 'El cliente va antes que el turno.');
        $this->assertBefore(
            $queries,
            $this->lockedRead('appointments', true),
            fn (string $sql) => str_contains($sql, 'from "consultations"') || str_contains($sql, 'from `consultations`'),
            'El turno se bloquea antes de revisar si ya tiene consulta.',
        );
        $this->assertBefore($queries, $this->lockedRead('appointments', true), $this->sequence(), 'La serie, al final.');
        $this->assertNull($this->firstIndex($queries, $this->lockedRead('schedules', true)), 'Abrir no bloquea la agenda.');
    }

    public function test_opening_without_appointment_locks_patient_and_client_before_the_series(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $this->actAsVet();

        $queries = $this->queriesOf(fn () => $this->openConsultation(['patient_id' => $patient->id])->assertCreated());

        $this->assertBefore($queries, $this->lockedRead('veterinarians', false), $this->lockedRead('patients', false), 'El veterinario va antes que el paciente.');
        $this->assertBefore($queries, $this->lockedRead('patients', false), $this->lockedRead('clients', false), 'El paciente va antes que el cliente.');
        $this->assertBefore($queries, $this->lockedRead('clients', false), $this->sequence(), 'La serie, al final.');
    }

    public function test_editing_locks_the_consultation_before_reading_its_status(): void
    {
        $this->actAsVet();
        $id = $this->opened(['appointment_id' => $this->checkedIn()]);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/consultations/{$id}", ['diagnosis' => 'Otitis'])->assertOk());

        $this->assertTrue(($this->lockedRead('consultations', true))($queries[0] ?? ''), "La primera consulta no es el bloqueo de la consulta:\n" . implode("\n", $queries));
    }

    /**
     * Entrega 4.2: cerrar toma el paciente (copia el peso) antes que la consulta (§3, paciente →
     * consulta); enmendar, solo la consulta; anular, la consulta antes que sus ítems (consulta → ítem).
     */
    public function test_closing_amending_and_cancelling_lock_in_order(): void
    {
        $this->actAsVet();
        $id = $this->opened(['appointment_id' => $this->checkedIn(), 'diagnosis' => 'Otitis', 'weight_kg' => 9]);
        DB::table('consultation_items')->insert([
            'clinic_id' => $this->clinic->id, 'consultation_id' => $id, 'type' => 'service', 'description' => 'Consulta',
            'quantity' => '1.000', 'unit_price' => '1.00', 'discount' => '0.00', 'total' => '1.00', 'tax_rate' => '10',
            'billing_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $queries = $this->queriesOf(fn () => $this->postJson("/api/consultations/{$id}/close")->assertOk());
        $this->assertBefore($queries, $this->lockedRead('patients', true), $this->lockedRead('consultations', true), 'Cerrar: el paciente va antes que la consulta.');

        // Enmendar: solo la consulta, antes de leer su estado (sin el paciente).
        $queries = $this->queriesOf(fn () => $this->postJson("/api/consultations/{$id}/amendments", ['content' => 'Corrección', 'reason' => 'Error'])->assertCreated());
        $this->assertTrue(($this->lockedRead('consultations', true))($queries[0] ?? ''), "Enmendar: la primera consulta no es el bloqueo de la consulta:\n" . implode("\n", $queries));
        $this->assertNull($this->firstIndex($queries, $this->lockedRead('patients', true)), 'Enmendar no bloquea el paciente.');

        $this->actAsAdmin();
        $queries = $this->queriesOf(fn () => $this->postJson("/api/consultations/{$id}/cancel", ['cancellation_reason' => 'Error'])->assertOk());
        $this->assertTrue(($this->lockedRead('consultations', true))($queries[0] ?? ''), "Anular: la primera consulta no es el bloqueo de la consulta:\n" . implode("\n", $queries));
        $this->assertBefore(
            $queries,
            $this->lockedRead('consultations', true),
            fn (string $sql) => preg_match('/^select \* from [`"]consultation_items[`"] where/', $sql) === 1
                && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update')),
            'Anular: la consulta va antes que sus ítems.',
        );
    }

    /**
     * Entrega 4.3 (§3: veterinario → servicio → consulta → ítem): sumar un servicio toma el veterinario de
     * los honorarios y el servicio con sharedLock antes que la consulta; editar y quitar, la consulta antes
     * que el ítem.
     */
    public function test_items_lock_veterinarian_and_service_before_the_consultation_and_it_before_the_item(): void
    {
        $service = \App\Support\CurrentClinic::run($this->clinic, fn () => \App\Models\Service::create(['code' => 'SRV-000001', 'name' => 'Consulta general', 'standard_price' => '150000']));
        $this->actAsVet();
        $id = $this->opened(['appointment_id' => $this->checkedIn()]);

        $queries = $this->queriesOf(fn () => $this->postJson("/api/consultations/{$id}/items", [
            'service_id' => $service->id, 'quantity' => 1, 'veterinarian_id' => $this->vet->id,
        ])->assertCreated());
        $this->assertBefore($queries, $this->lockedRead('veterinarians', false), $this->lockedRead('services', false), 'El veterinario va antes que el servicio.');
        $this->assertBefore($queries, $this->lockedRead('services', false), $this->lockedRead('consultations', true), 'El servicio va antes que la consulta.');

        // Sin veterinarian_id (revisión de la 4.3): el de la consulta, también antes que el servicio.
        $queries = $this->queriesOf(fn () => $this->postJson("/api/consultations/{$id}/items", ['service_id' => $service->id, 'quantity' => 1])->assertCreated());
        $this->assertBefore($queries, $this->lockedRead('veterinarians', false), $this->lockedRead('services', false), 'Sin veterinario: el de la consulta va antes que el servicio.');

        $item = (int) DB::table('consultation_items')->value('id');
        $isItemLock = fn (string $sql) => preg_match('/^select \* from [`"]consultation_items[`"] where .*[`"]consultation_items[`"]\.[`"]id[`"] = \?/', $sql) === 1
            && (DB::getDriverName() !== 'mysql' || str_ends_with($sql, 'for update'));

        foreach (['patch' => ['quantity' => 2], 'delete' => []] as $method => $body) {
            $queries = $this->queriesOf(fn () => $this->json($method, "/api/consultation-items/{$item}", $body)->assertOk());
            $this->assertBefore($queries, $this->lockedRead('consultations', true), $isItemLock, "{$method}: la consulta va antes que el ítem.");
        }
    }

    public function test_a_deadlock_is_retried_and_a_lock_wait_timeout_is_not(): void
    {
        $appointment = $this->checkedIn();
        $this->actAsVet();

        $failures = $this->failOn($this->lockedRead('appointments', true), self::DEADLOCK);
        $response = $this->openConsultation(['appointment_id' => $appointment]);
        $this->assertErrorCode($response, 409, 'CONFLICT');
        $response->assertJsonPath('message', 'La consulta cambió mientras se guardaba: volvé a intentarlo.');
        $this->assertSame(AppointmentService::ATTEMPTS, $failures->count);

        $failures->message = self::LOCK_WAIT_TIMEOUT;
        $failures->count = 0;
        $this->assertErrorCode($this->openConsultation(['appointment_id' => $appointment]), 409, 'CONFLICT');
        $this->assertSame(1, $failures->count);

        $this->assertSame('checked_in', $this->appointmentRow($appointment)->status);
        $this->assertSame(0, DB::table('consultations')->count());
    }

    /**
     * Si el turno cambia de paciente entre la lectura y el bloqueo, lo bloqueado ya no corresponde:
     * se deshace y se repite (el cambio, hecho dentro de la misma transacción, se deshace con ella).
     */
    public function test_an_appointment_that_changed_while_locking_is_retried(): void
    {
        $appointment = $this->checkedIn();
        $patientId = $this->patientIdOf($appointment);
        $newPatient = $this->patientOf($this->clientIn($this->clinic));
        $this->actAsVet();
        $changed = false;
        $appointmentLocks = 0;

        DB::listen(function ($query) use (&$changed, &$appointmentLocks, $appointment, $newPatient) {
            $sql = strtolower($query->sql);

            if (($this->lockedRead('appointments', true))($sql)) {
                $appointmentLocks++;
            }

            if (! $changed && ($this->lockedRead('patients', false))($sql)) {
                $changed = true;
                DB::table('appointments')->where('id', $appointment)->update(['patient_id' => $newPatient->id]);
            }
        });

        $this->openConsultation(['appointment_id' => $appointment])->assertCreated()->assertJsonPath('data.patient.id', $patientId);
        $this->assertSame(2, $appointmentLocks, 'No se repitió después del cambio.');
    }

    private function failOn(callable $matches, string $message): object
    {
        $failures = (object) ['count' => 0, 'message' => $message];

        DB::listen(function ($query) use ($failures, $matches) {
            if (! $matches(strtolower($query->sql))) {
                return;
            }

            $failures->count++;

            throw new QueryException($query->connectionName, $query->sql, $query->bindings, new PDOException($failures->message));
        });

        return $failures;
    }
}
