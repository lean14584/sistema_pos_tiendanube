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
        $dbPath = config('database.connections.sqlite.database');

        if (config('database.default') !== 'sqlite' || ! is_string($dbPath) || ($dbPath !== ':memory:' && ! file_exists($dbPath))) {
            throw new RuntimeException('El respaldo automático está disponible solo para bases SQLite existentes.');
        }

        // Snapshot consistente aunque haya escrituras en curso.
        $snapshot = tempnam(sys_get_temp_dir(), 'db-').'.sqlite';
        @unlink($snapshot);
        DB::connection('sqlite')->statement("VACUUM INTO '".str_replace("'", "''", $snapshot)."'");

        $zipPath = tempnam(sys_get_temp_dir(), 'backup-').'.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($snapshot, 'database.sqlite');

        $uploads = storage_path('app/public');
        if (is_dir($uploads)) {
            foreach (File::allFiles($uploads) as $file) {
                $zip->addFile($file->getPathname(), 'uploads/'.$file->getRelativePathname());
            }
        }

        $zip->addFromString('LEEME.txt', $this->manifiesto());
        $zip->close();

        @unlink($snapshot);

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

    private function manifiesto(): string
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
            '  - database.sqlite  (base de datos completa)',
            '  - uploads/         (archivos subidos: logo, certificados, etc.)',
            '',
            'Registros al momento del respaldo:',
        ];

        foreach ($conteos as $etiqueta => $cantidad) {
            $lineas[] = "  - {$etiqueta}: {$cantidad}";
        }

        $lineas[] = '';
        $lineas[] = 'Para restaurar: reemplazar el archivo database.sqlite del sistema por';
        $lineas[] = 'el de este respaldo, y copiar la carpeta uploads/ a storage/app/public.';
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
