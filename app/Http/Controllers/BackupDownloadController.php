<?php

namespace App\Http\Controllers;

use App\Support\Backup\BackupService;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga un respaldo completo del sistema (base de datos + archivos subidos)
 * en un único .zip con la fecha en el nombre.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(BackupService $backup): BinaryFileResponse
    {
        try {
            $zipPath = $backup->generarEnTemp();
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return response()->download($zipPath, $backup->nombreArchivo())->deleteFileAfterSend();
    }
}
