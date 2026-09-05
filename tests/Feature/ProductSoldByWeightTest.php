<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\StockAdjuster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSoldByWeightTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_crear_producto_por_peso_requiere_sku_numerico(): void
    {
        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Queso cremoso')
            ->set('sold_by_weight', true)
            ->set('sku', '') // sin código PLU
            ->set('price', '3000')
            ->set('iva_rate', '21')
            ->set('stock', '0')
            ->call('save')
            ->assertHasErrors('sku');

        $this->assertSame(0, Product::count());
    }

    public function test_crear_producto_por_peso_con_sku_no_numerico_falla(): void
    {
        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Queso cremoso')
            ->set('sold_by_weight', true)
            ->set('sku', 'ABC12')
            ->set('price', '3000')
            ->set('iva_rate', '21')
            ->set('stock', '0')
            ->call('save')
            ->assertHasErrors('sku');
    }

    public function test_crear_producto_por_peso_ignora_el_stock_cargado_y_no_crea_product_stock(): void
    {
        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Queso cremoso')
            ->set('sold_by_weight', true)
            ->set('sku', '00099')
            ->set('price', '3000')
            ->set('iva_rate', '21')
            ->set('stock', '50') // se ignora: no lleva stock
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('sku', '00099')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->sold_by_weight);
        $this->assertSame(0, $product->stock);
        $this->assertSame(0, ProductStock::where('product_id', $product->id)->count());
    }

    public function test_stock_adjuster_apply_no_mueve_stock_de_un_producto_por_peso(): void
    {
        $sucursal = Sucursal::sole();
        $pesado = Product::create(['name' => 'Jamón', 'sku' => '00023', 'sold_by_weight' => true, 'price' => 2000, 'iva_rate' => 21, 'stock' => 0]);
        $normal = Product::create(['name' => 'Gaseosa', 'price' => 800, 'iva_rate' => 21, 'stock' => 10]);
        ProductStock::create(['product_id' => $normal->id, 'sucursal_id' => $sucursal->id, 'stock' => 10]);

        StockAdjuster::apply([
            ['product_id' => $pesado->id, 'quantity' => 0.35],
            ['product_id' => $normal->id, 'quantity' => 2],
        ], -1, $sucursal->id);

        $this->assertSame(0, $pesado->fresh()->stock);
        $this->assertSame(8, $normal->fresh()->stock);
    }
}
