<?php

namespace App\Models;

use App\Notifications\TiendaResetPasswordNotification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Cuenta de un comprador de la tienda online (guard `customer`, ver
 * config/auth.php). Independiente de App\Models\User (staff, guard `web`)
 * y de App\Models\Client (ficha de facturación/CRM del POS, no es
 * Authenticatable) — ver plan de cuentas de la tienda.
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable
{
    use Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TiendaResetPasswordNotification($token));
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(TiendaPedido::class, 'customer_id');
    }
}
