<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_agregar_producto_genera_una_etiqueta_por_unidad(): void
    {
        $product = Product::create(['name' => 'Yerba', 'sku' => 'YER-1', 'price' => 1500, 'iva_rate' => 21, 'stock' => 10]);

        $component = Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->call('addProduct', $product->id)
            ->set('selected.'.$product->id.'.qty', 3);

        $component->assertSee('Yerba')->assertSee('1,500.00');
        // 3 etiquetas del mismo producto.
        $component->assertSeeInOrder(['1,500.00', '1,500.00', '1,500.00']);
    }

    public function test_las_etiquetas_usan_el_precio_de_la_lista_elegida(): void
    {
        $mayorista = PriceList::create(['name' => 'Mayorista', 'adjustment_percent' => 20, 'is_default' => false, 'active' => true]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->call('addProduct', $product->id)
            ->set('price_list_id', $mayorista->id)
            ->assertSee('1,200.00'); // 1000 + 20%
    }

    public function test_agregar_categoria_entera_suma_sus_productos(): void
    {
        $cat = Category::create(['name' => 'Bebidas']);
        Product::create(['name' => 'Agua', 'category_id' => $cat->id, 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);
        Product::create(['name' => 'Gaseosa', 'category_id' => $cat->id, 'price' => 900, 'iva_rate' => 0, 'stock' => 5]);

        $component = Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->set('catToAdd', $cat->id)
            ->call('addCategory');

        $this->assertCount(2, $component->get('selected'));
        $component->assertSee('Agua')->assertSee('Gaseosa');
    }
}
