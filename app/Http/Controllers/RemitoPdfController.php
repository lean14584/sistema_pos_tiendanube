<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Support\CurrentSucursal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RemitoPdfController extends Controller
{
    public function __invoke(Invoice $invoice, Request $request)
    {
        abort_unless(
            Auth::user()?->esAdminGlobal() || $invoice->sucursal_id === CurrentSucursal::id(),
            403,
            'No podés descargar un remito de otra sucursal.'
        );

        abort_if(! $invoice->esRemito(), 404);

        $invoice->load('client', 'items');

        // ?precios=0 imprime la nota de entrega sin importes (para acompañar
        // la mercadería sin mostrar precios).
        $conPrecios = $request->query('precios', '1') !== '0';

        $company = CompanySettings::current();
        $logoPath = $company->logo_path ? storage_path('app/public/'.$company->logo_path) : null;

        $pdf = Pdf::loadView('pdf.remito', [
            'remito' => $invoice,
            'company' => $company,
            'conPrecios' => $conPrecios,
            'logoPath' => $logoPath && file_exists($logoPath) ? $logoPath : null,
        ]);

        return $pdf->download("Remito-{$invoice->number}.pdf");
    }
}
