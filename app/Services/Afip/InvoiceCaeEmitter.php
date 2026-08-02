<?php

namespace App\Services\Afip;

use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Exceptions\Afip\AfipValidationException;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Services\Afip\Data\CaeRequest;
use App\Services\Afip\Data\ComprobanteAsociado;
use App\Support\Afip\ComprobanteResolver;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Único punto de entrada para "emitir esta factura Draft a AFIP". Todo lo
 * demás (Livewire, tests) le habla a esto, nunca directo al gateway.
 */
class InvoiceCaeEmitter
{
    public function __construct(private readonly AfipGatewayInterface $gateway)
    {
    }

    /**
     * @throws \App\Exceptions\Afip\AfipConnectionException
     * @throws \App\Exceptions\Afip\AfipRejectedException
     * @throws \App\Exceptions\Afip\AfipValidationException
     */
    public function emit(Invoice $invoice): Invoice
    {
        if ($invoice->isFiscal) {
            throw new RuntimeException('Esta factura ya tiene CAE, no se puede reemitir.');
        }

        $company = CompanySettings::current();
        $client = $invoice->client;

        // El tipo lo elige el usuario en el switch de Invoices/Create (Remito X /
        // Factura B / Factura A / Devolución), no se auto-detecta acá.
        $tipoComprobante = $invoice->tipo_comprobante_interno->aTipoComprobante();

        if ($tipoComprobante === null) {
            throw new AfipValidationException('Este tipo de documento no se emite a AFIP.');
        }

        ComprobanteResolver::assertEmisorPuedeForzar($company->condicion_iva, $tipoComprobante);

        if ($tipoComprobante->family() === 'A' && $client->tipo_documento !== TipoDocumento::Cuit) {
            throw new AfipValidationException("Para {$tipoComprobante->label()} el cliente necesita tener CUIT cargado.");
        }

        $comprobanteAsociado = null;

        if ($invoice->related_invoice_id !== null) {
            $original = $invoice->relatedInvoice;

            if ($original->creditedTotal + $invoice->total > $original->total) {
                throw new AfipValidationException(
                    'Esta nota de crédito supera el saldo pendiente de la factura original.'
                );
            }

            $comprobanteAsociado = new ComprobanteAsociado(
                tipo: $original->tipo_comprobante,
                puntoVenta: $original->punto_venta,
                numero: $original->numero_comprobante_afip,
            );
        }

        $condicionIvaReceptorId = ComprobanteResolver::condicionIvaReceptorId($client->condicion_iva);

        // Serializa la asignación de número por punto de venta: "leer
        // último autorizado + 1 + pedir CAE" tiene que ser una unidad
        // atómica de nuestro lado, o dos emisiones simultáneas pueden
        // calcular el mismo próximo número (AFIP rechazaría la segunda,
        // pero mejor evitarlo antes de gastar una llamada de red).
        $lock = Cache::lock("afip:emision:{$company->punto_venta}", 30);

        $cbteNro = null;

        $caeResponse = $lock->block(10, function () use ($invoice, $company, $tipoComprobante, $condicionIvaReceptorId, $comprobanteAsociado, &$cbteNro) {
            // Re-chequeo por si otra request emitió esta misma factura
            // mientras esperábamos el lock.
            if ($invoice->fresh()->cae !== null) {
                throw new RuntimeException('Esta factura ya tiene CAE, no se puede reemitir.');
            }

            $cbteNro = $this->gateway->getLastVoucherNumber($company->punto_venta, $tipoComprobante) + 1;

            $request = new CaeRequest(
                puntoVenta: $company->punto_venta,
                tipoComprobante: $tipoComprobante,
                cbteNro: $cbteNro,
                docTipo: $invoice->client->tipo_documento->afipCode(),
                docNro: $this->docNroPara($invoice->client->tipo_documento, $invoice->client->tax_id),
                impNeto: (float) $invoice->subtotal,
                impIva: (float) $invoice->tax_amount,
                impTotal: (float) $invoice->total,
                condicionIvaReceptorId: $condicionIvaReceptorId,
                comprobanteAsociado: $comprobanteAsociado,
            );

            return $this->gateway->requestCae($request);
        });

        $invoice->forceFill([
            'cae' => $caeResponse->cae,
            'cae_vencimiento' => $caeResponse->caeVencimiento,
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $company->punto_venta,
            'numero_comprobante_afip' => $cbteNro,
            'condicion_iva_receptor_id' => $condicionIvaReceptorId,
            'condicion_iva_emisor' => $company->condicion_iva->value,
            'afip_observaciones' => $caeResponse->observaciones !== [] ? implode(' | ', $caeResponse->observaciones) : null,
            'afip_response' => $caeResponse->raw,
            'emitted_at' => now(),
        ])->save();

        return $invoice->fresh();
    }

    private function docNroPara(TipoDocumento $tipo, ?string $taxId): string
    {
        if ($tipo === TipoDocumento::SinIdentificar || ! $taxId) {
            return '0';
        }

        return preg_replace('/\D/', '', $taxId) ?: '0';
    }
}
