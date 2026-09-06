<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\CurrentSucursal;
use App\Support\InvoicePdfBuilder;
use Illuminate\Support\Facades\Auth;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice, InvoicePdfBuilder $builder)
    {
        // La ruta solo exige el módulo "invoices" (todos los roles lo tienen);
        // sin este chequeo, cualquiera podía descargar el PDF de una factura
        // de otra sucursal con solo cambiar el id en la URL.
        abort_unless(
            Auth::user()?->esAdminGlobal() || $invoice->sucursal_id === CurrentSucursal::id(),
            403,
            'No podés descargar una factura de otra sucursal.'
        );

        return $builder->pdf($invoice)->download("{$invoice->number}.pdf");
    }
}
