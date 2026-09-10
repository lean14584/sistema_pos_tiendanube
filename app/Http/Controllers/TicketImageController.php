<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\TicketPrinterService;
use App\Support\CurrentSucursal;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TicketImageController extends Controller
{
    public function __invoke(Invoice $invoice, TicketPrinterService $service): Response
    {
        // Mismo chequeo que InvoicePdfController: sin esto, cualquiera podía
        // pedir el ticket de una factura de otra sucursal con solo cambiar
        // el id en la URL.
        abort_unless(
            Auth::user()?->esAdminGlobal() || $invoice->sucursal_id === CurrentSucursal::id(),
            403,
            'No podés ver el ticket de una factura de otra sucursal.'
        );

        return response($service->renderPng($invoice))->header('Content-Type', 'image/png');
    }
}
