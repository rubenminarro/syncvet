<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\ScheduleHour;
use App\Models\Service;
use App\Models\Species;
use App\Models\User;
use App\Models\Veterinarian;
use App\Models\Warehouse;
use App\Services\PatientPhotoService;
use App\Support\CurrentClinic;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra lo que dejaron una o más corridas de las colecciones de Postman (docs/05-pruebas/postman) en la clínica de la demo
 * (cierre de la F3 del frontend): la API no tiene baja de turnos y, con turnos, tampoco se borran la agenda, el cliente ni el
 * paciente, así que una colección no puede limpiar lo suyo. Después de cada colección, la regresión corre
 * `php artisan postman:cleanup <corrida>` con el número de corrida que la colección dejó en su variable `run`.
 *
 * - Solo en local y testing (DemoSeeder::ENVIRONMENTS) y solo en la Clínica A del DemoSeeder.
 * - Lo de la corrida se reconoce por su número, como lo escriben las colecciones: los usuarios y los clientes con el email
 *   `…<corrida>@mailinator.com`; los servicios, las agendas, las especies y las razas con la corrida en el nombre; la ficha del
 *   veterinario de esos usuarios o con la matrícula `MP-<corrida>`; los pacientes de esos clientes, con el microchip
 *   `CHIP<corrida>` o de esas especies; las sucursales `SUC-<corrida>-…` (la de sucursales, en base 36), con sus depósitos. La
 *   corrida tiene al menos 5 caracteres, letras o dígitos: nunca un patrón que abarque otros datos.
 * - Los turnos de esas agendas, clientes, pacientes, fichas o servicios, con sus bloqueos y sus tramos, se borran de verdad,
 *   como los clientes, los pacientes (y su foto), los servicios, las fichas, las especies y las razas. Los usuarios quedan dados
 *   de baja (SoftDeletes, sin tokens) y no se borran de verdad: la auditoría los referencia (audit_logs.user_id), y es inmutable.
 *   Una sucursal que usa alguien que no es de la corrida (admin-a, por ejemplo) no se toca y se informa.
 * - Modelo por modelo (con su auditoría), en una transacción: si algo falla, no queda nada a medias. Nunca toca otros datos:
 *   ni las agendas "Demo …" de demo:agenda, ni la Clínica B, ni admin-a, recepcion-a o vet-a.
 */
class PostmanCleanupCommand extends Command
{
    protected $signature = 'postman:cleanup {runs* : Los números de corrida (la variable run de cada colección)}';

    protected $description = 'Borra lo que dejaron las corridas de las colecciones de Postman en la clínica de la demo (Clínica A)';

    /** Al menos 5 letras o dígitos: el número de corrida de las colecciones (6 dígitos, o la base 36 de sucursales). */
    private const RUN_PATTERN = '/^[A-Za-z0-9]{5,}$/';

