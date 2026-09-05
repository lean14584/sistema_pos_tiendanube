<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasBillingTotals;
use App\Concerns\HasOverdueStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TipoComprobante;
use App\Enums\TipoComprobanteInterno;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'number', 'client_id', 'issue_date', 'due_date', 'tax_rate', 'notes', 'status',
    'tipo_comprobante_interno', 'related_invoice_id', 'remito_id', 'afecta_stock', 'mp_external_reference',
    'tiendanube_order_id',
])]
class Invoice extends Model
{
    use Auditable, HasBillingTotals, HasOverdueStatus;

    // El JSON crudo de la respuesta de AFIP es ruido en el log de
    // auditoría, no un cambio legible.
    protected array $auditExclude = ['afip_response'];

    // Default también a nivel de modelo (no solo en la migración): si se
    // crea una factura sin pasar este campo explícitamente, el objeto en
    // memoria necesita el valor ya puesto — el default de la columna en
    // la base no se refleja en la instancia hasta hacer fresh()/refresh().
    protected $attributes = [
        'tipo_comprobante_interno' => 'factura_b',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'tax_rate' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'tipo_comprobante_interno' => TipoComprobanteInterno::class,
            'cae_vencimiento' => 'date',
            'tipo_comprobante' => TipoComprobante::class,
            'afip_response' => 'array',
            'emitted_at' => 'datetime',
            'afecta_stock' => 'boolean',
        ];
    }

    /**
     * IVA total de la factura: suma del IVA de cada ítem según su propia
     * alícuota (sobrescribe el cálculo por tax_rate único de HasBillingTotals
     * para soportar comprobantes con alícuotas mezcladas).
     */
    protected function taxAmount(): Attribute
    {
        return Attribute::get(fn () => $this->items->sum(fn (InvoiceItem $item) => $item->iva_amount));
    }

    /** Neto de los ítems gravados (alícuota > 0). */
    protected function netoGravado(): Attribute
    {
        return Attribute::get(
            fn () => $this->items->filter(fn (InvoiceItem $i) => $i->iva_rate_efectiva > 0)->sum(fn (InvoiceItem $i) => $i->line_total)
        );
    }

    /** Neto de los ítems exentos / no gravados (alícuota 0). */
    protected function netoExento(): Attribute
    {
        return Attribute::get(
            fn () => $this->items->filter(fn (InvoiceItem $i) => $i->iva_rate_efectiva <= 0)->sum(fn (InvoiceItem $i) => $i->line_total)
        );
    }

    /**
     * Desglose del IVA por alícuota (solo gravadas), ordenado por tasa.
     * Cada elemento: ['tasa' => float, 'base' => float, 'iva' => float].
     *
     * @return Collection<int, array{tasa: float, base: float, iva: float}>
     */
    public function ivaPorAlicuota(): Collection
    {
        return $this->items
            ->filter(fn (InvoiceItem $i) => $i->iva_rate_efectiva > 0)
            ->groupBy(fn (InvoiceItem $i) => number_format($i->iva_rate_efectiva, 2, '.', ''))
            ->map(fn (Collection $grupo, string $tasa) => [
                'tasa' => (float) $tasa,
                'base' => $grupo->sum(fn (InvoiceItem $i) => $i->line_total),
                'iva' => $grupo->sum(fn (InvoiceItem $i) => $i->iva_amount),
            ])
            ->sortBy('tasa')
            ->values();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * La factura original que esta Nota de Crédito acredita (null para
     * todo lo que no sea una NC).
     */
    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id');
    }

    /**
     * Notas de Crédito emitidas contra esta factura.
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(Invoice::class, 'related_invoice_id');
    }

    /**
     * El remito (nota de entrega) del que salió esta factura, si se generó
     * facturando un remito.
     */
    public function remito(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'remito_id');
    }

    /**
     * Facturas generadas a partir de este remito (normalmente una).
     */
    public function facturasDelRemito(): HasMany
    {
        return $this->hasMany(Invoice::class, 'remito_id');
    }

    /** True si este comprobante es un Remito X. */
    public function esRemito(): bool
    {
        return $this->tipo_comprobante_interno === TipoComprobanteInterno::RemitoX;
    }

    /**
     * +1 si este comprobante suma a la deuda del cliente (Factura, Remito),
     * -1 si la reduce (Nota de Crédito, Devolución). Usado por
     * Client::debitLines() para que el saldo de cuenta corriente no sume
     * las NC/Devoluciones en vez de restarlas.
     */
    public function signoDeuda(): int
    {
        return $this->tipo_comprobante_interno->esNotaCredito()
            || $this->tipo_comprobante_interno === TipoComprobanteInterno::Devolucion
            ? -1 : 1;
    }

    /** La factura ya generada a partir de este remito, o null. */
    public function facturaGenerada(): ?Invoice
    {
        return $this->facturasDelRemito()->first();
    }

    /**
     * Cuánto ya se acreditó (o está en proceso de acreditarse) de esta
     * factura vía Notas de Crédito. Cuenta también las que están en borrador
     * sin CAE todavía: NotasCredito\Create::save() ya mueve stock y caja al
     * crear la NC (antes de emitirla a AFIP), así que si no las contáramos acá
     * una segunda NC contra la misma factura podría duplicar esa reversión
     * mientras la primera sigue sin emitir.
     */
    protected function creditedTotal(): Attribute
    {
        return Attribute::get(
            fn () => $this->creditNotes()->get()->sum(fn (Invoice $nc) => $nc->total)
        );
    }

    /**
     * True una vez que AFIP otorgó un CAE. A partir de ahí la factura es
     * legalmente inmutable (ver guards en Invoices/Show y Invoices/Edit).
     */
    protected function isFiscal(): Attribute
    {
        return Attribute::get(fn () => $this->cae !== null);
    }

    /**
     * Comprobantes fiscales (Factura A/B, Nota de Crédito A/B) que todavía no
     * se emitieron a AFIP: sin CAE y fuera de borrador. Son los que faltan
     * emitir para que entren al Libro IVA.
     */
    public function scopePendientesDeEmision(Builder $query): Builder
    {
        return $query
            ->whereNull('cae')
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereIn(
                'tipo_comprobante_interno',
                array_map(fn (TipoComprobanteInterno $t) => $t->value, TipoComprobanteInterno::fiscales()),
            );
    }

    /**
     * Conteo memoizado por request (lo piden el sidebar y el dashboard).
     */
    public static function pendientesDeEmisionCountCached(): int
    {
        return once(fn () => self::pendientesDeEmision()->count());
    }
}
