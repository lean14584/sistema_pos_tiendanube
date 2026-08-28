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
     * Se cachea un array PLANO (no una Collection ni modelos Eloquent).
     * El store de caché "database" en MySQL solo hace base64 si el
     * serialize() resultante tiene bytes NUL (ver DatabaseStore::serialize());
     * Collection tiene una propiedad protegida ($items), y serialize() de PHP
     * marca las propiedades protegidas con bytes NUL ("\0*\0items"). Con
     * pedidos concurrentes escribiendo la misma clave, esos NUL corrompen el
     * valor guardado (unserialize() devuelve false o un __PHP_Incomplete_Class)
     * — reproducido de forma consistente disparando varios procesos en
     * paralelo. Un array de stdClass con propiedades públicas serializa sin
     * ningún byte NUL, así que no le pega el bug. Lo que se GUARDA en caché
     * es ese array; acá se envuelve en collect() recién al devolverlo, para
     * que los callers puedan seguir usando firstWhere() etc. sin que esa
     * envoltura llegue a serializarse.
     *
     * @return Collection<int, object{id: int, name: string}>
     */
    public static function forSelectCached(): Collection
    {
        return collect(Cache::remember(
            'clients:select-list-v3',
            now()->addSeconds(60),
            fn () => self::orderBy('name')->get(['id', 'name'])->map(fn (self $c) => (object) ['id' => $c->id, 'name' => $c->name])->values()->all(),
        ));
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
