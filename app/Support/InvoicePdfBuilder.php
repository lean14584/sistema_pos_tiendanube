<?php

namespace App\Support;

use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Services\Afip\QrPayloadBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Construye el PDF de una factura. Centralizado acá para que lo usen tanto la
 * descarga (InvoicePdfController) como el envío por email (InvoiceMail).
 */
class InvoicePdfBuilder
{
    public function pdf(Invoice $invoice): DomPDF
    {
        $invoice->load('client', 'items', 'payments');

        $qrImage = $invoice->isFiscal
            ? base64_encode((new PngWriter)->write(new QrCode(QrPayloadBuilder::url($invoice)))->getString())
            : null;

        $company = CompanySettings::current();
        $logoPath = $company->logo_path ? storage_path('app/public/'.$company->logo_path) : null;

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'qrImage' => $qrImage,
            'company' => $company,
            'logoPath' => $logoPath && file_exists($logoPath) ? $logoPath : null,
        ]);
    }

    public function binary(Invoice $invoice): string
    {
        return $this->pdf($invoice)->output();
    }
}
