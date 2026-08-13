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

    /**
     * Promo por familia (NxM entre varios productos): cuenta todas las
     * unidades del grupo presentes en el carrito y regala las MÁS BARATAS.
     * Devuelve el descuento en pesos asignado a cada producto.
     *
     * @param  array<int, array{product_id:int, quantity:int, unit_price:float}>  $lines
     * @return array<int, float>  product_id => descuento en pesos
     */
    public static function groupDiscount(int $buy, int $pay, array $lines): array
    {
        if ($buy <= 0 || $pay < 0 || $pay >= $buy) {
            return [];
        }

        $totalQty = array_sum(array_map(fn ($l) => (int) $l['quantity'], $lines));
        $freeUnits = intdiv($totalQty, $buy) * ($buy - $pay);

        if ($freeUnits <= 0) {
            return [];
        }

        // Expando a una unidad por precio y me quedo con las más baratas.
        $units = [];
        foreach ($lines as $line) {
            for ($i = 0; $i < (int) $line['quantity']; $i++) {
                $units[] = ['product_id' => $line['product_id'], 'price' => (float) $line['unit_price']];
            }
        }

        usort($units, fn ($a, $b) => $a['price'] <=> $b['price']);

        $allocation = [];
        foreach (array_slice($units, 0, $freeUnits) as $unit) {
            $allocation[$unit['product_id']] = ($allocation[$unit['product_id']] ?? 0) + $unit['price'];
        }

        return $allocation;
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
