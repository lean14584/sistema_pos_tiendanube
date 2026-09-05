<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\StockTransfer;
use Barryvdh\DomPDF\Facade\Pdf;

class StockTransferPdfController extends Controller
{
    public function __invoke(StockTransfer $transfer)
    {
        $transfer->load(['items.product', 'fromSucursal', 'toSucursal', 'user', 'receivedBy']);

        $company = CompanySettings::current();
        $logoPath = $company->logo_path ? storage_path('app/public/'.$company->logo_path) : null;

        $pdf = Pdf::loadView('pdf.stock-transfer', [
            'transfer' => $transfer,
            'company' => $company,
            'logoPath' => $logoPath && file_exists($logoPath) ? $logoPath : null,
        ]);

        return $pdf->download("Envio-{$transfer->id}.pdf");
    }
}
