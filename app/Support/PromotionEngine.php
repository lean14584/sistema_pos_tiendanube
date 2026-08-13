<?php

namespace App\Support;

use App\Enums\PromotionType;
use App\Models\Promotion;

/**
 * Calcula el descuento (en pesos) que una promoción le da a una línea del
 * carrito, según su tipo, la cantidad y el precio unitario. Centralizado acá
 * para que el POS y los tests usen exactamente la misma lógica.
 */
class PromotionEngine
{
    public static function discount(Promotion $promo, int $quantity, float $unitPrice): float
    {
        if ($quantity < 1 || $unitPrice <= 0) {
            return 0.0;
        }

        return match ($promo->type) {
            // Nx M: cada grupo de N unidades se pagan M -> se regalan (N-M).
            PromotionType::Nxm => self::nxm($promo, $quantity, $unitPrice),
            // 2da unidad: cada par, la 2da con `percent`% off.
            PromotionType::Segunda => intdiv($quantity, 2) * $unitPrice * ((float) $promo->percent / 100),
            // Cantidad: desde min_qty, `percent`% en toda la línea.
            PromotionType::Cantidad => $quantity >= (int) $promo->min_qty
                ? $quantity * $unitPrice * ((float) $promo->percent / 100)
                : 0.0,
        };
    }

    private static function nxm(Promotion $promo, int $quantity, float $unitPrice): float
    {
        $buy = (int) $promo->buy_qty;
        $pay = (int) $promo->pay_qty;

        if ($buy <= 0 || $pay < 0 || $pay >= $buy) {
            return 0.0;
        }

        $freePerGroup = $buy - $pay;
        $freeUnits = intdiv($quantity, $buy) * $freePerGroup;

        return $freeUnits * $unitPrice;
    }
}
