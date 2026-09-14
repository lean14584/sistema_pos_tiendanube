<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionGroup;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Antes de este fix, cualquier rol con acceso al módulo (Vendedor en
 * products-manage/categories/price-lists/clients/quotes/promotions, Cajero
 * en clients) podía borrar sin ningún chequeo más fino que ese — igual que
 * pasaba con Invoices\Show::delete() antes de restringirlo a Admin/Encargado
 * (ver test_cajero_no_puede_eliminar_un_comprobante en
 * InvoiceStockAndCashTest). Acá se extiende el mismo criterio al resto de
 * las pantallas de borrado.
 */
class DeleteRestrictedToAdminEncargadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_no_puede_eliminar_un_producto(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($vendedor)
            ->test('products.index')
            ->call('delete', $product->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_encargado_si_puede_eliminar_un_producto(): void
    {
        $encargado = User::factory()->create(['role' => Role::Encargado, 'active' => true]);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($encargado)->test('products.index')->call('delete', $product->id);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_vendedor_no_puede_eliminar_una_categoria(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $category = Category::create(['name' => 'Bebidas']);

        Livewire::actingAs($vendedor)
            ->test('categories.index')
            ->call('delete', $category->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_vendedor_no_puede_eliminar_una_lista_de_precios(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $priceList = PriceList::create(['name' => 'Mayorista', 'adjustment_percent' => -15, 'is_default' => false, 'active' => true]);

        Livewire::actingAs($vendedor)
            ->test('price-lists.index')
            ->call('delete', $priceList->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('price_lists', ['id' => $priceList->id]);
    }

    public function test_vendedor_no_puede_eliminar_un_cliente(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        Livewire::actingAs($vendedor)
            ->test('clients.index')
            ->call('delete', $client->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_cajero_no_puede_eliminar_un_cliente(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        Livewire::actingAs($cajero)
            ->test('clients.index')
            ->call('delete', $client->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_vendedor_no_puede_eliminar_un_presupuesto(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $quote = Quote::create([
            'number' => 'PRE-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'draft',
        ]);

        Livewire::actingAs($vendedor)
            ->test('quotes.show', ['quote' => $quote])
            ->call('delete')
            ->assertStatus(403);

        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    }

    public function test_vendedor_no_puede_eliminar_una_promocion(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);
        $promotion = Promotion::create(['product_id' => $product->id, 'type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1, 'active' => true]);

        Livewire::actingAs($vendedor)
            ->test('promotions.index')
            ->call('delete', $promotion->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('promotions', ['id' => $promotion->id]);
    }

    public function test_vendedor_no_puede_eliminar_una_familia_de_promocion(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $group = PromotionGroup::create(['name' => 'Gaseosas', 'buy_qty' => 3, 'pay_qty' => 2, 'active' => true]);

        Livewire::actingAs($vendedor)
            ->test('promotion-groups.index')
            ->call('delete', $group->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('promotion_groups', ['id' => $group->id]);
    }
}
