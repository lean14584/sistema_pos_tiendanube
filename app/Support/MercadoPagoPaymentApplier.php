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
 *
 * El webhook de MP (POST público, sin sesión de navegador) no tiene ningún
 * usuario autenticado — CashLinker::linkInvoicePayment() no encuentra caja
 * abierta de auth()->id() y no hace nada (return silencioso, por diseño).
 * Si el webhook gana la carrera contra el polling de Invoices\Show y crea
 * el InvoicePayment primero, ese cobro quedaba invisible para el arqueo
 * PARA SIEMPRE: el guard de "ya existe el pago" también saltaba el intento
 * de linkear caja en la llamada siguiente (la del polling, que sí corre
 * autenticado). Por eso el link de caja se reintenta en CADA llamada a
 * apply() sin importar si el pago ya existía — CashLinker::linkInvoicePayment
 * es idempotente por su cuenta (chequea el source_id), así que la primera
 * llamada que corra con un usuario logueado (normalmente el polling) es la
 * que termina de anotarlo, sea cual sea el orden en que lleguen las dos.
 */
class MercadoPagoPaymentApplier
{
    public static function apply(Invoice $invoice): void
    {
        $payment = DB::transaction(function () use ($invoice) {
            $payment = $invoice->payments()
                ->where('method', PaymentMethod::MercadoPago->value)
                ->first();

            if (! $payment) {
                $payment = $invoice->payments()->create([
                    'method' => PaymentMethod::MercadoPago->value,
                    'amount' => round((float) $invoice->total, 2),
                ]);
            }

            if ($invoice->status !== InvoiceStatus::Paid) {
                $invoice->update(['status' => InvoiceStatus::Paid->value]);
            }

            return $payment;
        });

        CashLinker::linkInvoicePayment($invoice, $payment, $invoice->sucursal_id);

        $invoice->refresh();
    }
}
