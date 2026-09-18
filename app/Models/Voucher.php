<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Vale de cambio al portador: se emite cuando un "cambio" en el POS
 * (Devolución + producto nuevo en la misma operación) deja un sobrante a
 * favor del cliente (ver Pos\Index::cobrarInterno()). Se identifica por
 * código, no por cliente — lo puede canjear quien lo traiga, en cualquier
 * venta futura del POS, incluso en partes (balance > 0 después de un canje
 * parcial queda disponible para la próxima).
 */
#[Fillable(['code', 'amount', 'balance', 'sucursal_id', 'devolucion_invoice_id'])]
class Voucher extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function devolucionInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'devolucion_invoice_id');
    }

    public function estaDisponible(): bool
    {
        return (float) $this->balance > 0;
    }

    public static function porCodigo(string $code): ?self
    {
        return self::where('code', strtoupper(trim($code)))->first();
    }

    public static function emitir(float $amount, ?int $sucursalId = null, ?Invoice $devolucion = null): self
    {
        return self::create([
            'code' => self::generarCodigo(),
            'amount' => $amount,
            'balance' => $amount,
            'sucursal_id' => $sucursalId,
            'devolucion_invoice_id' => $devolucion?->id,
        ]);
    }

    /**
     * Descuenta del saldo disponible. Con lock propio: dos canjes casi
     * simultáneos del mismo código (dos cajas, o doble clic) no pueden
     * pasar los dos el chequeo de saldo y dejarlo negativo.
     */
    public function redeem(float $amount): void
    {
        if ($amount <= 0) {
            throw new RuntimeException('El monto a canjear tiene que ser mayor a cero.');
        }

        Cache::lock("voucher:{$this->id}", 5)->block(3, function () use ($amount) {
            $fresh = self::findOrFail($this->id);

            if (round($amount, 2) > round((float) $fresh->balance, 2) + 0.009) {
                throw new RuntimeException(
                    "El vale {$fresh->code} tiene $".money((float) $fresh->balance).' de saldo, no alcanza para $'.money($amount).'.'
                );
            }

            $fresh->decrement('balance', $amount);
            $this->balance = $fresh->balance;
        });
    }

    private static function generarCodigo(): string
    {
        // Sin 0/O/1/I/L: se lee a mano de un ticket impreso, evita confusiones.
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
