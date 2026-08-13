<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Support\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function promo(Product $product, array $attrs): Promotion
    {
        return Promotion::create(array_merge([
            'product_id' => $product->id, 'active' => true,
        ], $attrs));
    }

    // ---- Motor de cálculo ----

    public function test_engine_2x1_regala_una_de_cada_dos(): void
    {
        $p = Product::create(['name' => 'Alfajor', 'price' => 100, 'iva_rate' => 0, 'stock' => 100]);
        $promo = $this->promo($p, ['type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1]);

        $this->assertEquals(0, PromotionEngine::discount($promo, 1, 100)); // 1 -> sin regalo
        $this->assertEquals(100, PromotionEngine::discount($promo, 2, 100)); // paga 1
        $this->assertEquals(100, PromotionEngine::discount($promo, 3, 100)); // 1 gratis
        $this->assertEquals(200, PromotionEngine::discount($promo, 4, 100)); // 2 gratis
    }

    public function test_engine_segunda_al_50(): void
    {
        $p = Product::create(['name' => 'Shampoo', 'price' => 100, 'iva_rate' => 0, 'stock' => 100]);
        $promo = $this->promo($p, ['type' => 'segunda', 'percent' => 50]);

        $this->assertEquals(0, PromotionEngine::discount($promo, 1, 100));
        $this->assertEquals(50, PromotionEngine::discount($promo, 2, 100));  // 1 par -> 50% de una
        $this->assertEquals(50, PromotionEngine::discount($promo, 3, 100));  // sigue 1 par
        $this->assertEquals(100, PromotionEngine::discount($promo, 4, 100)); // 2 pares
    }

    public function test_engine_descuento_por_cantidad(): void
    {
        $p = Product::create(['name' => 'Arroz', 'price' => 100, 'iva_rate' => 0, 'stock' => 100]);
        $promo = $this->promo($p, ['type' => 'cantidad', 'min_qty' => 10, 'percent' => 15]);

        $this->assertEquals(0, PromotionEngine::discount($promo, 9, 100));      // no llega
        $this->assertEquals(150, PromotionEngine::discount($promo, 10, 100));   // 15% de 1000
    }

    // ---- POS aplica la promo ----

    public function test_pos_aplica_2x1_al_total_y_lo_guarda_en_la_factura(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Alfajor', 'price' => 100, 'iva_rate' => 0, 'stock' => 100]);
        $this->promo($product, ['type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('cart.0.quantity', 2);

        // 2 unidades con 2x1 -> se paga 1 = $100
        $this->assertEqualsWithDelta(100.0, $pos->instance()->total(), 0.01);

        $pos->call('addPayment')->set('printOnSale', false)->call('cobrar')->assertHasNoErrors();

        // El ítem quedó con 50% de descuento (1 de 2 gratis).
        $this->assertDatabaseHas('invoice_items', ['description' => 'Alfajor', 'discount_percent' => 50]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 100]);
    }

    public function test_pos_no_aplica_promo_inactiva(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Alfajor', 'price' => 100, 'iva_rate' => 0, 'stock' => 100]);
        $this->promo($product, ['type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1, 'active' => false]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('cart.0.quantity', 2);

        // Sin promo activa -> paga las 2 = $200
        $this->assertEqualsWithDelta(200.0, $pos->instance()->total(), 0.01);
    }

    public function test_crear_promo_desde_la_pantalla_guarda_solo_los_parametros_del_tipo(): void
    {
        $product = Product::create(['name' => 'Gaseosa', 'price' => 900, 'iva_rate' => 21, 'stock' => 50]);

        Livewire::actingAs($this->admin())
            ->test('promotions.index')
            ->set('product_id', (string) $product->id)
            ->set('type', 'segunda')
            ->set('percent', '70')
            ->call('save')
            ->assertHasNoErrors();

        // Guarda percent, y deja en null los parámetros de otros tipos.
        $this->assertDatabaseHas('promotions', [
            'product_id' => $product->id, 'type' => 'segunda', 'percent' => 70,
            'buy_qty' => null, 'min_qty' => null,
        ]);
    }
}
