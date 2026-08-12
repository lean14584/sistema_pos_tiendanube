<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Genera un respaldo completo y lo guarda en la carpeta configurada (y en la
 * copia externa, si hay), rotando los viejos. Pensado para correr una vez por
 * día vía el programador de Laravel o una tarea de Windows.
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run {--keep= : Cuántos respaldos conservar (por defecto, el de config)}';

    protected $description = 'Genera un respaldo completo del sistema y lo guarda en disco.';

    public function handle(BackupService $backup): int
    {
        $dir = config('backups.path');
        File::ensureDirectoryExists($dir);

        try {
            $zipTemp = $backup->generarEnTemp();
        } catch (\Throwable $e) {
            $this->error('No se pudo generar el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }

        $nombre = $backup->nombreArchivo();
        $destino = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$nombre;
        File::move($zipTemp, $destino);
        $this->info('Respaldo generado: '.$destino);

        // Copia a la carpeta externa (pendrive / nube), si está configurada.
        $copyTo = config('backups.copy_to');
        if (! empty($copyTo)) {
            try {
                File::ensureDirectoryExists($copyTo);
                File::copy($destino, rtrim($copyTo, '/\\').DIRECTORY_SEPARATOR.$nombre);
                $this->info('Copia externa: '.$copyTo);
            } catch (\Throwable $e) {
                $this->warn('No se pudo copiar a la carpeta externa: '.$e->getMessage());
            }
        }

        $keep = (int) ($this->option('keep') ?? config('backups.keep'));
        $borrados = $backup->rotar($dir, $keep);
        if ($borrados > 0) {
            $this->line("Se borraron {$borrados} respaldo(s) viejo(s) (se conservan {$keep}).");
        }

        return self::SUCCESS;
    }
}
