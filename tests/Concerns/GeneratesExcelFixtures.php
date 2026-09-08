<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Genera un .xlsx real (no un fake genérico) para que IOFactory::load() lo pueda leer, usado por los tests de import. */
trait GeneratesExcelFixtures
{
    private function excel(array $filas, string $nombreArchivo = 'datos.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();

        foreach ($filas as $numFila => $fila) {
            foreach ($fila as $numCol => $valor) {
                $hoja->setCellValue([$numCol + 1, $numFila + 1], $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        // UploadedFile::fake() es lo que Livewire sabe manejar en tests (trae
        // metadata propia que su wiring interno necesita); le reemplazamos el
        // contenido aleatorio por el .xlsx real que acabamos de generar.
        $fake = UploadedFile::fake()->create($nombreArchivo, 1);
        file_put_contents($fake->getRealPath(), file_get_contents($ruta));

        return $fake;
    }
}
