<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Igual que active()->orderBy('name')->get(), pero cacheada 60s — mismo
     * patrón que Sucursal::activasParaSelectCached()/Category::forSelectCached().
     * Se repetía sin cachear en 8 render() distintos (POS, Invoices, Quotes,
     * Products\Labels, Clients), incluyendo pantallas de alta frecuencia.
     *
     * @return Collection<int, object{id: int, name: string, adjustment_percent: string}>
     */
    public static function activasParaSelectCached(): Collection
    {
        $json = Cache::remember(
            'price-lists:activas-select-list-v1',
            now()->addSeconds(60),
            fn () => self::active()->orderBy('name')->get(['id', 'name', 'adjustment_percent'])->toJson(),
        );

        return collect(json_decode($json));
    }
}
