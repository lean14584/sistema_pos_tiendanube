<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\PromotionGroup;
use App\Models\User;
use App\Support\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionGroupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_engine_regala_las_unidades_mas_baratas_del_grupo(): void
    {
        // 3x2 (llevás 3, pagás 2 -> 1 gratis por cada 3).
        $lines = [
            ['product_id' => 1, 'quantity' => 1, 'unit_price' => 1000], // Coca
            ['product_id' => 2, 'quantity' => 1, 'unit_price' => 900],  // Fanta
            ['product_id' => 3, 'quantity' => 1, 'unit_price' => 800],  // Sprite (la más barata)
        ];

        $alloc = PromotionEngine::groupDiscount(3, 2, $lines);

        // 3 unidades -> 1 grupo -> regala 1: la más barata (Sprite, $800).
        $this->assertEquals([3 => 800.0], $alloc);
    }

    public function test_engine_regala_las_dos_mas_baratas_con_seis_unidades(): void
    {
        $lines = [
            ['product_id' => 1, 'quantity' => 2, 'unit_price' => 1000],
            ['product_id' => 2, 'quantity' => 2, 'unit_price' => 900],
            ['product_id' => 3, 'quantity' => 2, 'unit_price' => 800],
        ];

        // 6 unidades -> 2 grupos -> 2 gratis: las 2 Sprite (800 c/u) = 1600.
        $alloc = PromotionEngine::groupDiscount(3, 2, $lines);
        $this->assertEquals([3 => 1600.0], $alloc);
    }

    public function test_pos_aplica_la_promo_de_familia_regalando_la_mas_barata(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $coca = Product::create(['name' => 'Coca', 'price' => 1000, 'iva_rate' => 0, 'stock' => 50]);
        $fanta = Product::create(['name' => 'Fanta', 'price' => 900, 'iva_rate' => 0, 'stock' => 50]);
        $sprite = Product::create(['name' => 'Sprite', 'price' => 800, 'iva_rate' => 0, 'stock' => 50]);

        $group = PromotionGroup::create(['name' => 'Gaseosas', 'buy_qty' => 3, 'pay_qty' => 2, 'active' => true]);
        $group->products()->sync([$coca->id, $fanta->id, $sprite->id]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $coca->id)
            ->call('addProduct', $fanta->id)
            ->call('addProduct', $sprite->id);

        // 1000 + 900 + 800 = 2700, menos la más barata (800) = 1900.
        $this->assertEqualsWithDelta(1900.0, $pos->instance()->total(), 0.01);

        $pos->call('addPayment')->set('printOnSale', false)->call('cobrar')->assertHasNoErrors();

        // Sprite quedó 100% off (gratis); Coca y Fanta sin descuento.
        $this->assertDatabaseHas('invoice_items', ['description' => 'Sprite', 'discount_percent' => 100]);
        $this->assertDatabaseHas('invoice_items', ['description' => 'Coca', 'discount_percent' => 0]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 1900]);
    }

    public function test_crear_familia_desde_la_pantalla(): void
    {
        $a = Product::create(['name' => 'Coca', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);
        $b = Product::create(['name' => 'Fanta', 'price' => 900, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('promotion-groups.index')
            ->set('name', 'Gaseosas')
            ->call('addProduct', $a->id)
            ->call('addProduct', $b->id)
            ->call('save')
            ->assertHasNoErrors();

        $group = PromotionGroup::first();
        $this->assertNotNull($group);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $group->products->pluck('id')->all());
    }

    public function test_no_se_puede_crear_familia_con_menos_de_dos_productos(): void
    {
        $a = Product::create(['name' => 'Coca', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('promotion-groups.index')
            ->set('name', 'Gaseosas')
            ->call('addProduct', $a->id)
            ->call('save')
            ->assertHasErrors('selected');

        $this->assertEquals(0, PromotionGroup::count());
    }
}
