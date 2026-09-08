<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Venta importada desde otro sistema (ej. Tango) para consulta e informes.
 * No es una Invoice: no tiene numeración/CAE, no entra al Libro IVA y no
 * afecta el saldo de cuenta corriente (ver Client::opening_balance).
 */
#[Fillable(['client_id', 'client_name_raw', 'sale_date', 'comprobante_type', 'comprobante_number', 'total', 'notes'])]
class HistoricalSale extends Model
{
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
