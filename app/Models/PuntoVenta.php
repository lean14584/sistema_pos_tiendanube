<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un punto de venta ARCA (ex AFIP) habilitado para una sucursal. Una
 * sucursal puede tener varios (ej.: mostrador + online); el número tiene
 * que ser único en toda la empresa (un mismo CUIT no puede repetir punto
 * de venta entre sucursales).
 */
#[Fillable(['sucursal_id', 'numero', 'nombre', 'active'])]
class PuntoVenta extends Model
{
    protected $table = 'puntos_venta';

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function label(): string
    {
        $numero = str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);

        return $this->nombre ? "{$numero} — {$this->nombre}" : $numero;
    }
}
