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
#[Fillable(['token', 'customer_id', 'invoice_id', 'sucursal_id', 'status', 'customer_name', 'customer_email', 'customer_phone', 'cart_snapshot', 'mp_preference_id', 'expires_at', 'picked_up_at'])]
class TiendaPedido extends Model
{
    protected function casts(): array
    {
        return [
            'cart_snapshot' => 'array',
            'expires_at' => 'datetime',
            'picked_up_at' => 'datetime',
        ];
    }

    /** Si el cliente ya pasó a buscar su pedido — independiente de si está pagado. */
    public function estaRetirado(): bool
    {
        return $this->picked_up_at !== null;
    }

    /**
     * Pedidos todavía en curso (pagos o esperando pago, sin retirar) —
     * badge del sidebar en Pedidos Online. Memoizado por request, mismo
     * criterio que Product::lowStockCountCached().
     */
    public static function enCursoCountCached(): int
    {
        return once(fn () => self::whereIn('status', ['paid', 'pending_payment'])
            ->whereNull('picked_up_at')
            ->count());
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
