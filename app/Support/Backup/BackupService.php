<?php

namespace App\Support\Backup;

use App\Models\CompanySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Genera el respaldo completo del sistema (base de datos + archivos subidos)
 * en un único .zip. Lo usan tanto el botón de descarga como la tarea
 * programada de respaldo automático.
 */
class BackupService
{
    /**
     * Crea el .zip en una ruta temporal y devuelve su path. El que llama se
     * encarga de moverlo/enviarlo y borrarlo.
     */
    public function generarEnTemp(): string
    {
        return match (config('database.default')) {
            'sqlite' => $this->generarDesdeSqlite(),
            'mysql' => $this->generarDesdeMysql(),
            default => throw new RuntimeException('El respaldo automático no soporta el driver de base de datos configurado ("'.config('database.default').'").'),
        };
    }

    private function generarDesdeSqlite(): string
    {
        $dbPath = config('database.connections.sqlite.database');

        if (! is_string($dbPath) || ($dbPath !== ':memory:' && ! file_exists($dbPath))) {
            throw new RuntimeException('No se encontró el archivo de base de datos SQLite.');
        }

        // Snapshot consistente aunque haya escrituras en curso.
        $snapshot = tempnam(sys_get_temp_dir(), 'db-').'.sqlite';
        @unlink($snapshot);
        DB::connection('sqlite')->statement("VACUUM INTO '".str_replace("'", "''", $snapshot)."'");

        $zipPath = $this->zipDbYUploads($snapshot, 'database.sqlite');

        @unlink($snapshot);

        return $zipPath;
    }

    /**
     * Igual que en posOfflineDos: mysqldump con --single-transaction (no
     * bloquea las tablas InnoDB mientras se genera, así el POS puede seguir
     * vendiendo durante el respaldo).
     */
    private function generarDesdeMysql(): string
    {
        $conn = config('database.connections.mysql');
        $dumpPath = tempnam(sys_get_temp_dir(), 'db-').'.sql';

        $mysqldump = config('backups.mysqldump_path', 'mysqldump');

        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s %s --single-transaction --routines --result-file=%s %s',
            escapeshellarg($mysqldump),
            escapeshellarg((string) $conn['host']),
            escapeshellarg((string) $conn['port']),
            escapeshellarg((string) $conn['username']),
            ! empty($conn['password']) ? '--password='.escapeshellarg((string) $conn['password']) : '',
            escapeshellarg($dumpPath),
            escapeshellarg((string) $conn['database']),
        );

        exec($cmd.' 2>&1', $salida, $codigo);

        if ($codigo !== 0 || ! file_exists($dumpPath) || filesize($dumpPath) === 0) {
            @unlink($dumpPath);
            throw new RuntimeException('mysqldump falló: '.implode(' ', $salida) ?: 'sin salida (¿está mysqldump en el PATH? configurá BACKUP_MYSQLDUMP_PATH si no).');
        }

        $zipPath = $this->zipDbYUploads($dumpPath, 'database.sql');

        @unlink($dumpPath);

        return $zipPath;
    }

    private function zipDbYUploads(string $dbFilePath, string $nombreEnZip): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'backup-').'.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($dbFilePath, $nombreEnZip);

        $uploads = storage_path('app/public');
        if (is_dir($uploads)) {
            foreach (File::allFiles($uploads) as $file) {
                $zip->addFile($file->getPathname(), 'uploads/'.$file->getRelativePathname());
            }
        }

        $zip->addFromString('LEEME.txt', $this->manifiesto($nombreEnZip));
        $zip->close();

        return $zipPath;
    }

    public function nombreArchivo(): string
    {
        return 'respaldo-'.str($this->nombreEmpresa())->slug().'-'.now()->format('Ymd-His').'.zip';
    }

    /**
     * Deja solo los $keep respaldos más nuevos en $dir, borrando los viejos.
     * Devuelve cuántos borró.
     */
    public function rotar(string $dir, int $keep): int
    {
        if ($keep < 1 || ! is_dir($dir)) {
            return 0;
        }

        $zips = collect(glob($dir.DIRECTORY_SEPARATOR.'respaldo-*.zip') ?: [])
            ->sortByDesc(fn (string $f) => filemtime($f))
            ->values();

        $aBorrar = $zips->slice($keep);
        $aBorrar->each(fn (string $f) => @unlink($f));

        return $aBorrar->count();
    }

    private function manifiesto(string $nombreDbEnZip): string
    {
        $empresa = $this->nombreEmpresa();
        $fecha = now()->format('d/m/Y H:i');

        $conteos = [
            'Facturas' => DB::table('invoices')->count(),
            'Clientes' => DB::table('clients')->count(),
            'Productos' => DB::table('products')->count(),
            'Compras' => DB::table('purchases')->count(),
        ];

        $lineas = [
            "RESPALDO DEL SISTEMA - {$empresa}",
            "Fecha: {$fecha}",
            'Sistema: '.config('app.name'),
            '',
            'Contenido:',
            "  - {$nombreDbEnZip}  (base de datos completa)",
            '  - uploads/         (archivos subidos: logo, certificados, etc.)',
            '',
            'Registros al momento del respaldo:',
        ];

        foreach ($conteos as $etiqueta => $cantidad) {
            $lineas[] = "  - {$etiqueta}: {$cantidad}";
        }

        $lineas[] = '';
        $lineas[] = $nombreDbEnZip === 'database.sql'
            ? 'Para restaurar: mysql -u usuario -p nombre_de_la_base < database.sql'
            : 'Para restaurar: reemplazar el archivo database.sqlite del sistema por el de este respaldo.';
        $lineas[] = 'Copiar además la carpeta uploads/ a storage/app/public.';
        $lineas[] = '';
        $lineas[] = 'IMPORTANTE: guardá este archivo en otro dispositivo (pendrive, nube).';

        return implode("\r\n", $lineas);
    }

    private function nombreEmpresa(): string
    {
        $empresa = CompanySettings::current();

        return $empresa->nombre_fantasia ?: ($empresa->razon_social ?: config('app.name'));
    }
}
