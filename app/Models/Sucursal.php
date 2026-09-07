<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'razon_social', 'logo_path', 'active'])]
class Sucursal extends Model
{
    use Auditable;

    protected $table = 'sucursales';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class);
    }

    /**
     * El punto de venta que se usa cuando no se elige ninguno a mano (el
     * primero cargado, cronológicamente). Si la sucursal tiene uno solo
     * (el caso más común), es el único disponible y no hace falta elegir.
     */
    public function puntoVentaPorDefecto(): ?PuntoVenta
    {
        return $this->puntosVenta()->where('active', true)->orderBy('id')->first();
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

    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function mercadoPagoConfig(): HasOne
    {
        return $this->hasOne(SucursalMercadoPagoConfig::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }
}
