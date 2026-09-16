<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Observers\CategoryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[ObservedBy(CategoryObserver::class)]
#[Fillable(['name', 'description', 'tiendanube_category_id'])]
class Category extends Model
{
    use Auditable;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * MEJORA: Category::orderBy('name')->get() se repetía sin caché en 3
     * render() distintos (Products\Create, Products\Edit, Products\Labels)
     * - se re-ejecuta en cada round-trip de Livewire. Mismo patrón que
     * Client::forSelectCached()/Sucursal::forSelectCached(): JSON en vez de
     * la Collection de modelos, para no depender de que serialize()/
     * unserialize() de objetos PHP sobreviva intacto entre deploys.
     *
     * @return Collection<int, object{id: int, name: string}>
     */
    public static function forSelectCached(): Collection
    {
        $json = Cache::remember(
            'categories:select-list-v1',
            now()->addSeconds(60),
            fn () => self::orderBy('name')->get(['id', 'name'])->toJson(),
        );

        return collect(json_decode($json));
    }
}
