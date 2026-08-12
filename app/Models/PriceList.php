<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lista de precios: un ajuste porcentual sobre el precio base del producto.
 * Ej.: Minorista 0%, Mayorista -15%, Tarjeta +10%. Se asigna por cliente y
 * se puede elegir manualmente al vender.
 */
#[Fillable(['name', 'adjustment_percent', 'is_default', 'active'])]
class PriceList extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'adjustment_percent' => 'decimal:2',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** La lista por defecto (o la primera activa como fallback). */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->active()->orderBy('id')->first();
    }
}
