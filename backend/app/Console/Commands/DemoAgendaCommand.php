<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceStatus;
use App\Enums\VeterinarianStatus;
use App\Exceptions\AppointmentException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use App\Models\Veterinarian;
use App\Services\AppointmentService;
use App\Services\AppointmentTransitionService;
use App\Services\ScheduleBlockService;
use App\Services\ScheduleService;
use App\Services\ServiceCatalogService;
use App\Support\CurrentClinic;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Agendas y turnos ficticios de la semana en curso en la clínica de la demo (demo-02, F3 del
 * frontend): la Clínica A del DemoSeeder, que no trae agendas ni turnos.
 *
 * - Solo en local y testing (DemoSeeder::ENVIRONMENTS): en production se niega sin tocar nada.
 * - Lo suyo lleva el prefijo PREFIX: las agendas "Demo …" (de cualquier sucursal de la clínica)
 *   con sus bloqueos y sus turnos, y los servicios "Demo …". Al correrlo de nuevo borra los
 *   turnos y los bloqueos de esas agendas, actualiza las agendas (conservan su id), elimina las
 *   "Demo …" que ya no están en AGENDAS y regenera la semana. Nunca toca otros datos: ni otras
 *   agendas ni sus turnos, ni la Clínica B, ni admin-a, recepcion-a o vet-a (solo vincula una
 *   agenda a la ficha de vet-a).
 * - Por los Services (ScheduleService, ScheduleBlockService, ServiceCatalogService,
 *   AppointmentService y AppointmentTransitionService), con admin-a como usuario: las reglas de
 *   negocio y la auditoría son las de la API. La única baja sin Service es la de los turnos de
 *   las agendas "Demo …" (la API no tiene baja de turnos), modelo por modelo (auditoría).
 * - La semana va de lunes a domingo en America/Asuncion. Los estados que exigen pasado (atendido,
 *   ausente) van en horas que ya pasaron; si la semana todavía no tiene (lunes temprano), en el
 *   día anterior, de la semana pasada. Como el alta no acepta un día que ya pasó y el fichado
 *   exige el día del turno, el comando corre el reloj de PHP (Carbon::setTestNow) a la hora en
 *   que cada paso habría pasado en la recepción, y lo devuelve al terminar cada paso.
 * - Datos ficticios: los pacientes del DemoSeeder y contactos con nombres y teléfonos inventados.
 * - Todo en una transacción: si algo falla, no queda nada a medias.
 */
class DemoAgendaCommand extends Command
{
    protected $signature = 'demo:agenda';

    protected $description = 'Agendas y turnos ficticios de la semana en curso en la clínica de la demo (Clínica A)';

    /**
     * La marca de lo que crea: el comienzo del nombre de sus agendas y de sus servicios.
     */
    public const PREFIX = 'Demo ';

    public const NOTES = 'Dato de demostración (php artisan demo:agenda).';

    public const CANCELLATION_REASON = 'El cliente avisó que no puede venir.';

    public const BLOCK_REASON = 'Demo: capacitación del personal';

    /**
     * Los servicios de las agendas: se crean si faltan y se reactivan si están inactivos.
     */
    public const SERVICES = [
        'consulta' => ['name' => 'Demo Consulta general', 'category' => 'consultation', 'duration_minutes' => 30, 'standard_price' => '120000', 'tax_rate' => 10],
        'peluqueria' => ['name' => 'Demo Baño y corte', 'category' => 'grooming', 'duration_minutes' => 60, 'standard_price' => '90000', 'tax_rate' => 10],
        'guardia' => ['name' => 'Demo Guardia', 'category' => 'consultation', 'duration_minutes' => 30, 'standard_price' => '180000', 'tax_rate' => 10],
        'vacunacion' => ['name' => 'Demo Vacunación', 'category' => 'vaccination', 'duration_minutes' => 30, 'standard_price' => '70000', 'tax_rate' => 10],
    ];

