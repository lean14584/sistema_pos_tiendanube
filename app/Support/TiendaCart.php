<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Carrito de la tienda online: vive en la sesión del navegador (invitado,
 * sin login — ver plan de la tienda), no en la DB. Guarda solo
 * {product_id: quantity}; el precio NUNCA se guarda acá, se relee de
 * Product en cada render (mismo criterio que Pos\Index::cobrarInterno() —
 * no confiar en nada que haya quedado viejo en sesión).
 */
class TiendaCart
{
    private const SESSION_KEY = 'tienda_cart';

    /** @return array<int,int> product_id => quantity */
    private static function raw(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    private static function save(array $items): void
    {
        Session::put(self::SESSION_KEY, array_filter($items, fn ($qty) => $qty > 0));
    }

    public static function add(int $productId, int $quantity = 1): void
    {
        $items = self::raw();
        $items[$productId] = max(1, ($items[$productId] ?? 0) + $quantity);
        self::save($items);
    }

    public static function setQuantity(int $productId, int $quantity): void
    {
        $items = self::raw();

        if ($quantity <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $quantity;
        }

        self::save($items);
    }

    public static function remove(int $productId): void
    {
        $items = self::raw();
        unset($items[$productId]);
        self::save($items);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function count(): int
    {
        return array_sum(self::raw());
    }

    public static function isEmpty(): bool
    {
        return self::raw() === [];
    }

    /**
     * Líneas del carrito con precio y stock leídos AHORA de la DB (no de lo
     * guardado en sesión), cada una con su subtotal. Productos borrados
     * desde que se agregaron simplemente no aparecen.
     *
     * @return Collection<int, array{product: Product, quantity: int, subtotal: float}>
     */
    public static function lines(): Collection
    {
        $items = self::raw();

        if ($items === []) {
            return collect();
        }

        return Product::whereIn('id', array_keys($items))
            ->get()
            ->map(fn (Product $product) => [
                'product' => $product,
                'quantity' => $items[$product->id],
                'subtotal' => round((float) $product->price * $items[$product->id], 2),
            ]);
    }

    public static function total(): float
    {
        return (float) self::lines()->sum('subtotal');
    }
}
