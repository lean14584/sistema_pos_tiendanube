<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'razon_social', 'logo_path', 'punto_venta', 'active'])]
class Sucursal extends Model
{
    use Auditable;

    protected $table = 'sucursales';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'punto_venta' => 'integer',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path ? asset('storage/'.$this->logo_path) : null);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }
}
