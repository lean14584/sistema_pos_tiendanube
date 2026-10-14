<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Descuento manual ("Mis Descuentos") que el cajero elige en el POS para
 * aplicar a una venta puntual (ej. "Día de la Madre -15%", "Liquidación
 * -$2000"), a diferencia de Promotion (atada a un producto, la aplica el
 * sistema solo sin que el cajero elija nada). Ver Pos\Index::lineDiscountPct()
 * para cómo se compone con los descuentos por línea/promo/medio de pago.
 */
#[Fillable(['name', 'type', 'value', 'active', 'starts_at', 'ends_at'])]
class Discount extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /** Descuentos activos y dentro de su vigencia (si tiene fechas) — los que el POS puede ofrecer hoy. */
    public function scopeActiveNow(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }

    /** Etiqueta corta para mostrar en el POS y el ticket (ej. "-15%", "-$2.000"). */
    public function shortLabel(): string
    {
        return match ($this->type) {
            DiscountType::Percent => '-'.rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'%',
            DiscountType::Fixed => '-$'.number_format((float) $this->value, 0, ',', '.'),
        };
    }
}
