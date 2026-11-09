<?php

namespace App\Console\Commands;

use App\Services\OrphanFileService;
use Illuminate\Console\Command;

/**
 * Limpieza de archivos huérfanos (FASE 2, entrega 2.5; deuda 14): las fotos de
 * pacientes y los logos que no son el photo_path de ningún paciente (también
 * eliminado) ni el logo_path de su clínica, con más de 24 horas. Solo mira las carpetas
 * de fotos y de logos de cada clínica (OrphanFileService). Con --dry-run informa lo
 * mismo que borraría, sin borrar.
 *
 * Programado a diario a las 03:00 (routes/console.php); en producción necesita el cron
 * del scheduler (FASE 10).
 */
class PruneOrphanFilesCommand extends Command
{
    protected $signature = 'files:prune-orphans
        {--dry-run : Solo listar los archivos huérfanos, sin borrarlos}';

    protected $description = 'Limpieza de las fotos de pacientes y los logos huérfanos de más de 24 horas';

    public function handle(OrphanFileService $orphans): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $orphans->prune($dryRun);
        $failed = array_map(fn (array $orphan) => "{$orphan['disk']}/{$orphan['path']}", $result['failed']);

        foreach ($result['orphans'] as $orphan) {
            $file = "{$orphan['disk']}/{$orphan['path']}";
            $this->line(match (true) {
                $dryRun => "Se borraría: {$file}",
                in_array($file, $failed, true) => "No se pudo borrar: {$file}",
                default => "Borrado: {$file}",
            });
        }

        $count = count($result['orphans']);

        if ($dryRun) {
            $this->info("Archivos huérfanos que se borrarían: {$count} (--dry-run, no se borró nada).");

            return self::SUCCESS;
        }

        $this->info('Archivos huérfanos borrados: ' . ($count - count($failed)) . " de {$count}.");

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
