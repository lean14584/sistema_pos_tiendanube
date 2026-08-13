<?php

namespace App\Http\Controllers;

use App\Models\ClientPayment;
use App\Models\CompanySettings;
use App\Support\InvoiceNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;

class ReciboPdfController extends Controller
{
    public function __invoke(ClientPayment $payment)
    {
        $client = $payment->client;

        // Saldo actual del cliente: facturas (no borrador) menos lo pagado al
        // momento de cada venta, menos todos los cobros a cuenta corriente.
        $invoices = $client->invoices()->whereNot('status', 'draft')->with('items', 'payments')->get();
        $debito = $invoices->sum(fn ($i) => (float) $i->total - (float) $i->payments->sum('amount'));
        $cobrado = (float) $client->payments()->sum('amount');
        $saldo = round($debito - $cobrado, 2);

        $company = CompanySettings::current();
        $logoPath = $company->logo_path ? storage_path('app/public/'.$company->logo_path) : null;

        $numero = InvoiceNumberGenerator::puntoVenta().'-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdf.recibo', [
            'payment' => $payment,
            'client' => $client,
            'company' => $company,
            'numero' => $numero,
            'saldo' => $saldo,
            'logoPath' => $logoPath && file_exists($logoPath) ? $logoPath : null,
        ]);

        return $pdf->download("Recibo-{$numero}.pdf");
    }
}
