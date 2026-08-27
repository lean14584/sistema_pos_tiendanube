<?php

namespace App\Http\Controllers;

use App\Enums\AlicuotaIva;
use App\Models\Product;
use App\Support\ProductImport\ProductImportFields;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportController extends Controller
{
    /**
     * Mismas columnas (mismo orden, mismas etiquetas) que la pantalla de
     * importación espera — así un archivo exportado, editado y vuelto a
     * subir se auto-empareja solo, sin tener que mapear de nuevo.
     */
    public function __invoke(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();

        $campos = array_keys(ProductImportFields::FIELDS);
        $columna = 1;
        foreach ($campos as $campo) {
            $hoja->setCellValue([$columna++, 1], ProductImportFields::FIELDS[$campo]['label']);
        }

        $fila = 2;
        Product::with('category')->orderBy('name')->chunk(200, function ($productos) use ($hoja, $campos, &$fila) {
            foreach ($productos as $producto) {
                $columna = 1;
                foreach ($campos as $campo) {
                    $valor = match ($campo) {
                        'sku' => $producto->sku,
                        'category' => $producto->category?->name,
                        'min_stock' => $producto->min_stock,
                        'cost_price' => $producto->cost_price,
                        'iva_rate' => AlicuotaIva::normalizar($producto->iva_rate),
                        'price' => $producto->price,
                        'stock' => $producto->stock,
                        'description' => $producto->description,
                        'name' => $producto->name,
                    };
                    $hoja->setCellValue([$columna++, $fila], $valor);
                }
                $fila++;
            }
        });

        foreach (range(1, count($campos)) as $columna) {
            $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
        }

        $nombre = 'productos-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
