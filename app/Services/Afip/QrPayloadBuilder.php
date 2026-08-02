<?php

namespace App\Services\Afip;

use App\Models\Invoice;

/**
 * QR obligatorio en comprobantes electrónicos (RG 4892). Todo sale de los
 * campos ya snapshoteados en la factura al momento de emisión — nunca de
 * datos "en vivo" del cliente/empresa, que pueden haber cambiado desde
 * entonces.
 */
final class QrPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Invoice $invoice): array
    {
        return [
            'ver' => 1,
            'fecha' => $invoice->issue_date->format('Y-m-d'),
            'cuit' => (int) config('afip.cuit'),
            'ptoVta' => $invoice->punto_venta,
            'tipoCmp' => $invoice->tipo_comprobante->value,
            'nroCmp' => $invoice->numero_comprobante_afip,
            'importe' => (float) $invoice->total,
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => $invoice->condicion_iva_receptor_id,
            'nroDocRec' => (int) $invoice->client->tax_id,
            'tipoCodAut' => 'E',
            'codAut' => (int) $invoice->cae,
        ];
    }

    public static function url(Invoice $invoice): string
    {
        return 'https://www.afip.gob.ar/fe/qr/?p='.base64_encode(json_encode(self::build($invoice)));
    }
}