    /**
     * Las agendas. branch: 'default' (la sucursal por defecto) u 'others' (una en cada sucursal
     * activa que no es la por defecto). vet: la ficha de vet-a. hours: día (1 lunes … 7 domingo)
     * => tramos. every: un turno cada tantos horarios (la densidad de la grilla).
     */
    public const AGENDAS = [
        [
            'name' => 'Demo Consultorio 1', 'branch' => 'default', 'vet' => true, 'service' => 'consulta', 'slot' => 30, 'color' => '#206BC4', 'every' => 2,
            'hours' => [1 => [['08:00', '12:00'], ['14:00', '18:00']], 2 => [['08:00', '12:00'], ['14:00', '18:00']], 3 => [['08:00', '12:00'], ['14:00', '18:00']], 4 => [['08:00', '12:00'], ['14:00', '18:00']], 5 => [['08:00', '12:00'], ['14:00', '18:00']], 6 => [['08:00', '12:00']]],
            'patients' => ['Firulais', 'Mishi', 'Rocky', 'Lucero'],
            'reasons' => ['Control anual', 'Consulta por vómitos', 'Revisión de la piel', 'Control de peso'],
        ],
        [
            'name' => 'Demo Consultorio 2', 'branch' => 'default', 'vet' => false, 'service' => 'consulta', 'slot' => 30, 'color' => '#2FB344', 'every' => 3,
            'hours' => [1 => [['09:00', '17:00']], 2 => [['09:00', '17:00']], 3 => [['09:00', '17:00']], 4 => [['09:00', '17:00']], 5 => [['09:00', '17:00']]],
            'patients' => ['Rocky', 'Firulais', 'Mishi'],
            'reasons' => ['Control posoperatorio', 'Consulta por tos', 'Desparasitación'],
        ],
        [
            'name' => 'Demo Peluquería', 'branch' => 'default', 'vet' => false, 'service' => 'peluqueria', 'slot' => 60, 'color' => '#AE3EC9', 'every' => 2,
            'hours' => [1 => [['09:00', '17:00']], 2 => [['09:00', '17:00']], 3 => [['09:00', '17:00']], 4 => [['09:00', '17:00']], 5 => [['09:00', '17:00']], 6 => [['09:00', '13:00']]],
            'patients' => ['Mishi', 'Firulais', 'Rocky'],
            'reasons' => ['Baño y corte', 'Corte de uñas', 'Baño medicado'],
        ],
        [
            'name' => 'Demo Guardia', 'branch' => 'default', 'vet' => false, 'service' => 'guardia', 'slot' => 30, 'color' => '#D63939', 'every' => 3,
            'hours' => [1 => [['08:00', '20:00']], 2 => [['08:00', '20:00']], 3 => [['08:00', '20:00']], 4 => [['08:00', '20:00']], 5 => [['08:00', '20:00']], 6 => [['08:00', '20:00']], 7 => [['08:00', '20:00']]],
            'patients' => ['Lucero', 'Rocky', 'Firulais', 'Mishi'],
            'reasons' => ['Guardia: decaimiento', 'Guardia: herida en la pata', 'Guardia: no come'],
        ],
        [
            'name' => 'Demo Vacunación', 'branch' => 'others', 'vet' => false, 'service' => 'vacunacion', 'slot' => 30, 'color' => '#F76707', 'every' => 2,
            'hours' => [1 => [['09:00', '13:00']], 2 => [['09:00', '13:00']], 3 => [['09:00', '13:00']], 4 => [['09:00', '13:00']], 5 => [['09:00', '13:00']]],
            'patients' => ['Firulais', 'Mishi', 'Rocky'],
            'reasons' => ['Vacuna antirrábica', 'Vacuna séxtuple', 'Refuerzo anual'],
        ],
    ];

    /**
     * Contactos sin cliente (primera vez): nombres y teléfonos inventados.
     */
    public const CONTACTS = [
        ['name' => 'Sofía Cabrera (primera vez)', 'phone' => '+595981000101'],
    ];

    /**
     * Los estados, en el orden del resumen.
     */
    private const STATUS_ORDER = [
        AppointmentStatus::PENDING_CONFIRMATION,
        AppointmentStatus::CONFIRMED,
        AppointmentStatus::CHECKED_IN,
        AppointmentStatus::ATTENDED,
        AppointmentStatus::NO_SHOW,
        AppointmentStatus::CANCELLED,
    ];

    private const PAST_ROTATION = [AppointmentStatus::ATTENDED, AppointmentStatus::ATTENDED, AppointmentStatus::NO_SHOW, AppointmentStatus::ATTENDED, AppointmentStatus::CANCELLED];

    private const FUTURE_ROTATION = [AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED, AppointmentStatus::PENDING_CONFIRMATION, AppointmentStatus::CONFIRMED, AppointmentStatus::CANCELLED];

    private const SOURCES = ['reception', 'phone', 'whatsapp'];

    private CarbonImmutable $now;

    private ?CarbonImmutable $originalTestNow;

    private int $skipped = 0;

    public function __construct(
        private readonly ScheduleService $schedules,
        private readonly ScheduleBlockService $blocks,
        private readonly ServiceCatalogService $services,
        private readonly AppointmentService $appointments,
        private readonly AppointmentTransitionService $transitions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! app()->environment(DemoSeeder::ENVIRONMENTS)) {
            $this->components->error('demo:agenda solo corre en los entornos local y testing: sus datos ficticios son para la clínica de la demo.');

            return self::FAILURE;
        }

