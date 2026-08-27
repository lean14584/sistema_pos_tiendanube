<?php

namespace App\Models;

use App\Enums\StockAdjustmentReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'user_id', 'previous_stock', 'new_stock', 'reason', 'notes'])]
class StockAdjustment extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'previous_stock' => 'integer',
            'new_stock' => 'integer',
            'reason' => StockAdjustmentReason::class,
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function delta(): Attribute
    {
        return Attribute::get(fn () => $this->new_stock - $this->previous_stock);
    }
}
