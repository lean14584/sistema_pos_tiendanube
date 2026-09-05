<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Combina débitos (facturas/compras) y créditos (cobros/pagos) en una sola
 * línea de tiempo con saldo corriente. Misma fórmula que ya usa
 * resources/views/components/current-account.blade.php para la pantalla;
 * centralizada acá para que el PDF de cuenta corriente use exactamente los
 * mismos números.
 */
class AccountLedger
{
    /**
     * @param  Collection<int, array{date: string, label: string, amount: float}>  $debits
     * @param  Collection<int, object{date: Carbon, method: object, amount: mixed, notes: ?string}>  $payments
     * @return Collection<int, array{date: string, description: string, debit: float, credit: float, balance: float}>
     */
    public static function build(Collection $debits, Collection $payments, string $debitLabel, string $paymentLabel): Collection
    {
        // `amount` puede venir con signo negativo (Nota de Crédito/Devolución):
        // esas líneas van a la columna "Haber", no a "Debe".
        $movements = $debits->map(function ($d) use ($debitLabel) {
            $amount = (float) $d['amount'];

            return [
                'date' => $d['date'],
                'description' => "{$debitLabel} {$d['label']}",
                'debit' => max($amount, 0.0),
                'credit' => max(-$amount, 0.0),
            ];
        })->merge(
            $payments->map(fn ($p) => [
                'date' => $p->date->toDateString(),
                'description' => "{$paymentLabel} · {$p->method->label()}".($p->notes ? " · {$p->notes}" : ''),
                'debit' => 0.0,
                'credit' => (float) $p->amount,
            ])
        )->sortBy('date')->values();

        $running = 0.0;

        return $movements->map(function ($m) use (&$running) {
            $running += $m['debit'] - $m['credit'];
            $m['balance'] = $running;

            return $m;
        });
    }
}
