<?php

namespace App\Models;

use App\Enums\CondicionIva;
use App\Enums\TipoDocumento;
use App\Observers\ClientObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ClientObserver::class)]
#[Fillable(['name', 'email', 'phone', 'address', 'tax_id', 'condicion_iva', 'tipo_documento', 'price_list_id', 'tiendanube_customer_id'])]
class Client extends Model
{
    protected function casts(): array
    {
        return [
            'condicion_iva' => CondicionIva::class,
            'tipo_documento' => TipoDocumento::class,
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public static function consumidorFinal(): self
    {
        return self::firstOrCreate(
            ['name' => 'Consumidor Final'],
            [
                'email' => 'consumidor.final@localhost',
                'condicion_iva' => CondicionIva::ConsumidorFinal,
                'tipo_documento' => TipoDocumento::SinIdentificar,
            ]
        );
    }
}
