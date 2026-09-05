<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Provider;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Support\Collection;

/**
 * Construye el PDF de "resumen de cuenta corriente" (facturas/compras +
 * cobros/pagos con saldo corriente), tanto para la descarga
 * (Client|ProviderAccountStatementController) como para el envío por email
 * (Client|ProviderAccountStatementMail). Mismo criterio que InvoicePdfBuilder.
 */
class AccountStatementPdfBuilder
{
    public function forClient(Client $client): DomPDF
    {
        $payments = $client->payments()->orderBy('date')->get();

        return $this->build('Cliente', $client->name, AccountLedger::build($client->debitLines(), $payments, 'Factura', 'Cobro'), 'Nos debe');
    }

    public function forProvider(Provider $provider): DomPDF
    {
        $purchases = $provider->purchases()->whereNot('status', 'draft')->with('items', 'payments')->get();
        $payments = $provider->payments()->orderBy('date')->get();

        $debits = $purchases->map(fn ($p) => [
            'date' => $p->issue_date->toDateString(),
            'label' => $p->number,
            'amount' => (float) $p->total - (float) $p->payments->sum('amount'),
        ]);

        return $this->build('Proveedor', $provider->name, AccountLedger::build($debits, $payments, 'Compra', 'Pago'), 'Les debemos');
    }

    public function binaryForClient(Client $client): string
    {
        return $this->forClient($client)->output();
    }

    public function binaryForProvider(Provider $provider): string
    {
        return $this->forProvider($provider)->output();
    }

    /** @param  Collection<int, array{date: string, description: string, debit: float, credit: float, balance: float}>  $movements */
    private function build(string $subjectLabel, string $subjectName, Collection $movements, string $balanceOwedLabel): DomPDF
    {
        $company = CompanySettings::current();
        $logoPath = $company->logo_path ? storage_path('app/public/'.$company->logo_path) : null;
        $saldo = (float) ($movements->last()['balance'] ?? 0.0);

        return Pdf::loadView('pdf.cuenta-corriente', [
            'subjectLabel' => $subjectLabel,
            'subjectName' => $subjectName,
            'movements' => $movements,
            'company' => $company,
            'logoPath' => $logoPath && file_exists($logoPath) ? $logoPath : null,
            'saldo' => $saldo,
            'balanceOwedLabel' => $balanceOwedLabel,
            'generatedAt' => now(),
        ]);
    }
}
