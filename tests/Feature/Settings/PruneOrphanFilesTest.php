<?php

namespace Tests\Feature\Settings;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\SystemSetting;
use App\Services\ClinicLogoService;
use App\Services\PatientPhotoService;
use App\Support\CurrentClinic;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * files:prune-orphans (FASE 2, entrega 2.5; deuda 14, P15 y pregunta 26): borra las
 * fotos de pacientes y los logos de más de 24 horas que no son el photo_path de ningún
 * paciente (también eliminado) ni el logo_path de su clínica. Solo mira la carpeta de
 * fotos y la del logo de cada clínica: nunca borra fuera de ellas, aunque una ruta de la
 * base apunte a otro lugar. --dry-run informa lo mismo que borraría.
 */
class PruneOrphanFilesTest extends TestCase
{
    use InteractsWithClinics;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PatientPhotoService::DISK);
        Storage::fake(ClinicLogoService::DISK);
        $this->clinic = $this->createClinic();
    }

    /**
     * Borra los huérfanos viejos de fotos y del logo; conserva la foto de un paciente,
     * la de un paciente eliminado, el logo vigente y los archivos de menos de 24 horas.
     */
    public function test_old_orphans_are_deleted_and_the_used_and_recent_files_are_kept(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $deleted = $this->patientOf($this->clientIn($this->clinic));
        $photo = $this->photo($patient, hoursAgo: 48);
        $deletedPhoto = $this->photo($deleted, hoursAgo: 48);
        $this->setPhotoPath($patient, $photo);
        $this->setPhotoPath($deleted, $deletedPhoto);
        CurrentClinic::run($this->clinic, fn () => Patient::findOrFail($deleted->id)->delete());
        $logo = $this->logo($this->clinic, hoursAgo: 48);
        CurrentClinic::run($this->clinic, fn () => SystemSetting::create(['key' => 'logo_path', 'value' => $logo]));

        $orphanPhoto = $this->photo($patient, hoursAgo: 25);
        $orphanLogo = $this->logo($this->clinic, hoursAgo: 25);
        $recentPhoto = $this->photo($patient, hoursAgo: 23);
        $recentLogo = $this->logo($this->clinic, hoursAgo: 23);
        Log::spy();

        $this->artisan('files:prune-orphans')
            ->expectsOutput('Borrado: patient_photos/' . $orphanPhoto)
            ->expectsOutput('Borrado: clinic_logos/' . $orphanLogo)
            ->expectsOutput('Archivos huérfanos borrados: 2 de 2.')
            ->assertExitCode(0);

        $this->assertSame([$photo, $deletedPhoto, $recentPhoto], $this->files(PatientPhotoService::DISK, [$photo, $deletedPhoto, $recentPhoto, $orphanPhoto]));
        $this->assertSame([$logo, $recentLogo], $this->files(ClinicLogoService::DISK, [$logo, $recentLogo, $orphanLogo]));
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'Archivo huérfano borrado.' && $context['path'] === $orphanPhoto)->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'Archivo huérfano borrado.' && $context['path'] === $orphanLogo)->once();
    }

    /**
     * --dry-run no borra nada e informa exactamente lo que borra la corrida real.
     */
    public function test_the_dry_run_reports_the_same_files_that_are_deleted(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $this->setPhotoPath($patient, $this->photo($patient, hoursAgo: 48));
        $orphans = [
            'patient_photos/' . $this->photo($patient, hoursAgo: 30),
            'patient_photos/' . $this->photo($this->patientOf($this->clientIn($this->clinic)), hoursAgo: 72),
            'clinic_logos/' . $this->logo($this->clinic, hoursAgo: 30),
        ];
        $before = $this->allFiles();

        // Artisan::output() vacía el buffer: se lee una sola vez.
        Artisan::call('files:prune-orphans', ['--dry-run' => true]);
        $output = Artisan::output();
        $dryRun = $this->reported($output, 'Se borraría: ');

        $this->assertSame($before, $this->allFiles(), '--dry-run borró archivos.');
        $this->assertStringContainsString('Archivos huérfanos que se borrarían: 3 (--dry-run, no se borró nada).', $output);

        Artisan::call('files:prune-orphans');
        $deleted = $this->reported(Artisan::output(), 'Borrado: ');

        sort($orphans);
        $this->assertSame($orphans, $dryRun);
        $this->assertSame($dryRun, $deleted);
        $this->assertSame(array_values(array_diff($before, $orphans)), $this->allFiles());
    }

    /**
     * Nunca borra fuera de la carpeta de fotos (clinics/{id}/patients/) ni de la del logo
     * (clinics/{id}/logo/) de cada clínica: ni otras carpetas de la clínica, ni la raíz
     * del disco, ni la carpeta de una clínica que no existe, aunque los archivos sean
     * viejos y ninguna ruta de la base los nombre. Una ruta de la base que apunta a otro
     * lugar tampoco hace borrar nada allí.
     */
    public function test_nothing_is_deleted_outside_the_photo_and_logo_folders(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $outside = [
            PatientPhotoService::DISK => [
                "clinics/{$this->clinic->id}/otra/" . Str::uuid() . '.png',
                Str::uuid() . '.png',
                'clinics/999999/patients/1/' . Str::uuid() . '.png',
                "clinics/{$this->clinic->id}/logo/" . Str::uuid() . '.png',
            ],
            ClinicLogoService::DISK => [
                "clinics/{$this->clinic->id}/otra/" . Str::uuid() . '.png',
                Str::uuid() . '.png',
                'clinics/999999/logo/' . Str::uuid() . '.png',
                "clinics/{$this->clinic->id}/patients/" . Str::uuid() . '.png',
            ],
        ];

        foreach ($outside as $disk => $paths) {
            foreach ($paths as $path) {
                $this->putOld($disk, $path, hoursAgo: 72);
            }
        }

        // Rutas de la base que apuntan fuera de las carpetas: no cambian nada.
        $this->setPhotoPath($patient, $outside[PatientPhotoService::DISK][0]);
        CurrentClinic::run($this->clinic, fn () => SystemSetting::create(['key' => 'logo_path', 'value' => '../' . $outside[ClinicLogoService::DISK][1]]));

        foreach (['--dry-run' => true, '' => false] as $option => $value) {
            Artisan::call('files:prune-orphans', $value ? [$option => true] : []);
            $this->assertStringContainsString($value ? 'que se borrarían: 0' : 'borrados: 0 de 0', Artisan::output());
        }

        foreach ($outside as $disk => $paths) {
            foreach ($paths as $path) {
                Storage::disk($disk)->assertExists($path);
            }
        }
    }

    /**
     * Cada clínica conserva solo lo que usan sus pacientes, en su carpeta. Las clínicas X y
     * X0 (como 1 y 10: "clinics/X" es el comienzo de "clinics/X0") no se mezclan. Un
     * paciente de A con un photo_path dentro de la carpeta de B no protege ese archivo: es
     * un huérfano de B y se borra. Falla si la limpieza deja de filtrar por la clínica
     * (Patient::withoutGlobalScopes()).
     */
    public function test_each_clinic_keeps_only_the_files_its_own_patients_use(): void
    {
        $a = $this->clinic;
        $b = $this->createClinic(['id' => $a->id * 10]);
        $this->assertStringStartsWith("clinics/{$a->id}", "clinics/{$b->id}/");

        $patientA = $this->patientOf($this->clientIn($a));
        $otherA = $this->patientOf($this->clientIn($a));
        $patientB = $this->patientOf($this->clientIn($b));

        $usedA = $this->photo($otherA, hoursAgo: 48);
        $this->setPhotoPath($otherA, $usedA);
        $usedB = $this->photo($patientB, hoursAgo: 48);
        $this->setPhotoPath($patientB, $usedB);
        // En la carpeta de B, pero solo lo nombra un paciente de A.
        $crossed = $this->photo($patientB, hoursAgo: 48);
        $this->setPhotoPath($patientA, $crossed);
        $orphanA = $this->photo($otherA, hoursAgo: 48);
        $logoA = $this->logo($a, hoursAgo: 48);
        CurrentClinic::run($a, fn () => SystemSetting::create(['key' => 'logo_path', 'value' => $logoA]));
        $orphanLogoB = $this->logo($b, hoursAgo: 48);

        $this->artisan('files:prune-orphans')
            ->expectsOutput('Archivos huérfanos borrados: 3 de 3.')
            ->assertExitCode(0);

        $disk = Storage::disk(PatientPhotoService::DISK);
        $disk->assertExists($usedA);
        $disk->assertExists($usedB);
        $disk->assertMissing($crossed);
        $disk->assertMissing($orphanA);
        Storage::disk(ClinicLogoService::DISK)->assertExists($logoA);
        Storage::disk(ClinicLogoService::DISK)->assertMissing($orphanLogoB);
    }

    /**
     * Si la revisión de una clínica falla (por ejemplo, un enlace simbólico en su carpeta:
     * el disco lanza una excepción al listarla), queda en el log, no se borra nada en esa
     * clínica, ni sus fotos ni su logo, y se sigue con las demás. El comando termina con 1,
     * también con --dry-run.
     */
    public function test_a_clinic_that_fails_is_skipped_and_the_others_are_cleaned(): void
    {
        $failing = $this->clinic;
        $other = $this->createClinic();
        $failingPhoto = $this->photo($this->patientOf($this->clientIn($failing)), hoursAgo: 48);
        $failingLogo = $this->logo($failing, hoursAgo: 48);
        $otherPhoto = $this->photo($this->patientOf($this->clientIn($other)), hoursAgo: 48);

        $fake = Storage::disk(PatientPhotoService::DISK);
        $failingFolder = "clinics/{$failing->id}/patients";
        Storage::set(PatientPhotoService::DISK, new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig(), $failingFolder) extends FilesystemAdapter {
            public function __construct($driver, $adapter, $config, private string $failingFolder)
            {
                parent::__construct($driver, $adapter, $config);
            }

            public function allFiles($directory = null)
            {
                if ($directory === $this->failingFolder) {
                    throw new RuntimeException('Enlace simbólico en la carpeta.');
                }

                return parent::allFiles($directory);
            }
        });
        Log::spy();

        $this->artisan('files:prune-orphans', ['--dry-run' => true])
            ->expectsOutput('Se borraría: patient_photos/' . $otherPhoto)
            ->expectsOutput("No se pudo revisar la clínica {$failing->id}: no se borró nada en ella.")
            ->assertExitCode(1);
        Storage::disk(PatientPhotoService::DISK)->assertExists($otherPhoto);

        $this->artisan('files:prune-orphans')
            ->expectsOutput('Borrado: patient_photos/' . $otherPhoto)
            ->expectsOutput("No se pudo revisar la clínica {$failing->id}: no se borró nada en ella.")
            ->expectsOutput('Archivos huérfanos borrados: 1 de 1.')
            ->assertExitCode(1);

        Storage::disk(PatientPhotoService::DISK)->assertExists($failingPhoto);
        Storage::disk(ClinicLogoService::DISK)->assertExists($failingLogo);
        Storage::disk(PatientPhotoService::DISK)->assertMissing($otherPhoto);
        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context) => str_contains($message, 'no se borró nada') && $context['clinic_id'] === $failing->id && $context['exception'] instanceof RuntimeException
        )->twice();
    }

    /**
     * Un borrado que falla queda en el log, la salida lo dice y el comando termina con 1.
     */
    public function test_a_failed_delete_is_logged_and_the_command_fails(): void
    {
        $patient = $this->patientOf($this->clientIn($this->clinic));
        $orphan = $this->photo($patient, hoursAgo: 48);
        $fake = Storage::disk(PatientPhotoService::DISK);
        Storage::set(PatientPhotoService::DISK, new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends FilesystemAdapter {
            public function delete($paths)
            {
                return false;
            }
        });
        Log::spy();

        $this->artisan('files:prune-orphans')
            ->expectsOutput('No se pudo borrar: patient_photos/' . $orphan)
            ->expectsOutput('Archivos huérfanos borrados: 0 de 1.')
            ->assertExitCode(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'No se pudo borrar un archivo huérfano.' && $context['path'] === $orphan)->once();
    }

    public function test_it_is_scheduled_daily_at_3(): void
    {
        $events = array_filter(app(Schedule::class)->events(), fn ($event) => str_contains($event->command ?? '', 'files:prune-orphans'));

        $this->assertCount(1, $events);
        $this->assertSame('0 3 * * *', array_values($events)[0]->expression);
    }

    private function photo(Patient $patient, int $hoursAgo): string
    {
        return $this->putOld(PatientPhotoService::DISK, PatientPhotoService::folder($patient) . Str::uuid() . '.png', $hoursAgo);
    }

    private function logo(Clinic $clinic, int $hoursAgo): string
    {
        return $this->putOld(ClinicLogoService::DISK, ClinicLogoService::folder($clinic->id) . Str::uuid() . '.png', $hoursAgo);
    }

    /**
     * Un archivo con su fecha de modificación de hace $hoursAgo horas.
     */
    private function putOld(string $disk, string $path, int $hoursAgo): string
    {
        Storage::disk($disk)->put($path, 'png');
        touch(Storage::disk($disk)->path($path), now()->subHours($hoursAgo)->getTimestamp());

        return $path;
    }

    private function setPhotoPath(Patient $patient, string $path): void
    {
        DB::table('patients')->where('id', $patient->id)->update(['photo_path' => $path]);
    }

    /**
     * Los de la lista que siguen en el disco, en el mismo orden.
     *
     * @param list<string> $paths
     * @return list<string>
     */
    private function files(string $disk, array $paths): array
    {
        return array_values(array_filter($paths, fn (string $path) => Storage::disk($disk)->exists($path)));
    }

    /**
     * @return list<string> disco/ruta de todo archivo de los dos discos, ordenados
     */
    private function allFiles(): array
    {
        $files = [];

        foreach ([PatientPhotoService::DISK, ClinicLogoService::DISK] as $disk) {
            foreach (Storage::disk($disk)->allFiles() as $path) {
                $files[] = "{$disk}/{$path}";
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string> los archivos que informa la salida con ese prefijo, ordenados
     */
    private function reported(string $output, string $prefix): array
    {
        $files = [];

        foreach (preg_split('/\R/', $output) as $line) {
            if (str_starts_with($line, $prefix)) {
                $files[] = substr($line, strlen($prefix));
            }
        }

        sort($files);

        return $files;
    }
}
