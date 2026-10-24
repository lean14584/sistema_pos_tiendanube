<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name', 'buy_qty', 'pay_qty', 'active', 'starts_at', 'ends_at'])]
class PromotionGroup extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'buy_qty' => 'integer',
            'pay_qty' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_group_product');
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }

    /** Etiqueta corta para el POS (ej. "3x2 familia"). */
    public function shortLabel(): string
    {
        return "{$this->buy_qty}x{$this->pay_qty} familia";
    }

    /**
     * Igual que activeNow()->with('products:id')->get(), cacheada 60s —
     * Pos\Index::groupAllocations() es un #[Computed] que la resuelve en
     * cada click del carrito (agregar producto, +/-, etc.), no solo al
     * cargar la pantalla. El cómputo por carrito (foreach sobre $lines)
     * sigue sin cachear, solo la query de grupos+productos, que no cambia
     * salvo que alguien edite "Mis Descuentos por familia".
     *
     * Se cachea `toArray()` (incluye la relación `products` ya cargada,
     * como array de arrays) en vez de la Collection de modelos tal cual:
     * con el driver `database`, serializar un objeto Eloquent completo
     * puede corromperse en el charset utf8mb4 de la columna `cache.value` y
     * romper "Return value must be of type Collection" en la próxima
     * lectura (ver el mismo fix en PuntoVenta::activosParaSucursalCached()).
     * products:id va aparte y se reconstruye como relación manualmente,
     * porque hydrate() solo repone atributos propios, no relaciones.
     */
    public static function activosConProductosCached(): Collection
    {
        $rows = Cache::remember(
            'promotion-groups:activos-con-productos-v2',
            now()->addSeconds(60),
            fn () => self::activeNow()->with('products:id')->get()->toArray(),
        );

        $productRowsByGroup = array_column($rows, 'products');
        $baseRows = array_map(function (array $row) {
            unset($row['products']);

            return $row;
        }, $rows);

        $groups = self::hydrate($baseRows)->values();
        $groups->each(fn (self $group, int $i) => $group->setRelation('products', Product::hydrate($productRowsByGroup[$i] ?? [])));

        return $groups;
    }
}
