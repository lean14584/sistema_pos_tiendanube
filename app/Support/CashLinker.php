<?php

namespace App\Support;

use App\Enums\CashMovementSource;
use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Purchase;
use App\Models\ProviderPayment;
use App\Models\PurchasePayment;

class CashLinker
{
    private static function openSession(): ?CashSession
    {
        return CashSession::where('status', 'open')->first();
    }

    public static function linkClientPayment(ClientPayment $payment): void
    {
        $session = self::openSession();

        if (! $session) {
            return;
        }

        CashMovement::create([
            'session_id' => $session->id,
            'type' => CashMovementType::Ingreso,
            'concept' => "Cobro a {$payment->client->name}",
            'amount' => $payment->amount,
            'source' => CashMovementSource::Venta,
            'source_id' => "client_payment_{$payment->id}",
            'date' => $payment->date,
        ]);
    }

    public static function unlinkClientPayment(ClientPayment $payment): void
    {
        CashMovement::where('source_id', "client_payment_{$payment->id}")->delete();
    }

    public static function linkProviderPayment(ProviderPayment $payment): void
    {
        $session = self::openSession();

        if (! $session) {
            return;
        }

        CashMovement::create([
            'session_id' => $session->id,
            'type' => CashMovementType::Egreso,
            'concept' => "Pago a {$payment->provider->name}",
            'amount' => $payment->amount,
            'source' => CashMovementSource::Compra,
            'source_id' => "provider_payment_{$payment->id}",
            'date' => $payment->date,
        ]);
    }

    public static function unlinkProviderPayment(ProviderPayment $payment): void
    {
        CashMovement::where('source_id', "provider_payment_{$payment->id}")->delete();
    }

    public static function linkInvoicePayment(Invoice $invoice, InvoicePayment $payment): void
    {
        $session = self::openSession();

        if (! $session) {
            return;
        }

        CashMovement::create([
            'session_id' => $session->id,
            'type' => CashMovementType::Ingreso,
            'concept' => "Venta {$invoice->number} · {$invoice->client->name} · {$payment->method->label()}",
            'amount' => $payment->amount,
            'source' => CashMovementSource::Venta,
            'source_id' => "invoice_payment_{$payment->id}",
            'date' => $invoice->issue_date,
        ]);
    }

    /**
     * Devolución: el dinero sale de caja, no entra.
     */
    public static function linkInvoiceRefund(Invoice $invoice, InvoicePayment $payment): void
    {
        $session = self::openSession();

        if (! $session) {
            return;
        }

        CashMovement::create([
            'session_id' => $session->id,
            'type' => CashMovementType::Egreso,
            'concept' => "Devolución {$invoice->number} · {$invoice->client->name}",
            'amount' => $payment->amount,
            'source' => CashMovementSource::Devolucion,
            'source_id' => "invoice_payment_{$payment->id}",
            'date' => $invoice->issue_date,
        ]);
    }

    public static function unlinkInvoicePayment(InvoicePayment $payment): void
    {
        CashMovement::where('source_id', "invoice_payment_{$payment->id}")->delete();
    }

    /**
     * Método de pago cargado al momento de registrar la compra (distinto de
     * ProviderPayment, que salda saldo pendiente de cuenta corriente después).
     */
    public static function linkPurchasePayment(Purchase $purchase, PurchasePayment $payment): void
    {
        $session = self::openSession();

        if (! $session) {
            return;
        }

        CashMovement::create([
            'session_id' => $session->id,
            'type' => CashMovementType::Egreso,
            'concept' => "Compra {$purchase->number} · {$purchase->provider->name} · {$payment->method->label()}",
            'amount' => $payment->amount,
            'source' => CashMovementSource::Compra,
            'source_id' => "purchase_payment_{$payment->id}",
            'date' => $purchase->issue_date,
        ]);
    }

    public static function unlinkPurchasePayment(PurchasePayment $payment): void
    {
        CashMovement::where('source_id', "purchase_payment_{$payment->id}")->delete();
    }
}
