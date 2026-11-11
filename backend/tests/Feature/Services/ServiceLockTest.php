<?php

namespace Tests\Feature\Services;

use Tests\Concerns\InspectsQueries;
use Tests\Concerns\InteractsWithServices;
use Tests\TestCase;

/**
 * Concurrencia de servicios (FASE 2, entrega 2.4). Orden de bloqueo: clínica → servicio →
 * serie. El alta y el cambio de nombre bloquean la clínica antes de leer los nombres; la
 * edición y la baja bloquean el servicio antes de revisar su uso; la serie va última.
 * Como UserLockTest, se verifica el orden de las consultas dentro de la transacción del
 * Service; el "for update" solo lo escribe MySQL.
 */
class ServiceLockTest extends TestCase
{
    use InspectsQueries;
    use InteractsWithServices;

    public function test_creating_locks_the_clinic_before_reading_the_names_and_the_series_last(): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $queries = $this->queriesOf(fn () => $this->postJson('/api/services', ['name' => 'Consulta', 'category' => 'consultation', 'standard_price' => 1])->assertCreated());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->namesRead(), 'Se leyeron los nombres antes de bloquear la clínica.');
        $this->assertBefore($queries, $this->namesRead(), $this->seriesLock(), 'La serie se bloqueó antes de revisar el nombre.');
    }

    public function test_renaming_locks_the_clinic_then_the_service_before_checking_the_usage_and_the_names(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic);
        $this->actAsServiceManager($clinic);

        $queries = $this->queriesOf(fn () => $this->patchJson("/api/services/{$service->id}", ['name' => 'Consulta clínica'])->assertOk());

        $this->assertBefore($queries, $this->lockOf('clinics'), $this->lockOf('services'), 'El servicio se bloqueó antes que la clínica.');
        $this->assertBefore($queries, $this->lockOf('services'), $this->readOf('schedules'), 'Se revisó el uso antes de bloquear el servicio.');
        $this->assertBefore($queries, $this->lockOf('services'), $this->namesRead(), 'Se leyeron los nombres antes de bloquear el servicio.');
    }

    public function test_deleting_locks_the_service_before_checking_the_usage(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic);
        $this->actAsServiceManager($clinic);

        $queries = $this->queriesOf(fn () => $this->deleteJson("/api/services/{$service->id}")->assertOk());

        $this->assertBefore($queries, $this->lockOf('services'), $this->readOf('schedules'), 'Se revisó el uso antes de bloquear el servicio.');
    }

    /**
     * La lectura de los nombres de los servicios de la clínica (CatalogName).
     */
    private function namesRead(): callable
    {
        return fn (string $sql) => preg_match('/^select [`"]name[`"] from [`"]services[`"]/', $sql) === 1;
    }

    private function seriesLock(): callable
    {
        return fn (string $sql) => str_starts_with($sql, 'select * from "document_sequences"') || str_starts_with($sql, 'select * from `document_sequences`');
    }
}
