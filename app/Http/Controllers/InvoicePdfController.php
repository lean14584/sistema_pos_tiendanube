<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Services\Afip\QrPayloadBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class InvoicePdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Invoice $invoice)
    {
        $invoice->load('client', 'items', 'payments');

        $qrImage = $invoice->isFiscal
            ? base64_encode((new PngWriter)->write(new QrCode(QrPayloadBuilder::url($invoice)))->getString())
            : null;

        $company = CompanySettings::current();
        $logoPath = $company->logo_path ? storage_path('app/public/'.$company->logo_path) : null;

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'qrImage' => $qrImage,
            'company' => $company,
            'logoPath' => $logoPath && file_exists($logoPath) ? $logoPath : null,
        ]);

        return $pdf->download("{$invoice->number}.pdf");
    }
}
