<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasBillingTotals;
use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'client_id', 'issue_date', 'valid_until', 'tax_rate', 'notes', 'status', 'converted_invoice_id'])]
class Quote extends Model
{
    use Auditable, HasBillingTotals;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'tax_rate' => 'decimal:2',
            'status' => QuoteStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }
}
