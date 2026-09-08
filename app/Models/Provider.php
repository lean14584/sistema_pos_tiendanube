<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'phone', 'address', 'tax_id', 'tipo_documento', 'opening_balance', 'opening_balance_date'])]
class Provider extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProviderPayment::class);
    }

    /**
     * Líneas de "debe" para la cuenta corriente: una por compra no
     * borrador, más el saldo de apertura si lo hay (migración desde otro
     * sistema). Espejo de Client::debitLines(), usado tanto por
     * saldoCuentaCorriente() como por la pantalla de cuenta corriente y el
     * PDF de resumen para que los tres muestren el mismo número.
     *
     * @return Collection<int, array{date: string, label: string, description: ?string, amount: float, purchase: ?Purchase}>
     */
    public function debitLines(): Collection
    {
        $this->loadMissing(['purchases' => fn ($q) => $q->whereNot('status', 'draft')->with('items', 'payments')]);

        $lineas = $this->purchases
            ->map(fn (Purchase $p) => [
                'date' => $p->issue_date->toDateString(),
                'label' => $p->number,
                'description' => null,
                'amount' => (float) $p->total - (float) $p->payments->sum('amount'),
                'purchase' => $p,
            ])
            ->values();

        return $this->conLineaDeApertura($lineas);
    }

    private function conLineaDeApertura(Collection $lineas): Collection
    {
        if ($this->opening_balance === null || (float) $this->opening_balance === 0.0) {
            return $lineas;
        }

        return $lineas->push([
            'date' => ($this->opening_balance_date ?? now())->toDateString(),
            'label' => 'Saldo inicial (migración)',
            'description' => 'Saldo inicial (migración)',
            'amount' => (float) $this->opening_balance,
            'purchase' => null,
        ]);
    }

    /**
     * Saldo actual de cuenta corriente (lo que le debemos): débitos de
     * debitLines() —incluye el saldo de apertura si lo hay— menos los
     * pagos a cuenta. Espejo de Client::saldoCuentaCorriente().
     */
    public function saldoCuentaCorriente(): float
    {
        $this->loadMissing('payments');

        $debito = $this->debitLines()->sum('amount');
        $pagado = (float) $this->payments->sum('amount');

        return round($debito - $pagado, 2);
    }
}
