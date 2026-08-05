<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function sell(Product $product, Client $client, int $quantity, string $status = 'paid'): void
    {
        $invoice = Invoice::create([
            'number' => 'FAC-'.uniqid(),
            'client_id' => $client->id,
            'issue_date' => now()->subDays(5),
            'due_date' => now()->addDays(10),
            'tax_rate' => 0,
            'status' => $status,
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
        ]);
    }

    public function test_prioriza_productos_con_mas_ventas_por_encima_de_los_que_venden_menos(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $bestSeller = Product::create(['name' => 'Best seller', 'price' => 100, 'stock' => 1, 'min_stock' => 10]);
        $slowMover = Product::create(['name' => 'Slow mover', 'price' => 100, 'stock' => 1, 'min_stock' => 10]);

        $this->sell($bestSeller, $client, 20);
        $this->sell($slowMover, $client, 2);

        $component = Livewire::actingAs($this->admin())->test('purchases.suggestions');

        $suggestions = $component->viewData('suggestions');

        $this->assertSame('Best seller', $suggestions[0]['product']->name);
        $this->assertSame('Slow mover', $suggestions[1]['product']->name);
    }

    public function test_no_sugiere_productos_sin_stock_bajo(): void
    {
        Product::create(['name' => 'Con stock de sobra', 'price' => 100, 'stock' => 100, 'min_stock' => 10]);

        $component = Livewire::actingAs($this->admin())->test('purchases.suggestions');

        $this->assertCount(0, $component->viewData('suggestions'));
    }

    public function test_ignora_ventas_en_borrador_al_calcular_la_prioridad(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $product = Product::create(['name' => 'Producto', 'price' => 100, 'stock' => 1, 'min_stock' => 10]);
        $this->sell($product, $client, 50, 'draft');

        $component = Livewire::actingAs($this->admin())->test('purchases.suggestions');

        $suggestions = $component->viewData('suggestions');

        $this->assertSame(0.0, $suggestions[0]['soldQty']);
    }
}
