<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone', 'address', 'tax_id', 'tipo_documento'])]
class Provider extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
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
     * Saldo actual de cuenta corriente (lo que le debemos): compras no
     * borrador menos lo pagado al momento, menos los pagos a cuenta.
     * Espejo de Client::saldoCuentaCorriente().
     */
    public function saldoCuentaCorriente(): float
    {
        $this->loadMissing(['purchases' => fn ($q) => $q->whereNot('status', 'draft')->with('items', 'payments'), 'payments']);

        $debito = $this->purchases->sum(fn ($p) => (float) $p->total - (float) $p->payments->sum('amount'));
        $pagado = (float) $this->payments->sum('amount');

        return round($debito - $pagado, 2);
    }
}
