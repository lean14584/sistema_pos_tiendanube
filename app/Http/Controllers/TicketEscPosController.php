<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\TicketPrinterService;
use Illuminate\Http\Response;

/**
 * Sin auth: la ruta usa el middleware 'signed', y la URL firmada
 * (URL::temporarySignedRoute, vence en minutos) es la que autoriza el
 * acceso — se genera server-side solo cuando el usuario que ya pasó el
 * chequeo de sucursal aprieta "Imprimir Ticket". El agente local (ver
 * pos-print-agent/) no tiene sesión de navegador para mandar por auth.
 */
class TicketEscPosController extends Controller
{
    public function __invoke(Invoice $invoice, TicketPrinterService $service): Response
    {
        return response($service->renderEscPos($invoice))
            ->header('Content-Type', 'application/octet-stream');
    }
}
