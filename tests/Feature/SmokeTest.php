<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_route_renders_successfully(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $category = Category::create(['name' => 'Bebidas']);
        $product = Product::create(['name' => 'Coca Cola', 'price' => 100, 'category_id' => $category->id]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $otherUser = User::factory()->create();

        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'tax_rate' => 21,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'paid',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 100]);

        $quote = Quote::create([
            'number' => 'PRE-0001', 'client_id' => $client->id, 'tax_rate' => 21,
            'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'draft',
        ]);
        $quote->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 100]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 21,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'pending',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 90]);

        $routes = [
            'dashboard',
            'quotes.index', 'quotes.create', ['quotes.show', $quote], ['quotes.edit', $quote],
            'invoices.index', 'invoices.create', ['invoices.show', $invoice], ['invoices.edit', $invoice],
            'clients.index', 'clients.create', ['clients.edit', $client], ['clients.account', $client],
            'products.index', 'products.create', ['products.edit', $product],
            'categories.index', 'categories.create', ['categories.edit', $category],
            'providers.index', 'providers.create', ['providers.edit', $provider], ['providers.account', $provider],
            'purchases.index', 'purchases.create', ['purchases.show', $purchase], ['purchases.edit', $purchase],
            'cash-register.index',
            'reports.index',
            'users.index', 'users.create', ['users.edit', $otherUser],
        ];

        foreach ($routes as $route) {
            [$name, $param] = is_array($route) ? $route : [$route, null];
            $url = $param ? route($name, $param) : route($name);

            $response = $this->actingAs($admin)->get($url);

            $response->assertOk();
        }

        // PDF is a plain controller response, not a Livewire page — check separately.
        $this->actingAs($admin)->get(route('invoices.pdf', $invoice))->assertOk();
    }

    public function test_login_and_logout_round_trip(): void
    {
        $admin = User::factory()->create(['username' => 'smoketest', 'password' => 'secret123', 'role' => Role::Admin, 'active' => true]);

        $this->get('/')->assertRedirect(route('login'));

        \Livewire\Livewire::test('auth.login')
            ->set('username', 'smoketest')
            ->set('password', 'secret123')
            ->call('submit')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->actingAs($admin)->post(route('logout'))->assertRedirect(route('login'));
    }
}
