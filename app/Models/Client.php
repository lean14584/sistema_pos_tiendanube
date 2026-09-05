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
     * Líneas de "debe" para la cuenta corriente: una por comprobante no
     * borrador, con `amount` ya con signo — Nota de Crédito y Devolución
     * restan en vez de sumar (antes se sumaban igual que una Factura, lo
     * que hacía subir la deuda del cliente en vez de bajarla). También
     * excluye el Remito X que ya fue facturado, para no contar el mismo
     * envío dos veces (una como remito, otra como la factura generada).
     *
     * Usado tanto por saldoCuentaCorriente() como por la pantalla de cuenta
     * corriente y el PDF de resumen, para que los tres muestren el mismo
     * número.
     *
     * @return Collection<int, array{date: string, label: string, amount: float, invoice: Invoice}>
     */
    public function debitLines(): Collection
    {
        $this->loadMissing(['invoices' => fn ($q) => $q->whereNot('status', 'draft')->with('items', 'payments')]);

        $remitosYaFacturados = $this->invoices->pluck('remito_id')->filter()->all();

        return $this->invoices
            ->reject(fn (Invoice $i) => $i->esRemito() && in_array($i->id, $remitosYaFacturados, true))
            ->map(fn (Invoice $i) => [
                'date' => $i->issue_date->toDateString(),
                'label' => $i->number,
                'amount' => $i->signoDeuda() * ((float) $i->total - (float) $i->payments->sum('amount')),
                'invoice' => $i,
            ])
            ->values();
    }

    /**
     * Saldo actual de cuenta corriente (lo que nos debe): débitos de
     * debitLines() menos los cobros a cuenta.
     */
    public function saldoCuentaCorriente(): float
    {
        $this->loadMissing('payments');

        $debito = $this->debitLines()->sum('amount');
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
     * Se guarda como STRING JSON, no como objetos/array vía serialize()
     * nativo de PHP. Dos vueltas anteriores (cachear `Client`, después
     * `stdClass`, después un array plano de `stdClass`) seguían dando
     * __PHP_Incomplete_Class / "tried to access a property on an incomplete
     * object" de forma intermitente: el store de caché "database" pasa por
     * `serialize()`/`unserialize()` nativos de PHP en el viaje de ida y
     * vuelta por MySQL, y algo en ese camino (concurrencia, o los acentos
     * del nombre del cliente) termina corrompiendo el grafo de objetos.
     * JSON no tiene ese modo de falla: `json_decode()` no reconstruye clases
     * a partir de un conteo de propiedades como `unserialize()`, así que no
     * puede quedar "incompleto". Cachear un string JSON reduce el valor
     * cacheado a algo trivial de serializar (`s:N:"[...]"`, un string plano).
     *
     * @return Collection<int, object{id: int, name: string}>
     */
    public static function forSelectCached(): Collection
    {
        $json = Cache::remember(
            'clients:select-list-v4',
            now()->addSeconds(60),
            fn () => self::orderBy('name')->get(['id', 'name'])->toJson(),
        );

        return collect(json_decode($json));
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
