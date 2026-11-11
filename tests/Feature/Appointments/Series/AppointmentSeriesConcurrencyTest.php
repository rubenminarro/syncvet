<?php

namespace Tests\Feature\Appointments\Series;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAppointments;
use Tests\Concerns\RunsConcurrentRequests;
use Tests\TestCase;

/**
 * Dos series simultáneas que comparten una fecha (FASE 3, entrega 3.3; solo MySQL): una entra
 * entera y la otra se rechaza entera (409 APPOINTMENT_SERIES_CONFLICT, con la fecha compartida en
 * la lista), sin ningún turno suyo.
 *
 * Como en AppointmentConcurrencyTest: dos procesos de PHP aparte piden las series por la API
 * mientras una tercera conexión del test tiene tomada la fila del veterinario (FOR UPDATE); los dos
 * esperan ese bloqueo y, al soltarlo, la primera serie bloquea el veterinario y la agenda una vez,
 * revisa todas sus fechas, inserta y confirma; la segunda lee después la fecha ocupada.
 */
class AppointmentSeriesConcurrencyTest extends TestCase
{
    use InteractsWithAppointments;
    use RunsConcurrentRequests;

    /**
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    private ?int $clinicId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Solo en MySQL: dos procesos con sus propias conexiones.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->clinicId !== null) {
            $this->deleteClinicData($this->clinicId);
        }

        parent::tearDown();
    }

    public function test_two_simultaneous_series_sharing_a_date_one_enters_whole_and_the_other_is_rejected_whole(): void
    {
        $clinic = $this->createClinic();
        $this->clinicId = $clinic->id;
        $schedule = $this->openSchedule($this->defaultBranchIn($clinic));

        // Reloj real: los procesos aparte no ven el reloj congelado de un test. La serie A, tres
        // semanas seguidas; la B, cada dos semanas desde la tercera fecha de A: comparten esa.
        $first = now()->addDays(2)->setTime(10, 0);
        $shared = $first->copy()->addWeeks(2);
        $bodies = [
            ['starts_at' => $first->format('Y-m-d\TH:i:s'), 'recurrence' => ['count' => 3]],
            ['starts_at' => $shared->format('Y-m-d\TH:i:s'), 'recurrence' => ['every_weeks' => 2, 'count' => 3]],
        ];

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        $holder->select('select id from veterinarians where id = ? for update', [$schedule->veterinarian_id]);

        $processes = [];

        try {
            foreach ($bodies as $index => $body) {
                $token = $this->userWithRoles($clinic, ['recepcionista'])->createToken("serie-{$index}")->plainTextToken;
                $processes[] = $this->startRequest($token, 'POST', '/api/appointments', [
                    'schedule_id' => $schedule->id,
                    'contact_name' => "Serie {$index}",
                    'contact_phone' => '981123456',
                    ...$body,
                ]);
            }

            $this->waitUntilLockWaits(DB::connection(), 2, $processes, 'select * from `veterinarians` where %for update');
        } finally {
            $holder->rollBack();
        }

        $results = array_map(fn (Process $process) => $this->resultOf($process), $processes);
        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([201, 409], $statuses, json_encode($results, JSON_UNESCAPED_UNICODE));

        $accepted = $results[0]['status'] === 201 ? $results[0] : $results[1];
        $rejected = $results[0]['status'] === 409 ? $results[0] : $results[1];

        $this->assertCount(3, $accepted['body']['data']);
        $this->assertSame('APPOINTMENT_SERIES_CONFLICT', $rejected['body']['error_code']);
        $this->assertSame(
            [$shared->copy()->utc()->format('Y-m-d\TH:i:s.u\Z')],
            array_column($rejected['body']['errors']['conflicts'], 'starts_at')
        );

        // Solo los tres turnos de la serie que entró, todos con su series_id.
        $rows = DB::table('appointments')->where('schedule_id', $schedule->id)->get();
        $this->assertCount(3, $rows);
        $this->assertSame([$accepted['body']['meta']['series_id']], $rows->pluck('series_id')->unique()->values()->all());
    }
}
