<?php

namespace App\Support;

use App\Models\Product;
use App\Models\TiendaCartItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Carrito de la tienda online. Un invitado (sin cuenta) lo tiene en la
 * sesión del navegador, como antes; un cliente logueado (guard `customer`)
 * lo tiene en la tabla `tienda_cart_items`, para que sobreviva al
 * deslogueo o un cambio de dispositivo — ver mergeSessionIntoAccount(),
 * que funde el carrito de invitado al que se loguea. El precio NUNCA se
 * guarda acá, se relee de Product en cada render (mismo criterio que
 * Pos\Index::cobrarInterno() — no confiar en nada que haya quedado viejo).
 */
class TiendaCart
{
    private const SESSION_KEY = 'tienda_cart';

    private static function customerId(): ?int
    {
        return Auth::guard('customer')->id();
    }

    /** @return array<int,int> product_id => quantity */
    private static function raw(): array
    {
        if ($customerId = self::customerId()) {
            return TiendaCartItem::where('customer_id', $customerId)->pluck('quantity', 'product_id')->all();
        }

        return Session::get(self::SESSION_KEY, []);
    }

    /** @param  array<int,int>  $items */
    private static function save(array $items): void
    {
        $items = array_filter($items, fn ($qty) => $qty > 0);

        if ($customerId = self::customerId()) {
            TiendaCartItem::where('customer_id', $customerId)->whereNotIn('product_id', array_keys($items))->delete();

            foreach ($items as $productId => $qty) {
                TiendaCartItem::updateOrCreate(
                    ['customer_id' => $customerId, 'product_id' => $productId],
                    ['quantity' => $qty],
                );
            }

            return;
        }

        Session::put(self::SESSION_KEY, $items);
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
        if ($customerId = self::customerId()) {
            TiendaCartItem::where('customer_id', $customerId)->delete();

            return;
        }

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
     * guardado en sesión/cuenta), cada una con su subtotal. Productos
     * borrados desde que se agregaron simplemente no aparecen.
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

    /**
     * Al loguearse/registrarse, suma lo que había en el carrito de invitado
     * (sesión) al carrito de la cuenta y limpia la sesión — igual al
     * patrón que ya documentaba tienda-online/CLAUDE.md para su propio
     * carrito. Tiene que llamarse DESPUÉS de Auth::login(), para que
     * customerId() ya resuelva a la cuenta recién logueada.
     */
    public static function mergeSessionIntoAccount(int $customerId): void
    {
        $sessionItems = Session::get(self::SESSION_KEY, []);

        if ($sessionItems === []) {
            return;
        }

        foreach ($sessionItems as $productId => $qty) {
            TiendaCartItem::where('customer_id', $customerId)->where('product_id', $productId)->exists()
                ? TiendaCartItem::where('customer_id', $customerId)->where('product_id', $productId)->increment('quantity', $qty)
                : TiendaCartItem::create(['customer_id' => $customerId, 'product_id' => $productId, 'quantity' => $qty]);
        }

        Session::forget(self::SESSION_KEY);
    }
}
