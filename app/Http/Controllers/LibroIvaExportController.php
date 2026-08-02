<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Support\LibroIva\LibroIvaCalculator;
use App\Support\LibroIva\LibroIvaExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class LibroIvaExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $ventas = LibroIvaCalculator::ventas($data['desde'], $data['hasta']);
        $compras = LibroIvaCalculator::compras($data['desde'], $data['hasta']);

        $cuit = CompanySettings::current()->cuit ?: '00000000000';
        $periodo = Carbon::parse($data['hasta'])->format('Ym');

        $files = [
            "LIBRO_IVA_DIGITAL_VENTAS_CBTE_{$cuit}_{$periodo}.txt" => LibroIvaExporter::ventasCbte($ventas),
            "LIBRO_IVA_DIGITAL_VENTAS_ALICUOTAS_{$cuit}_{$periodo}.txt" => LibroIvaExporter::ventasAlicuotas($ventas),
            "LIBRO_IVA_DIGITAL_COMPRAS_CBTE_{$cuit}_{$periodo}.txt" => LibroIvaExporter::comprasCbte($compras),
            "LIBRO_IVA_DIGITAL_COMPRAS_ALICUOTAS_{$cuit}_{$periodo}.txt" => LibroIvaExporter::comprasAlicuotas($compras),
        ];

        $zipPath = tempnam(sys_get_temp_dir(), 'libro-iva-').'.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return response()->download($zipPath, "libro-iva-digital-{$periodo}.zip")->deleteFileAfterSend();
    }
}
