<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\CurrentSucursal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class TicketPrintController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        abort_unless(
            Auth::user()?->esAdminGlobal() || $invoice->sucursal_id === CurrentSucursal::id(),
            403,
            'No podés imprimir el ticket de una factura de otra sucursal.'
        );

        return view('ticket.print', ['invoice' => $invoice]);
    }
}
