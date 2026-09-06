<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\StockTransfer;
use App\Support\CurrentSucursal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class StockTransferPdfController extends Controller
{
    public function __invoke(StockTransfer $transfer)
    {
        // Mismo criterio que StockTransfers\Show::puedeVer(): el PDF es un
        // endpoint aparte y no heredaba ese chequeo.
        $puedeVer = Auth::user()?->esAdminGlobal()
            || CurrentSucursal::id() === $transfer->from_sucursal_id
            || CurrentSucursal::id() === $transfer->to_sucursal_id;

        abort_unless($puedeVer, 403, 'No podés ver un envío de otra sucursal.');

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
