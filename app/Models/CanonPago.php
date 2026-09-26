<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanonPago extends Model
{
    protected $fillable = [
        'user_id',
        'fecha_pago',
        'mp_payment_id',
        'monto',
        'mes',
        'anio',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
