<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\CondicionIva;
use App\Enums\TipoDocumento;
use App\Observers\ClientObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[ObservedBy(ClientObserver::class)]
#[Fillable(['name', 'email', 'phone', 'address', 'tax_id', 'condicion_iva', 'tipo_documento', 'price_list_id', 'credit_limit', 'tiendanube_customer_id'])]
class Client extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'condicion_iva' => CondicionIva::class,
            'tipo_documento' => TipoDocumento::class,
            'credit_limit' => 'decimal:2',
        ];
    }

    /**
     * Saldo actual de cuenta corriente (lo que nos debe): facturas no
     * borrador menos lo pagado al momento, menos los cobros a cuenta.
     */
    public function saldoCuentaCorriente(): float
    {
        $this->loadMissing(['invoices' => fn ($q) => $q->whereNot('status', 'draft')->with('items', 'payments'), 'payments']);

        $debito = $this->invoices->sum(fn ($i) => (float) $i->total - (float) $i->payments->sum('amount'));
        $cobrado = (float) $this->payments->sum('amount');

        return round($debito - $cobrado, 2);
    }

    /**
     * Si sumar `$nuevaDeuda` a cuenta corriente superaría el límite de
     * crédito, devuelve un mensaje de error; si no (o no hay límite), null.
     */
    public function excesoDeCredito(float $nuevaDeuda): ?string
    {
        $limite = (float) $this->credit_limit;

        if ($limite <= 0 || $nuevaDeuda <= 0.009) {
            return null;
        }

        $nuevoSaldo = $this->saldoCuentaCorriente() + $nuevaDeuda;

        if ($nuevoSaldo > $limite + 0.009) {
            return 'El saldo quedaría en $'.number_format($nuevoSaldo, 2)
                .' y supera el límite de crédito de $'.number_format($limite, 2).' de '.$this->name.'.';
        }

        return null;
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

    /**
     * Lista liviana (solo id + nombre) para los selects de cliente que se
     * repiten en pantallas de alta frecuencia (POS, facturas, presupuestos).
     * Sin esto, cada click en el POS (agregar producto, +/-, pago...) volvía
     * a traer la tabla de clientes entera en cada request. Cacheada 60s:
     * un cliente recién creado puede tardar hasta ese tiempo en aparecer.
     *
     * Devuelve stdClass, NO instancias de Client: con el driver de caché
     * "database" (o file) los valores se guardan con serialize() nativo de
     * PHP, y cachear modelos Eloquent completos es frágil — un deploy que
     * toque la clase (o un desfasaje de autoload entre el momento en que se
     * guardó y el momento en que se lee) puede dejar el valor cacheado
     * corrupto (__PHP_Incomplete_Class). stdClass no tiene ese problema y
     * alcanza para mostrar id+nombre.
     *
     * @return Collection<int, object{id: int, name: string}>
     */
    public static function forSelectCached(): Collection
    {
        return Cache::remember(
            'clients:select-list-v2',
            now()->addSeconds(60),
            fn () => self::orderBy('name')->get(['id', 'name'])->map(fn (self $c) => (object) ['id' => $c->id, 'name' => $c->name])->values(),
        );
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
