<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['from_sucursal_id', 'to_sucursal_id', 'user_id', 'notes'])]
class StockTransfer extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromSucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'from_sucursal_id');
    }

    public function toSucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'to_sucursal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
