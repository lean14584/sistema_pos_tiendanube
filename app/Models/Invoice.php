<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasBillingTotals;
use App\Concerns\HasOverdueStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TipoComprobante;
use App\Enums\TipoComprobanteInterno;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'client_id', 'issue_date', 'due_date', 'tax_rate', 'notes', 'status',
    'tipo_comprobante_interno', 'related_invoice_id', 'afecta_stock',
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
     * Cuánto ya se acreditó de esta factura vía Notas de Crédito con CAE
     * (las que quedaron en borrador, rechazadas o canceladas no cuentan).
     * Se usa para no dejar emitir una NC que supere el saldo pendiente.
     */
    protected function creditedTotal(): Attribute
    {
        return Attribute::get(
            fn () => $this->creditNotes()->whereNotNull('cae')->get()->sum(fn (Invoice $nc) => $nc->total)
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
}
