<?php

namespace App\Support;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use RuntimeException;

class StockAdjuster
{
    /**
     * Verifica que haya stock suficiente para descontar $items, bloqueando
     * (lockForUpdate) la fila de `products` de cada uno involucrado. Tiene
     * que llamarse DENTRO de la misma transacción de base de datos que
     * después descuenta ese stock — fuera de una transacción, lockForUpdate()
     * no sirve de nada y dos cobros simultáneos del mismo producto podrían
     * pasar los dos el chequeo.
     *
     * Chequea contra `products.stock` (el total de la empresa, no el de una
     * sucursal puntual — ver Product::stockEnSucursal()) a propósito: es la
     * única cifra que apply() mantiene siempre consistente en cualquier
     * instalación, tenga o no cargado el detalle por sucursal en
     * `product_stocks` (ej. un producto creado antes de usar sucursales, o
     * en los tests, nunca tiene fila ahí y leería 0 aunque sí haya stock
     * real). No evita vender de la sucursal equivocada en un negocio
     * multi-sucursal, pero sí evita vender lo que no existe en absoluto.
     *
     * Lanza InsufficientStockException (no devuelve bool) para que quien
     * llama no pueda "olvidarse" de chequear el resultado. Público además de
     * usarse desde apply(): algún caller puede querer chequear ANTES de abrir
     * la transacción (ej. para mostrar el error sin haber tocado nada).
     */
    public static function assertSufficientStock(iterable $items): void
    {
        $items = collect($items)->filter(fn ($item) => ! empty($item['product_id'] ?? null));

        // Mismo criterio que apply(): lo que se vende por peso no lleva
        // control de stock.
        $pesables = Product::whereIn('id', $items->pluck('product_id')->unique())
            ->where('sold_by_weight', true)
            ->pluck('id')
            ->all();

        $necesario = $items
            ->reject(fn ($item) => in_array($item['product_id'], $pesables, true))
            ->groupBy('product_id')
            ->map(fn (Collection $group) => $group->sum('quantity'));

        self::assertAvailable($necesario);
    }

    /**
     * $necesario ya viene agrupado por product_id (sin los pesables) — lo
     * usan tanto assertSufficientStock() (chequeo standalone) como apply()
     * (chequeo inline antes de descontar, reusando el mismo groupBy que ya
     * hizo para calcular los deltas, sin recalcular pesables ni traer los
     * productos de a uno).
     */
    private static function assertAvailable(Collection $necesario): void
    {
        if ($necesario->isEmpty()) {
            return;
        }

        $products = Product::whereIn('id', $necesario->keys())->lockForUpdate()->get()->keyBy('id');

        foreach ($necesario as $productId => $cantidad) {
            $product = $products->get($productId);

            if ($product && $product->stock < $cantidad) {
                throw new InsufficientStockException(
                    "No queda stock suficiente de \"{$product->name}\" (disponible: {$product->stock}, se intenta vender: {$cantidad})."
                );
            }
        }
    }

    /**
     * Apply a stock delta for a set of purchase items ({product_id, quantity} shaped).
     * $sign is 1 to add stock (purchase created) or -1 to remove it (purchase deleted/reverted).
     *
     * $sucursalId: en qué sucursal se mueve el stock. Si no se pasa, se
     * resuelve a la sucursal activa del usuario logueado (CurrentSucursal) —
     * así los ~10 lugares que ya llamaban a apply() (Ventas, Compras, Notas
     * de Crédito, Presupuestos) quedan sucursal-aware sin tener que tocarlos
     * uno por uno.
     *
     * Con $sign === -1 (se va a descontar stock), valida ACÁ ADENTRO que haya
     * suficiente antes de tocar nada — así los ~12 call sites que descuentan
     * stock (Ventas, facturación manual, conversión de Presupuesto a venta,
     * Notas de Crédito, reversión de Compras) quedan protegidos contra stock
     * negativo sin tener que agregar el chequeo en cada uno por separado (ver
     * assertSufficientStock(), que hacía justo esto pero solo se llamaba
     * desde el POS).
     */
    public static function apply(iterable $items, int $sign, ?int $sucursalId = null): void
    {
        $items = collect($items)->filter(fn ($item) => ! empty($item['product_id'] ?? null));

        // Los productos que se venden por peso no llevan control de stock
        // (ver Product::sold_by_weight) — quedan afuera del movimiento, no
        // porque el descuento fallara, sino porque a propósito no se cuentan.
        $pesables = Product::whereIn('id', $items->pluck('product_id')->unique())
            ->where('sold_by_weight', true)
            ->pluck('id')
            ->all();

        $deltas = $items
            ->reject(fn ($item) => in_array($item['product_id'], $pesables, true))
            ->groupBy('product_id')
            ->map(fn (Collection $group) => $group->sum('quantity') * $sign);

        if ($sign === -1) {
            self::assertAvailable($deltas->map(fn ($delta) => -$delta));
        }

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
            } catch (QueryException) {
                // Carrera: otro request creó la fila de product_stocks justo
                // antes (misma sucursal, mismo producto, primer movimiento).
                // Reintenta como update ahora que ya existe.
                ProductStock::where('product_id', $productId)->where('sucursal_id', $sucursalId)->increment('stock', $delta);
            }
        }

        return $sucursalId;
    }
}
