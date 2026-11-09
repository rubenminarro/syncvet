<?php

namespace Tests\Feature\Database;

use App\Models\Patient;
use App\Support\ApiDateTime;
use App\Support\CurrentClinic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Fechas con hora (FASE 2, entrega 2.6; deuda 13): el cast AppDateTime guarda un valor
 * con zona ("Z" o un desplazamiento) en la hora de Asunción, y uno sin zona como hora
 * de Asunción (pregunta 28). La salida sigue en UTC ("Z"). Corre en SQLite y en MySQL.
 * deceased_at, el único que hoy recibe la API, sigue igual de punta a punta.
 */
class AppDateTimeTest extends TestCase
{
    use InteractsWithAuth;

    /**
     * Noviembre: Asunción en UTC-3.
     */
    public static function values(): array
    {
        return [
            'con Z' => ['2025-11-05T13:00:00Z', '2025-11-05 10:00:00'],
            'con Z y fracción' => ['2025-11-05T13:00:00.000000Z', '2025-11-05 10:00:00'],
            'con -03:00' => ['2025-11-05T10:00:00-03:00', '2025-11-05 10:00:00'],
            'con +02:00' => ['2025-11-05T15:00:00+02:00', '2025-11-05 10:00:00'],
            'sin zona: hora de Asunción' => ['2025-11-05T10:00:00', '2025-11-05 10:00:00'],
            'sin zona, con espacio' => ['2025-11-05 10:00:00', '2025-11-05 10:00:00'],
            'cambia de día en Asunción' => ['2025-11-05T02:00:00Z', '2025-11-04 23:00:00'],
        ];
    }

    #[DataProvider('values')]
    public function test_the_cast_stores_the_time_of_asuncion(string $value, string $stored): void
    {
        $patient = $this->patient();

        CurrentClinic::run($patient->clinic, function () use ($patient, $value) {
            $patient->deceased_at = $value;
            $patient->save();
        });

        $this->assertSame($stored, $this->rawDeceasedAt($patient));

        $fresh = CurrentClinic::run($patient->clinic, fn () => Patient::findOrFail($patient->id));
        $this->assertSame(config('app.timezone'), $fresh->deceased_at->getTimezone()->getName());
        $this->assertSame($stored, $fresh->deceased_at->format('Y-m-d H:i:s'));
        $this->assertSame(Carbon::parse($stored, config('app.timezone'))->utc()->toISOString(), $fresh->deceased_at->toISOString());
    }

    public function test_a_carbon_in_utc_or_in_asuncion_and_null(): void
    {
        $patient = $this->patient();

        CurrentClinic::run($patient->clinic, function () use ($patient) {
            $patient->deceased_at = Carbon::parse('2025-11-05 13:00:00', 'UTC');
            $patient->save();
        });
        $this->assertSame('2025-11-05 10:00:00', $this->rawDeceasedAt($patient));

        CurrentClinic::run($patient->clinic, function () use ($patient) {
            $patient->deceased_at = Carbon::parse('2025-11-05 11:00:00', config('app.timezone'));
            $patient->save();
        });
        $this->assertSame('2025-11-05 11:00:00', $this->rawDeceasedAt($patient));

        CurrentClinic::run($patient->clinic, function () use ($patient) {
            $patient->deceased_at = null;
            $patient->save();
        });
        $this->assertNull($this->rawDeceasedAt($patient));
    }

    /**
     * Guardar la misma hora con otra zona no es un cambio (la auditoría no lo registra).
     */
    public function test_the_same_moment_in_another_zone_is_not_a_change(): void
    {
        $patient = $this->patient();

        CurrentClinic::run($patient->clinic, function () use ($patient) {
            $patient->deceased_at = '2025-11-05T13:00:00Z';
            $patient->save();
            $patient->deceased_at = '2025-11-05T10:00:00-03:00';

            $this->assertFalse($patient->isDirty('deceased_at'));
        });
    }

    /**
     * deceased_at de punta a punta, como en la FASE 1: llega en UTC, se guarda en la hora de
     * Asunción y sale en UTC.
     */
    public function test_deceased_at_end_to_end(): void
    {
        $patient = $this->patient();
        Sanctum::actingAs($this->userWithRole($patient->clinic, 'veterinario'));

        $this->patchJson("/api/patients/{$patient->id}", ['deceased_at' => '2025-11-05T02:00:00Z'])
            ->assertOk()
            ->assertJsonPath('data.deceased_at', '2025-11-05T02:00:00.000000Z');

        $this->assertSame('2025-11-04 23:00:00', $this->rawDeceasedAt($patient));
        $this->getJson("/api/patients/{$patient->id}")->assertOk()->assertJsonPath('data.deceased_at', '2025-11-05T02:00:00.000000Z');
    }

    public function test_api_date_time_start_and_end_of_a_filter(): void
    {
        $this->assertSame('2026-11-09 00:00:00', ApiDateTime::startOf('2026-11-09')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 23:59:59', ApiDateTime::endOf('2026-11-09')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 08:00:00', ApiDateTime::startOf('2026-11-09T11:00:00Z')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 11:00:00', ApiDateTime::endOf('2026-11-09T11:00:00')->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), ApiDateTime::toLocal('2026-11-09T11:00:00Z')->getTimezone()->getName());
    }

    private function patient(): Patient
    {
        $clinic = $this->createClinic();

        return $this->patientOf($this->clientIn($clinic), ['birth_date' => '2020-01-01']);
    }

    private function rawDeceasedAt(Patient $patient): ?string
    {
        $value = DB::table('patients')->where('id', $patient->id)->value('deceased_at');

        // MySQL y SQLite devuelven "Y-m-d H:i:s"; por si un motor suma la fracción.
        return $value === null ? null : substr((string) $value, 0, 19);
    }
}
