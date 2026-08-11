<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Marca una factura como pagada por Mercado Pago (QR), registrando el pago y
 * el movimiento de caja. Idempotente: si ya se registró el pago de MP, no lo
 * duplica (el webhook y el polling pueden dispararse casi a la vez).
 */
class MercadoPagoPaymentApplier
{
    public static function apply(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $already = $invoice->payments()
                ->where('method', PaymentMethod::MercadoPago->value)
                ->exists();

            if (! $already) {
                $payment = $invoice->payments()->create([
                    'method' => PaymentMethod::MercadoPago->value,
                    'amount' => round((float) $invoice->total, 2),
                ]);
                CashLinker::linkInvoicePayment($invoice, $payment);
            }

            if ($invoice->status !== InvoiceStatus::Paid) {
                $invoice->update(['status' => InvoiceStatus::Paid->value]);
            }
        });

        $invoice->refresh();
    }
}
