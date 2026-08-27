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
        $cutoff = $payment->created_at;

        // Saldo del cliente al momento de este pago (no el saldo actual): así
        // un recibo ya emitido no cambia por movimientos posteriores. Facturas
        // (no borrador) emitidas hasta ese momento, menos lo pagado en cada
        // una hasta ese momento, menos los cobros a cuenta corriente hasta ese momento.
        $invoices = $client->invoices()
            ->whereNot('status', 'draft')
            ->where('created_at', '<=', $cutoff)
            ->with(['items', 'payments' => fn ($q) => $q->where('created_at', '<=', $cutoff)])
            ->get();
        $debito = $invoices->sum(fn ($i) => (float) $i->total - (float) $i->payments->sum('amount'));
        $cobrado = (float) $client->payments()->where('created_at', '<=', $cutoff)->sum('amount');
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
