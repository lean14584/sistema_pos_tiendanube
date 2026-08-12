<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Descarga un respaldo completo del sistema: la base de datos (snapshot
 * consistente) más los archivos subidos (logo, certificados, etc.), todo en
 * un único .zip con la fecha en el nombre.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        abort_unless(
            config('database.default') === 'sqlite',
            422,
            'El respaldo automático está disponible solo para bases SQLite.'
        );

        $dbPath = config('database.connections.sqlite.database');
        abort_unless(
            is_string($dbPath) && ($dbPath === ':memory:' || file_exists($dbPath)),
            500,
            'No se encontró el archivo de base de datos.'
        );

        // Snapshot consistente de la base, aunque haya escrituras en curso
        // (VACUUM INTO es la forma recomendada por SQLite para copiar en vivo).
        $snapshot = tempnam(sys_get_temp_dir(), 'db-').'.sqlite';
        @unlink($snapshot); // VACUUM INTO exige que el destino no exista.
        DB::connection('sqlite')->statement("VACUUM INTO '".str_replace("'", "''", $snapshot)."'");

        $zipPath = tempnam(sys_get_temp_dir(), 'backup-').'.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFile($snapshot, 'database.sqlite');

        // Archivos subidos (logo de la empresa, certificados, etc.).
        $uploads = storage_path('app/public');
        if (is_dir($uploads)) {
            foreach (File::allFiles($uploads) as $file) {
                $zip->addFile($file->getPathname(), 'uploads/'.$file->getRelativePathname());
            }
        }

        $zip->addFromString('LEEME.txt', $this->manifiesto());
        $zip->close();

        @unlink($snapshot);

        $nombre = 'respaldo-'.str($this->nombreEmpresa())->slug().'-'.now()->format('Ymd-His').'.zip';

        return response()->download($zipPath, $nombre)->deleteFileAfterSend();
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
