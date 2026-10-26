<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cabecera pública de un pedido de la tienda online. El token identifica el
 * pedido en URLs públicas; el stock/plata real vive en el Remito (Invoice)
 * vinculado. `customer_id` es nullable: un invitado sin cuenta también
 * puede comprar (ver Livewire\Tienda\Checkout), solo que no le queda en
 * "Mis pedidos".
 */
#[Fillable(['token', 'customer_id', 'invoice_id', 'sucursal_id', 'status', 'customer_name', 'customer_email', 'customer_phone', 'cart_snapshot', 'mp_preference_id', 'expires_at'])]
class TiendaPedido extends Model
{
    protected function casts(): array
    {
        return [
            'cart_snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
