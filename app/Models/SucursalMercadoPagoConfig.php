<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sin trait Auditable a propósito: `access_token` es un secreto y esta
 * tabla no debe dejar rastro en texto plano en audit_logs (ver la
 * migración de creación).
 */
#[Fillable([
    'sucursal_id', 'access_token', 'collector_id', 'store_external_id', 'pos_external_id',
    'store_name', 'pos_name', 'store_street', 'store_number', 'store_city', 'store_state',
    'store_lat', 'store_lng', 'category', 'notification_url',
])]
class SucursalMercadoPagoConfig extends Model
{
    protected $table = 'sucursal_mercadopago_configs';

    protected function casts(): array
    {
        return [
            'store_lat' => 'float',
            'store_lng' => 'float',
            'category' => 'integer',
            'collector_id' => 'integer',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
