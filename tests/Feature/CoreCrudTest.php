<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
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
