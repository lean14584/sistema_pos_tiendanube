<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'sucursal_id', 'status', 'opened_at', 'opening_amount', 'closed_at', 'closing_amount', 'notes'])]
class CashSession extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opened_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'closing_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'session_id');
    }

    protected function ingresos(): Attribute
    {
        return Attribute::get(fn () => $this->movements->where('type', CashMovementType::Ingreso)->sum('amount'));
    }

    protected function egresos(): Attribute
    {
        return Attribute::get(fn () => $this->movements->where('type', CashMovementType::Egreso)->sum('amount'));
    }

    protected function expectedClosing(): Attribute
    {
        return Attribute::get(fn () => $this->opening_amount + $this->ingresos - $this->egresos);
    }

    protected function difference(): Attribute
    {
        return Attribute::get(fn () => $this->closing_amount !== null ? $this->closing_amount - $this->expected_closing : null);
    }
}
