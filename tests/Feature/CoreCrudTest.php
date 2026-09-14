<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoreCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_can_create_and_edit_a_provider(): void
    {
        Livewire::actingAs($this->admin())
            ->test('providers.create')
            ->set('name', 'Distribuidora SA')
            ->set('email', 'ventas@dist.com')
            ->call('save')
            ->assertRedirect(route('providers.index'));

        $provider = Provider::firstWhere('name', 'Distribuidora SA');
        $this->assertNotNull($provider);

        Livewire::actingAs($this->admin())
            ->test('providers.edit', ['provider' => $provider])
            ->set('name', 'Distribuidora SRL')
            ->call('save');

        $this->assertDatabaseHas('providers', ['id' => $provider->id, 'name' => 'Distribuidora SRL']);
    }

    public function test_cannot_delete_provider_with_purchases(): void
    {
        $provider = Provider::create(['name' => 'P1']);
        Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'draft',
        ]);

        Livewire::actingAs($this->admin())->test('providers.index')->call('delete', $provider->id);

        $this->assertDatabaseHas('providers', ['id' => $provider->id]);
    }

    public function test_client_requires_email(): void
    {
        Livewire::actingAs($this->admin())
            ->test('clients.create')
            ->set('name', 'Juan Perez')
            ->set('email', '')
            ->call('save')
            ->assertHasErrors(['email' => 'required']);
    }

    public function test_cannot_delete_client_with_invoices(): void
    {
        $client = Client::create(['name' => 'C1', 'email' => 'c1@test.com']);
        Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'draft',
        ]);

        Livewire::actingAs($this->admin())->test('clients.index')->call('delete', $client->id);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_cannot_delete_client_with_quotes(): void
    {
        $client = Client::create(['name' => 'C1', 'email' => 'c1@test.com']);
        Quote::create([
            'number' => 'PRE-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'draft',
        ]);

        Livewire::actingAs($this->admin())->test('clients.index')->call('delete', $client->id);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_cannot_delete_client_with_payments(): void
    {
        $client = Client::create(['name' => 'C1', 'email' => 'c1@test.com']);
        $payment = ClientPayment::create([
            'client_id' => $client->id, 'date' => now(), 'amount' => 1000, 'method' => PaymentMethod::Efectivo->value,
        ]);

        Livewire::actingAs($this->admin())->test('clients.index')->call('delete', $client->id);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('client_payments', ['id' => $payment->id]);
    }

    public function test_can_create_product_with_category_and_alerts_compute_correctly(): void
    {
        $category = Category::create(['name' => 'Bebidas']);

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Coca Cola')
            ->set('price', '100')
            ->set('cost_price', '150')
            ->set('stock', '2')
            ->set('min_stock', '5')
            ->set('category_id', (string) $category->id)
            ->call('save')
            ->assertRedirect(route('products.index'));

        $product = Product::firstWhere('name', 'Coca Cola');
        $this->assertTrue($product->margin_alert);
        $this->assertTrue($product->stock_alert);
        $this->assertEquals($category->id, $product->category_id);
    }

    /**
     * MEJORA: products.sku no tenía restricción de unicidad — dos productos
     * con el mismo código rompían el escaneo (siempre resolvía el de menor
     * id, sin importar cuál se haya querido escanear en el POS/Compras/
     * Ajustes de Stock).
     */
    public function test_no_se_puede_crear_un_producto_con_un_sku_ya_usado(): void
    {
        Product::create(['name' => 'Termo', 'sku' => '5555', 'price' => 5000]);

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Otro Termo')
            ->set('price', '6000')
            ->set('stock', '0')
            ->set('sku', '5555')
            ->call('save')
            ->assertHasErrors(['sku' => 'unique']);
    }

    public function test_no_se_puede_editar_un_producto_para_usar_un_sku_ajeno(): void
    {
        Product::create(['name' => 'Termo', 'sku' => '5555', 'price' => 5000]);
        $mate = Product::create(['name' => 'Mate', 'sku' => '6666', 'price' => 3000]);

        Livewire::actingAs($this->admin())
            ->test('products.edit', ['product' => $mate])
            ->set('sku', '5555')
            ->call('save')
            ->assertHasErrors(['sku' => 'unique']);
    }

    public function test_editar_un_producto_sin_cambiarle_el_sku_no_choca_con_si_mismo(): void
    {
        $mate = Product::create(['name' => 'Mate', 'sku' => '6666', 'price' => 3000]);

        Livewire::actingAs($this->admin())
            ->test('products.edit', ['product' => $mate])
            ->set('name', 'Mate Imperial')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_dos_productos_sin_sku_no_chocan_entre_si(): void
    {
        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Producto sin sku 1')
            ->set('price', '100')
            ->set('stock', '0')
            ->set('sku', '')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Producto sin sku 2')
            ->set('price', '100')
            ->set('stock', '0')
            ->set('sku', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Product::whereNull('sku')->count());
    }

    public function test_product_search_filters_results(): void
    {
        Product::create(['name' => 'Coca Cola', 'price' => 100]);
        Product::create(['name' => 'Sprite', 'price' => 90]);

        Livewire::actingAs($this->admin())
            ->test('products.index')
            ->set('query', 'coca')
            ->assertSee('Coca Cola')
            ->assertDontSee('Sprite');
    }
}
