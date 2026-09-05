<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Collection;
use RuntimeException;

class StockAdjuster
{
    /**
     * Apply a stock delta for a set of purchase items ({product_id, quantity} shaped).
     * $sign is 1 to add stock (purchase created) or -1 to remove it (purchase deleted/reverted).
     *
     * $sucursalId: en qué sucursal se mueve el stock. Si no se pasa, se
     * resuelve a la sucursal activa del usuario logueado (CurrentSucursal) —
     * así los ~10 lugares que ya llamaban a apply() (Ventas, Compras, Notas
     * de Crédito, Presupuestos) quedan sucursal-aware sin tener que tocarlos
     * uno por uno.
     */
    public static function apply(iterable $items, int $sign, ?int $sucursalId = null): void
    {
        $deltas = collect($items)
            ->filter(fn ($item) => ! empty($item['product_id'] ?? null))
            ->groupBy('product_id')
            ->map(fn (Collection $group) => $group->sum('quantity') * $sign);

        foreach ($deltas as $productId => $delta) {
            if ($delta !== 0) {
                self::moveBranchStock((int) $productId, (int) $delta, $sucursalId);
                // Query builder a propósito (no dispara eventos de Eloquent):
                // Auditable no debe loguear un diff de "stock" por cada venta
                // o compra, solo por cambios manuales (ver applyManualDelta).
                Product::whereKey($productId)->increment('stock', $delta);
            }
        }
    }

    /**
     * Igual que apply()/moveBranchStock(), pero para un ajuste manual
     * (Ajuste de Stock): acá sí queremos que products.stock quede auditado,
     * por eso el increment corre sobre una instancia del modelo (dispara
     * Auditable) en vez del query builder silencioso de arriba.
     */
    public static function applyManualDelta(int $productId, int $delta, ?int $sucursalId = null): void
    {
        self::moveBranchStock($productId, $delta, $sucursalId);
        Product::findOrFail($productId)->increment('stock', $delta);
    }

    /**
     * Mueve el stock de UN producto en UNA sucursal (product_stocks). No
     * toca products.stock — cada caller decide si ese incremento debe
     * auditarse o no (ver arriba). Devuelve la sucursal efectivamente usada.
     */
    private static function moveBranchStock(int $productId, int $delta, ?int $sucursalId): int
    {
        $sucursalId ??= CurrentSucursal::id();

        if ($sucursalId === null) {
            throw new RuntimeException('No hay ninguna sucursal para mover stock. Creá al menos una en Configuración → Sucursales.');
        }

        $affected = ProductStock::where('product_id', $productId)->where('sucursal_id', $sucursalId)->increment('stock', $delta);

        if ($affected === 0) {
            try {
                ProductStock::create(['product_id' => $productId, 'sucursal_id' => $sucursalId, 'stock' => $delta]);
            } catch (\Illuminate\Database\QueryException) {
                // Carrera: otro request creó la fila de product_stocks justo
                // antes (misma sucursal, mismo producto, primer movimiento).
                // Reintenta como update ahora que ya existe.
                ProductStock::where('product_id', $productId)->where('sucursal_id', $sucursalId)->increment('stock', $delta);
            }
        }

        return $sucursalId;
    }
}
