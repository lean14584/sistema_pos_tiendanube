<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

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
}