        $clinic = $this->demoClinic();

        if ($clinic === null) {
            $this->components->error('No está la clínica de la demo: corré primero php artisan db:seed --class=DemoSeeder.');

            return self::FAILURE;
        }

        $test = Carbon::getTestNow();
        $this->originalTestNow = $test === null ? null : CarbonImmutable::instance($test);
        $this->now = now()->toImmutable()->setTimezone(config('app.timezone'));
        $previousUser = Auth::user();

        try {
            $summary = CurrentClinic::run($clinic, fn () => DB::transaction(fn () => $this->generate()));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->restoreClock();
            $previousUser === null ? Auth::guard()->forgetUser() : Auth::guard()->setUser($previousUser);
        }

        CurrentClinic::run($clinic, fn () => $this->report($summary));

        return self::SUCCESS;
    }

    /**
     * La Clínica A del DemoSeeder, por su RUC y su marca de demostración.
     */
    private function demoClinic(): ?Clinic
    {
        $clinic = Clinic::where('ruc', DemoSeeder::CLINICS[0]['ruc'])->first();

        if ($clinic === null) {
            return null;
        }

        $isDemo = DB::table('system_settings')
            ->where('clinic_id', $clinic->id)
            ->where('key', DemoSeeder::MARKER)
            ->exists();

        return $isDemo ? $clinic : null;
    }

    /**
     * @return array<string, mixed> lo que muestra el resumen
     */
    private function generate(): array
    {
        $admin = $this->demoUser(DemoSeeder::CLINICS[0]['users']['administrador'][0]);
        Auth::guard()->setUser($admin);

        $vet = $this->demoVeterinarian();
        $defaultBranch = Branch::where('is_default', true)->firstOrFail();
        $otherBranches = Branch::where('is_default', false)->where('is_active', true)->orderBy('id')->get();
        $patients = $this->demoPatients();

        $this->cleanUp();

        $services = [];

        foreach (self::SERVICES as $key => $data) {
            $services[$key] = $this->demoService($data);
        }

        $agendas = [];

        foreach (self::AGENDAS as $definition) {
            $branches = $definition['branch'] === 'default' ? [$defaultBranch] : $otherBranches->all();

            foreach ($branches as $branch) {
                $agendas[] = [
                    'definition' => $definition,
                    'schedule' => $this->demoSchedule($definition, $branch, $definition['vet'] ? $vet : null, $services[$definition['service']]),
                ];
            }
        }

        $this->deleteStaleSchedules(array_map(fn (array $agenda) => $agenda['schedule']->id, $agendas));

        $plan = $this->plan($agendas, $patients);

        foreach ($plan['specs'] as $spec) {
            $this->createAppointment($spec);
        }

        $block = $this->createBlock($agendas);

        return [
            'week' => [$this->weekStart(), $this->weekStart()->addDays(6)],
            'schedules' => array_map(fn (array $agenda) => $agenda['schedule'], $agendas),
            'block' => $block,
        ];
    }

    private function demoUser(string $email): User
    {
        $user = User::query()->inClinic()->active()->where('email', User::normalizeEmail($email))->first();

        if ($user === null) {
            throw new RuntimeException("No está el usuario {$email} activo en la clínica de la demo: corré el DemoSeeder.");
        }

        return $user;
    }

    /**
     * La ficha de vet-a, activa y con su usuario activo; no se modifica.
     */
    private function demoVeterinarian(): Veterinarian
    {
        $user = $this->demoUser(DemoSeeder::CLINICS[0]['users']['veterinario'][0]);
        $vet = Veterinarian::where('user_id', $user->id)->first();

        if ($vet === null || $vet->status !== VeterinarianStatus::ACTIVE) {
            throw new RuntimeException('La ficha de veterinario de vet-a no está activa: el comando no la modifica.');
        }

        return $vet;
    }

    /**
     * Los pacientes del DemoSeeder (los clientes por su documento), sin los fallecidos.
     *
     * @return array<string, Patient> por nombre
     */
    private function demoPatients(): array
    {
        $documents = array_column(DemoSeeder::CLINICS[0]['clients'], 'document_number');
        $clientIds = Client::whereIn('document_number', $documents)->pluck('id');

        $patients = Patient::whereIn('client_id', $clientIds)->whereNull('deceased_at')->orderBy('id')->get()->keyBy('name')->all();

        if ($patients === []) {
            throw new RuntimeException('No están los pacientes del DemoSeeder: corré el DemoSeeder.');
        }

        return $patients;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Schedule>
     */
    private function ownSchedules(): \Illuminate\Support\Collection
    {
        return Schedule::query()->orderBy('id')->get()->filter(fn (Schedule $schedule) => str_starts_with($schedule->name, self::PREFIX))->values();
    }

    /**
     * Borra los turnos y los bloqueos de las agendas "Demo …". Un turno con una consulta (FASE 4)
     * no se borra: el comando se niega.
     */
    private function cleanUp(): void
    {
        $ids = $this->ownSchedules()->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $withConsultation = DB::table('consultations')
            ->whereIn('appointment_id', Appointment::query()->whereIn('schedule_id', $ids)->select('id'))
            ->exists();

        if ($withConsultation) {
            throw new RuntimeException('Un turno de una agenda "Demo …" tiene una consulta: el comando no lo borra.');
        }

        Appointment::query()->whereIn('schedule_id', $ids)->orderBy('id')->get()->each->delete();

        foreach (ScheduleBlock::query()->whereIn('schedule_id', $ids)->orderBy('id')->get() as $block) {
            $this->blocks->delete($block);
        }
    }

    /**
     * Las agendas "Demo …" que ya no están en AGENDAS (ya sin turnos), por ScheduleService.
     *
     * @param list<int> $keep
     */
    private function deleteStaleSchedules(array $keep): void
    {
        foreach ($this->ownSchedules() as $schedule) {
            if (! in_array($schedule->id, $keep, true)) {
                $this->schedules->delete($schedule);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function demoService(array $data): Service
    {
        $service = Service::where('name', $data['name'])->first();

        if ($service === null) {
            return $this->services->create([...$data, 'status' => ServiceStatus::ACTIVE->value]);
        }

        if ($service->status !== ServiceStatus::ACTIVE) {
            return $this->services->update($service, ['status' => ServiceStatus::ACTIVE->value]);
        }

        return $service;
    }

    /**
     * La agenda de la definición en la sucursal: la crea o, si ya está (por su nombre), la
     * actualiza (ya sin turnos: puede cambiar de veterinario).
     *
     * @param array<string, mixed> $definition
     */
    private function demoSchedule(array $definition, Branch $branch, ?Veterinarian $vet, Service $service): Schedule
    {
        $hours = [];

        foreach ($definition['hours'] as $weekday => $ranges) {
            foreach ($ranges as [$startsAt, $endsAt]) {
                $hours[] = ['weekday' => $weekday, 'starts_at' => $startsAt, 'ends_at' => $endsAt];
            }
        }

        $data = [
            'branch_id' => $branch->id,
            'name' => $definition['name'],
            'veterinarian_id' => $vet?->id,
            'service_id' => $service->id,
            'slot_minutes' => $definition['slot'],
            'color' => $definition['color'],
            'is_active' => true,
            'hours' => $hours,
        ];

        $existing = Schedule::where('branch_id', $branch->id)->where('name', $definition['name'])->first();

        return $existing === null ? $this->schedules->create($data) : $this->schedules->update($existing, $data);
    }

    private function weekStart(): CarbonImmutable
    {
        return $this->now->startOfWeek(CarbonImmutable::MONDAY);
    }

    private function todayStart(): CarbonImmutable
    {
        return $this->now->startOfDay();
    }

    /**
     * Los turnos de la semana: uno cada `every` horarios de cada agenda; el estado por la hora
     * (ya terminó, en curso, por venir). Después, lo que la semana tiene que mostrar siempre:
     * cada estado, algunos de hoy, un sobreturno y un turno de un contacto.
     *
     * @param list<array{definition: array<string, mixed>, schedule: Schedule}> $agendas
     * @param array<string, Patient> $patients
     * @return array{specs: list<array<string, mixed>>}
     */
    private function plan(array $agendas, array $patients): array
    {
        $specs = [];

        foreach ($agendas as $agenda) {
            $definition = $agenda['definition'];
            $agendaPatients = array_values(array_filter(array_map(fn (string $name) => $patients[$name] ?? null, $definition['patients'])));
            $agendaPatients = $agendaPatients === [] ? array_values($patients) : $agendaPatients;
            $counter = 0;

            for ($day = 0; $day < 7; $day++) {
                $date = $this->weekStart()->addDays($day);
                $slotIndex = 0;

                foreach ($this->slots($definition, $date) as [$startsAt, $endsAt]) {
                    if (($slotIndex++ + $day) % $definition['every'] !== 0) {
                        continue;
                    }

                    $specs[] = $this->spec($agenda['schedule'], $startsAt, $endsAt, $agendaPatients[$counter % count($agendaPatients)], $definition['reasons'][$counter % count($definition['reasons'])], $counter);
                    $counter++;
                }
            }
        }

        $specs = $this->assignStatuses($specs);
        $specs = $this->ensureCheckedIn($specs, $agendas, $patients);
        $specs = $this->ensureStatuses($specs, $agendas, $patients);
        $specs[] = $this->contactSpec($specs, $agendas);
        $specs[] = $this->overbookSpec($specs, $patients);

        return ['specs' => array_values(array_filter($specs))];
    }

    /**
     * Los horarios de un día de la agenda: desde el comienzo de cada tramo, cada slot minutos,
     * mientras el turno entero entre en el tramo.
     *
     * @param array<string, mixed> $definition
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function slots(array $definition, CarbonImmutable $date): array
    {
        $slots = [];

        foreach ($definition['hours'][$date->isoWeekday()] ?? [] as [$from, $to]) {
            $startsAt = $date->setTimeFromTimeString($from);
            $limit = $date->setTimeFromTimeString($to);

            while ($startsAt->addMinutes($definition['slot'])->lessThanOrEqualTo($limit)) {
                $slots[] = [$startsAt, $startsAt->addMinutes($definition['slot'])];
                $startsAt = $startsAt->addMinutes($definition['slot']);
            }
        }

        return $slots;
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(Schedule $schedule, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?Patient $patient, string $reason, int $counter, array $extra = []): array
    {
        return [
            'schedule' => $schedule,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'patient' => $patient,
            'contact' => null,
            'reason' => $reason,
            'source' => self::SOURCES[$counter % count(self::SOURCES)],
            'overbook' => false,
            'status' => AppointmentStatus::PENDING_CONFIRMATION,
            'counter' => $counter,
            ...$extra,
        ];
    }

    /**
     * @param list<array<string, mixed>> $specs
     * @return list<array<string, mixed>>
     */
    private function assignStatuses(array $specs): array
    {
        $past = 0;
        $future = 0;

        foreach ($specs as &$spec) {
            if ($spec['ends_at']->lessThanOrEqualTo($this->now)) {
                $spec['status'] = self::PAST_ROTATION[$past++ % count(self::PAST_ROTATION)];
            } elseif ($spec['starts_at']->lessThanOrEqualTo($this->now)) {
                // En curso (hoy): ya llegó.
                $spec['status'] = AppointmentStatus::CHECKED_IN;
            } else {
                $spec['status'] = self::FUTURE_ROTATION[$future++ % count(self::FUTURE_ROTATION)];
            }
        }

        return $specs;
    }

    /**
     * Si a la semana le falta un estado (el lunes temprano no hay atendidos ni ausentes; el
     * domingo a la noche, nada por venir), va en un horario libre de la guardia de ayer (el
     * lunes, de la semana pasada): un turno a confirmar o confirmado de ayer también es válido.
     *
     * @param list<array<string, mixed>> $specs
     * @param list<array{definition: array<string, mixed>, schedule: Schedule}> $agendas
     * @param array<string, Patient> $patients
     * @return list<array<string, mixed>>
     */
    private function ensureStatuses(array $specs, array $agendas, array $patients): array
    {
        $missing = array_values(array_filter(
            array_filter(self::STATUS_ORDER, fn (AppointmentStatus $status) => $status !== AppointmentStatus::CHECKED_IN),
            fn (AppointmentStatus $status) => ! $this->hasStatus($specs, $status)
        ));

        if ($missing === []) {
            return $specs;
        }

        $guardia = $this->dailyAgenda($agendas);
        $yesterday = $this->todayStart()->subDay();
        $free = array_values(array_filter(
            $this->slots($guardia['definition'], $yesterday),
            fn (array $slot) => ! $this->isTaken($specs, $guardia['schedule'], $slot[0])
        ));
        $patient = array_values($patients)[0];

        foreach ($missing as $i => $status) {
            $slot = $free[$i * 2] ?? throw new RuntimeException('No hay horarios libres en la guardia de ayer.');
            $specs[] = $this->spec($guardia['schedule'], $slot[0], $slot[1], $patient, $guardia['definition']['reasons'][0], $i, ['status' => $status]);
        }

        return $specs;
    }

    /**
     * Siempre un fichado hoy: el turno por venir más cercano de hoy o, si no hay, uno nuevo en
     * un horario libre de la guardia de hoy.
     *
     * @param list<array<string, mixed>> $specs
     * @param list<array{definition: array<string, mixed>, schedule: Schedule}> $agendas
     * @param array<string, Patient> $patients
     * @return list<array<string, mixed>>
     */
    private function ensureCheckedIn(array $specs, array $agendas, array $patients): array
    {
        if ($this->hasStatus($specs, AppointmentStatus::CHECKED_IN)) {
            return $specs;
        }

        foreach ($specs as $i => $spec) {
            if ($spec['starts_at']->isSameDay($this->now) && $spec['starts_at']->greaterThan($this->now) && $spec['patient'] !== null) {
                $specs[$i]['status'] = AppointmentStatus::CHECKED_IN;

                return $specs;
            }
        }

        $guardia = $this->dailyAgenda($agendas);

        foreach (array_reverse($this->slots($guardia['definition'], $this->todayStart())) as [$startsAt, $endsAt]) {
            if (! $this->isTaken($specs, $guardia['schedule'], $startsAt)) {
                $specs[] = $this->spec($guardia['schedule'], $startsAt, $endsAt, array_values($patients)[0], $guardia['definition']['reasons'][0], 0, ['status' => AppointmentStatus::CHECKED_IN]);

                return $specs;
            }
        }

        throw new RuntimeException('No hay un horario libre hoy en la guardia para el turno fichado.');
    }

    /**
     * Un turno de un contacto sin cliente (primera vez), en un horario libre: hoy por venir,
     * si no, en otro día por venir de la semana y, si no, en cualquiera.
     *
     * @param list<array<string, mixed>> $specs
     * @param list<array{definition: array<string, mixed>, schedule: Schedule}> $agendas
     * @return array<string, mixed>
     */
    private function contactSpec(array $specs, array $agendas): array
    {
        $agenda = $this->agendaNamed($agendas, 'Demo Consultorio 2') ?? $this->dailyAgenda($agendas);
        $candidates = [];

        foreach ([$agenda, $this->dailyAgenda($agendas)] as $candidate) {
            for ($day = 0; $day < 7; $day++) {
                foreach ($this->slots($candidate['definition'], $this->weekStart()->addDays($day)) as $slot) {
                    if (! $this->isTaken($specs, $candidate['schedule'], $slot[0])) {
                        $candidates[] = [$candidate, $slot];
                    }
                }
            }
        }

        [$chosen, $slot] = $this->preferred($candidates, fn (array $candidate) => $candidate[1][0]);
        $contact = self::CONTACTS[0];
        $status = $slot[0]->greaterThan($this->now) ? AppointmentStatus::PENDING_CONFIRMATION : AppointmentStatus::NO_SHOW;

        return $this->spec($chosen['schedule'], $slot[0], $slot[1], null, 'Primera consulta', 1, [
            'contact' => $contact,
            'source' => 'whatsapp',
            'status' => $status,
        ]);
    }

    /**
     * Un sobreturno en el consultorio de vet-a, sobre un turno que ocupa el horario: hoy por
     * venir, si no, otro día por venir y, si no, cualquiera de la semana.
     *
     * @param list<array<string, mixed>> $specs
     * @param array<string, Patient> $patients
     * @return array<string, mixed>
     */
    private function overbookSpec(array $specs, array $patients): array
    {
        $candidates = array_values(array_filter($specs, fn (array $spec) => $spec['contact'] === null
            && $spec['status']->occupiesSlot()
            && $spec['schedule']->veterinarian_id !== null));

        if ($candidates === []) {
            $candidates = array_values(array_filter($specs, fn (array $spec) => $spec['status']->occupiesSlot()));
        }

        $base = $this->preferred($candidates, fn (array $spec) => $spec['starts_at']);
        $status = match (true) {
            $base['ends_at']->lessThanOrEqualTo($this->now) => AppointmentStatus::ATTENDED,
            $base['starts_at']->lessThanOrEqualTo($this->now) => AppointmentStatus::CHECKED_IN,
            default => AppointmentStatus::CONFIRMED,
        };
        $patient = collect($patients)->first(fn (Patient $p) => $p->id !== $base['patient']?->id) ?? $base['patient'];

        return $this->spec($base['schedule'], $base['starts_at'], $base['ends_at'], $patient, 'Urgencia: sobreturno', 2, [
            'overbook' => true,
            'status' => $status,
        ]);
    }

    /**
     * El candidato preferido: el primero de hoy por venir; si no hay, el primero por venir de la
     * semana; si no, el último que ya pasó.
     *
     * @template T
     *
     * @param list<T> $candidates
     * @param Closure(T): CarbonImmutable $startsAt
     * @return T
     */
    private function preferred(array $candidates, Closure $startsAt): mixed
    {
        usort($candidates, fn ($a, $b) => $startsAt($a) <=> $startsAt($b));

        $future = array_values(array_filter($candidates, fn ($c) => $startsAt($c)->greaterThan($this->now)));
        $today = array_values(array_filter($future, fn ($c) => $startsAt($c)->isSameDay($this->now)));

        return $today[0] ?? $future[0] ?? end($candidates) ?: throw new RuntimeException('No hay un horario para el turno.');
    }

    /**
     * @param list<array<string, mixed>> $specs
     */
    private function hasStatus(array $specs, AppointmentStatus $status): bool
    {
        foreach ($specs as $spec) {
            if ($spec['status'] === $status) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $specs
     */
    private function isTaken(array $specs, Schedule $schedule, CarbonImmutable $startsAt): bool
    {
        foreach ($specs as $spec) {
            if ($spec['schedule']->id === $schedule->id && $spec['starts_at']->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * La agenda abierta todos los días (la guardia).
     *
     * @param list<array{definition: array<string, mixed>, schedule: Schedule}> $agendas
     * @return array{definition: array<string, mixed>, schedule: Schedule}
     */
    private function dailyAgenda(array $agendas): array
    {
        foreach ($agendas as $agenda) {
            if (count($agenda['definition']['hours']) === 7) {
                return $agenda;
            }
        }

        throw new RuntimeException('Falta una agenda abierta todos los días.');
    }

    /**
     * @param list<array{definition: array<string, mixed>, schedule: Schedule}> $agendas
     * @return array{definition: array<string, mixed>, schedule: Schedule}|null
     */
    private function agendaNamed(array $agendas, string $name): ?array
    {
        foreach ($agendas as $agenda) {
            if ($agenda['schedule']->name === $name) {
                return $agenda;
            }
        }

        return null;
    }

    /**
     * Da el turno y lo lleva a su estado por AppointmentService y AppointmentTransitionService.
     * Un turno que ya empezó se da a las 07:00 de su día (o a las 00:00) y cada transición, a la
     * hora en que habría pasado (el reloj de PHP se corre y se devuelve). Uno que choca con un
     * turno ajeno a la demo del mismo veterinario (409) se saltea.
     *
     * @param array<string, mixed> $spec
     */
    private function createAppointment(array $spec): void
    {
        /** @var CarbonImmutable $startsAt */
        $startsAt = $spec['starts_at'];
        $started = $startsAt->lessThanOrEqualTo($this->now);
        $createdAt = $started ? $this->morningOf($startsAt) : null;
        $confirmedAt = $createdAt?->addMinutes(5);

        $data = [
            'schedule_id' => $spec['schedule']->id,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $spec['ends_at']->toIso8601String(),
            'source' => $spec['source'],
            'reason' => $spec['reason'],
            'notes' => self::NOTES,
            'overbook' => $spec['overbook'],
        ];

        if ($spec['contact'] !== null) {
            $data['contact_name'] = $spec['contact']['name'];
            $data['contact_phone'] = $spec['contact']['phone'];
        } else {
            $data['patient_id'] = $spec['patient']->id;
        }

        try {
            $appointment = $this->at($createdAt, fn () => $this->appointments->create($data));
        } catch (AppointmentException $e) {
            if ($e->getErrorCode() === 'APPOINTMENT_OVERLAP') {
                $this->skipped++;

                return;
            }

            throw $e;
        }

        $status = $spec['status'];

        if (in_array($status, [AppointmentStatus::CONFIRMED, AppointmentStatus::CHECKED_IN, AppointmentStatus::ATTENDED], true)
            || ($status === AppointmentStatus::NO_SHOW && $spec['counter'] % 2 === 0)) {
            $appointment = $this->at($confirmedAt, fn () => $this->transitions->confirm($appointment));
        }

        match ($status) {
            AppointmentStatus::CHECKED_IN => $this->at($started ? $startsAt->subMinutes(10) : null, fn () => $this->transitions->checkIn($appointment)),
            AppointmentStatus::ATTENDED => $this->attend($appointment, $startsAt),
            AppointmentStatus::NO_SHOW => $this->at($startsAt->addMinutes(15), fn () => $this->transitions->noShow($appointment)),
            AppointmentStatus::CANCELLED => $this->at($confirmedAt?->addMinutes(5), fn () => $this->transitions->cancel($appointment, self::CANCELLATION_REASON)),
            default => null,
        };
    }

    private function attend(Appointment $appointment, CarbonImmutable $startsAt): void
    {
        $appointment = $this->at($startsAt->subMinutes(10), fn () => $this->transitions->checkIn($appointment));
        $this->at($startsAt->addMinutes(20), fn () => $this->transitions->attend($appointment));
    }

    private function morningOf(CarbonImmutable $startsAt): CarbonImmutable
    {
        $morning = $startsAt->setTime(7, 0);

        return $morning->lessThan($startsAt) ? $morning : $startsAt->startOfDay();
    }

    /**
     * El bloqueo sobre un turno que ocupa el horario (no un sobreturno), con ScheduleBlockService,
     * en la peluquería o en la guardia (abierta todos los días, para que se vea hoy aunque la
     * peluquería ya cerró): el primero de hoy por venir, si no, otro día por venir y, si no, el
     * último que pasó.
     *
     * @param list<array{definition: array<string, mixed>, schedule: Schedule}> $agendas
     * @return array{block: ScheduleBlock, schedule: Schedule, affected: int}|null
     */
    private function createBlock(array $agendas): ?array
    {
        $candidates = array_filter([$this->agendaNamed($agendas, 'Demo Peluquería'), $this->dailyAgenda($agendas)]);
        $schedules = collect($candidates)->map(fn (array $agenda) => $agenda['schedule'])->keyBy('id');
        $occupying = Appointment::query()
            ->whereIn('schedule_id', $schedules->keys())
            ->whereIn('status', AppointmentStatus::occupyingValues())
            ->where('is_overbooked', false)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        if ($occupying->isEmpty()) {
            return null;
        }

        $target = $this->preferred($occupying->all(), fn (Appointment $a) => CarbonImmutable::instance($a->starts_at)->setTimezone(config('app.timezone')));
        $schedule = $schedules[$target->schedule_id];

        [$block, $affected] = $this->blocks->create($schedule, [
            'starts_at' => CarbonImmutable::instance($target->starts_at),
            'ends_at' => CarbonImmutable::instance($target->ends_at),
            'reason' => self::BLOCK_REASON,
        ]);

        return ['block' => $block, 'schedule' => $schedule, 'affected' => $affected->count()];
    }

    /**
     * Corre $callback con el reloj de PHP en $instant (null: el reloj como estaba) y lo devuelve.
     *
     * @template T
     *
     * @param Closure(): T $callback
     * @return T
     */
    private function at(?CarbonImmutable $instant, Closure $callback): mixed
    {
        if ($instant === null) {
            return $callback();
        }

        Carbon::setTestNow($instant);
        CarbonImmutable::setTestNow($instant);

        try {
            return $callback();
        } finally {
            $this->restoreClock();
        }
    }

    private function restoreClock(): void
    {
        Carbon::setTestNow($this->originalTestNow ?? null);
        CarbonImmutable::setTestNow($this->originalTestNow ?? null);
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function report(array $summary): void
    {
        [$from, $to] = $summary['week'];
        $ids = array_map(fn (Schedule $schedule) => $schedule->id, $summary['schedules']);

        $this->components->info("Agenda de la demo lista: semana del {$from->format('d/m/Y')} al {$to->format('d/m/Y')} (America/Asuncion).");

        $rows = [];

        foreach ($summary['schedules'] as $schedule) {
            $schedule->load(['branch', 'veterinarian.user']);
            $rows[] = [
                $schedule->name,
                $schedule->branch->name,
                $schedule->veterinarian?->user?->name ?? '(sin veterinario)',
                Appointment::where('schedule_id', $schedule->id)->count(),
            ];
        }

        $this->table(['Agenda', 'Sucursal', 'Veterinario', 'Turnos'], $rows);

        $counts = Appointment::whereIn('schedule_id', $ids)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $statusRows = array_map(fn (AppointmentStatus $status) => [$status->value, (int) ($counts[$status->value] ?? 0)], self::STATUS_ORDER);
        $statusRows[] = ['total', (int) $counts->sum()];
        $this->table(['Estado', 'Turnos'], $statusRows);

        $today = $this->todayStart();
        $this->components->twoColumnDetail('Turnos de hoy', (string) Appointment::whereIn('schedule_id', $ids)
            ->where('starts_at', '>=', $today->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $today->addDay()->format('Y-m-d H:i:s'))
            ->count());
        $this->components->twoColumnDetail('Sobreturnos', (string) Appointment::whereIn('schedule_id', $ids)->where('is_overbooked', true)->count());
        $this->components->twoColumnDetail('Turnos de un contacto (primera vez)', (string) Appointment::whereIn('schedule_id', $ids)->whereNull('client_id')->count());

        if ($this->skipped > 0) {
            $this->components->twoColumnDetail('Salteados (chocaban con turnos ajenos a la demo)', (string) $this->skipped);
        }

        $block = $summary['block'];

        if ($block === null) {
            $this->components->twoColumnDetail('Bloqueos', '0');
        } else {
            $starts = CarbonImmutable::instance($block['block']->starts_at)->setTimezone(config('app.timezone'));
            $ends = CarbonImmutable::instance($block['block']->ends_at)->setTimezone(config('app.timezone'));
            $this->components->twoColumnDetail(
                'Bloqueos',
                "1: {$block['schedule']->name}, {$starts->format('d/m H:i')}–{$ends->format('H:i')}, con {$block['affected']} turno(s) adentro"
            );
        }

        $this->newLine();
    }
}