    public function handle(): int
    {
        if (! app()->environment(DemoSeeder::ENVIRONMENTS)) {
            $this->components->error('postman:cleanup solo corre en los entornos local y testing.');

            return self::FAILURE;
        }

        $runs = array_values(array_unique($this->argument('runs')));

        foreach ($runs as $run) {
            if (! preg_match(self::RUN_PATTERN, $run)) {
                $this->components->error("La corrida «{$run}» no es válida: al menos 5 letras o dígitos.");

                return self::FAILURE;
            }
        }

        $clinic = DemoAgendaCommand::demoClinic();

        if ($clinic === null) {
            $this->components->error('No está la clínica de la demo: corré primero php artisan db:seed --class=DemoSeeder.');

            return self::FAILURE;
        }

        foreach ($runs as $run) {
            $summary = CurrentClinic::run($clinic, fn () => DB::transaction(fn () => $this->cleanup($clinic, $run)));
            $this->components->info("Corrida {$run}: " . $this->describe($summary));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int|list<string>>
     */
    private function cleanup(Clinic $clinic, string $run): array
    {
        // El LIKE preselecciona; el número tiene que estar entero (no dentro de otro, como 123456 en 1234567 o en 123456b).
        $like = '%' . $run . '%';
        $inName = fn (?string $text) => $text !== null && preg_match('/(?<![A-Za-z0-9])' . $run . '(?![A-Za-z0-9])/i', $text) === 1;
        $inEmail = fn (?string $text) => $text !== null && preg_match('/(?<![0-9])' . $run . '@mailinator\.com$/i', $text) === 1;
        $byName = fn (Collection $models) => $models->filter(fn ($model) => $inName($model->name))->values();
        $byEmail = fn (Collection $models) => $models->filter(fn ($model) => $inEmail($model->email))->values();

        $users = $byEmail(User::withTrashed()->where('clinic_id', $clinic->id)->where('email', 'like', $like)->get());
        $vets = Veterinarian::withTrashed()
            ->where(fn (Builder $q) => $q->whereIn('user_id', $users->modelKeys())->orWhere('license_number', 'MP-' . $run))
            ->get();
        $services = $byName(Service::withTrashed()->where('name', 'like', $like)->get());
        $schedules = $byName(Schedule::where('name', 'like', $like)->get());
        $species = $byName(Species::withTrashed()->whereNotNull('clinic_id')->where('name', 'like', $like)->get());
        $breeds = Breed::withTrashed()->whereNotNull('clinic_id')
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhereIn('species_id', $species->modelKeys()))
            ->get()->filter(fn (Breed $breed) => $inName($breed->name) || $species->contains('id', $breed->species_id))->values();
        $clients = $byEmail(Client::withTrashed()->where('email', 'like', $like)->get());
        $patients = Patient::withTrashed()
            ->where(fn (Builder $q) => $q
                ->whereIn('client_id', $clients->modelKeys())
                ->orWhere('microchip', 'CHIP' . $run)
                ->orWhereIn('species_id', $species->modelKeys())
                ->orWhereIn('breed_id', $breeds->modelKeys()))
            ->get();
        $appointments = Appointment::query()
            ->where(fn (Builder $q) => $q
                ->whereIn('schedule_id', $schedules->modelKeys())
                ->orWhereIn('client_id', $clients->modelKeys())
                ->orWhereIn('patient_id', $patients->modelKeys())
                ->orWhereIn('veterinarian_id', $vets->modelKeys())
                ->orWhereIn('service_id', $services->modelKeys()))
            ->get();

        $summary = [
            'appointments' => $this->forceDeleteAll($appointments),
            'blocks' => $this->forceDeleteAll(ScheduleBlock::whereIn('schedule_id', $schedules->modelKeys())->get()),
            'hours' => $this->forceDeleteAll(ScheduleHour::whereIn('schedule_id', $schedules->modelKeys())->get()),
            'schedules' => $this->forceDeleteAll($schedules),
            'patients' => $this->forceDeletePatients($patients),
            'clients' => $this->forceDeleteAll($clients),
            'veterinarians' => $this->forceDeleteAll($vets),
            'services' => $this->forceDeleteAll($services),
            'breeds' => $this->forceDeleteAll($breeds),
            'species' => $this->forceDeleteAll($species),
        ];

        [$summary['branches'], $summary['skipped_branches']] = $this->deleteBranches($run, $users);
        $summary['users'] = $this->retireUsers($users);

        return $summary;
    }

    /**
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $models
     */
    private function forceDeleteAll(Collection $models): int
    {
        foreach ($models as $model) {
            $model->forceDelete();
        }

        return $models->count();
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    private function forceDeletePatients(Collection $patients): int
    {
        foreach ($patients as $patient) {
            $path = $patient->photo_path;
            $patient->forceDelete();

            if (is_string($path) && $path !== '') {
                Storage::disk(PatientPhotoService::DISK)->delete($path);
            }
        }

        return $patients->count();
    }

    /**
     * Las sucursales de la corrida de la colección de sucursales (`SUC-<corrida>-…`), con sus depósitos, salvo las que usa
     * alguien que no es de la corrida.
     *
     * @param  Collection<int, User>  $runUsers
     * @return array{0: int, 1: list<string>}
     */
    private function deleteBranches(string $run, Collection $runUsers): array
    {
        $deleted = 0;
        $skipped = [];
        $branches = Branch::where('code', 'like', 'SUC-' . strtoupper($run) . '-%')->get();

        foreach ($branches as $branch) {
            $usedByOthers = DB::table('branch_user')->where('branch_id', $branch->id)->whereNotIn('user_id', $runUsers->modelKeys())->exists()
                || User::withTrashed()->where('current_branch_id', $branch->id)->whereNotIn('id', $runUsers->modelKeys())->exists();

            if ($usedByOthers) {
                $skipped[] = $branch->code;

                continue;
            }

            Warehouse::where('branch_id', $branch->id)->get()->each->delete();
            $branch->delete();
            $deleted++;
        }

        return [$deleted, $skipped];
    }

    /**
     * Los usuarios de la corrida: sin tokens y dados de baja (la auditoría los referencia).
     *
     * @param  Collection<int, User>  $users
     */
    private function retireUsers(Collection $users): int
    {
        foreach ($users as $user) {
            $user->tokens()->delete();

            if (! $user->trashed()) {
                $user->delete();
            }
        }

        return $users->count();
    }

    /**
     * @param  array<string, int|list<string>>  $summary
     */
    private function describe(array $summary): string
    {
        $labels = [
            'appointments' => 'turnos', 'blocks' => 'bloqueos', 'hours' => 'tramos', 'schedules' => 'agendas',
            'patients' => 'pacientes', 'clients' => 'clientes', 'veterinarians' => 'fichas', 'services' => 'servicios',
            'breeds' => 'razas', 'species' => 'especies', 'branches' => 'sucursales', 'users' => 'usuarios dados de baja',
        ];
        $parts = [];

        foreach ($labels as $key => $label) {
            $parts[] = "{$summary[$key]} {$label}";
        }

        $text = implode(', ', $parts) . '.';

        return $summary['skipped_branches'] === []
            ? $text
            : $text . ' Sucursales sin tocar (las usa alguien que no es de la corrida): ' . implode(', ', $summary['skipped_branches']) . '.';
    }
}
