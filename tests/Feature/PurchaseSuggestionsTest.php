<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Sucursal;
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

    /**
     * Suggestions.php ahora compara contra el stock de la sucursal activa
     * (product_stocks), no products.stock — hay que sembrar el mismo valor
     * ahí para que estos tests sigan reflejando lo que setean en el producto.
     */
    private function stockearEnSucursalPrincipal(Product $product, int $stock): void
    {
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => $stock]);
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
        $this->stockearEnSucursalPrincipal($bestSeller, 1);
        $this->stockearEnSucursalPrincipal($slowMover, 1);

        $this->sell($bestSeller, $client, 20);
        $this->sell($slowMover, $client, 2);

        $component = Livewire::actingAs($this->admin())->test('purchases.suggestions');

        $suggestions = $component->viewData('suggestions');

        $this->assertSame('Best seller', $suggestions[0]['product']->name);
        $this->assertSame('Slow mover', $suggestions[1]['product']->name);
    }

    public function test_no_sugiere_productos_sin_stock_bajo(): void
    {
        $product = Product::create(['name' => 'Con stock de sobra', 'price' => 100, 'stock' => 100, 'min_stock' => 10]);
        $this->stockearEnSucursalPrincipal($product, 100);

        $component = Livewire::actingAs($this->admin())->test('purchases.suggestions');

        $this->assertCount(0, $component->viewData('suggestions'));
    }

    public function test_muestra_el_proveedor_de_la_ultima_compra(): void
    {
        // Cubre la reescritura de "último proveedor por producto" a una
        // subquery agrupada (MAX(id)) en vez de traer todo el historial de
        // compras y deduplicar en PHP: tiene que seguir devolviendo el
        // proveedor de la compra más reciente, no cualquiera.
        $product = Product::create(['name' => 'Insumo', 'price' => 100, 'stock' => 1, 'min_stock' => 10]);
        $this->stockearEnSucursalPrincipal($product, 1);
        $proveedorViejo = Provider::create(['name' => 'Proveedor Viejo']);
        $proveedorNuevo = Provider::create(['name' => 'Proveedor Nuevo']);

        $compraVieja = Purchase::create(['number' => 'COM-1', 'provider_id' => $proveedorViejo->id, 'tax_rate' => 0, 'issue_date' => now()->subDays(10), 'due_date' => now(), 'status' => 'paid']);
        $compraVieja->items()->create(['product_id' => $product->id, 'description' => 'Insumo', 'quantity' => 1, 'unit_price' => 100]);

        $compraNueva = Purchase::create(['number' => 'COM-2', 'provider_id' => $proveedorNuevo->id, 'tax_rate' => 0, 'issue_date' => now(), 'due_date' => now(), 'status' => 'paid']);
        $compraNueva->items()->create(['product_id' => $product->id, 'description' => 'Insumo', 'quantity' => 1, 'unit_price' => 100]);

        $component = Livewire::actingAs($this->admin())->test('purchases.suggestions');
        $suggestions = $component->viewData('suggestions');

        $this->assertSame('Proveedor Nuevo', $suggestions[0]['lastProvider']);
    }

    public function test_ignora_ventas_en_borrador_al_calcular_la_prioridad(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $product = Product::create(['name' => 'Producto', 'price' => 100, 'stock' => 1, 'min_stock' => 10]);
        $this->stockearEnSucursalPrincipal($product, 1);
        $this->sell($product, $client, 50, 'draft');

        $component = Livewire::actingAs($this->admin())->test('purchases.suggestions');

        $suggestions = $component->viewData('suggestions');

        $this->assertSame(0.0, $suggestions[0]['soldQty']);
    }

    public function test_no_considera_stock_ni_ventas_de_otra_sucursal(): void
    {
        // MEJORA: antes comparaba contra stock/ventas de TODA la empresa —
        // acá la sucursal activa está en cero pero "Norte" tiene de sobra;
        // tiene que sugerir comprar igual (no debe verse afectada por Norte).
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $principal->id]);

        $product = Product::create(['name' => 'Insumo', 'price' => 100, 'stock' => 0, 'min_stock' => 5]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 0]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 50]);

        $clienteNorte = Client::create(['name' => 'Cliente Norte', 'email' => 'norte@test.com']);
        $invoiceNorte = Invoice::create([
            'number' => 'FAC-'.uniqid(), 'client_id' => $clienteNorte->id, 'sucursal_id' => $norte->id,
            'issue_date' => now()->subDays(5), 'due_date' => now()->addDays(10), 'tax_rate' => 0, 'status' => 'paid',
        ]);
        $invoiceNorte->items()->create(['product_id' => $product->id, 'description' => 'Insumo', 'quantity' => 30, 'unit_price' => 100]);

        $component = Livewire::actingAs($vendedor)->test('purchases.suggestions');
        $suggestions = $component->viewData('suggestions');

        $this->assertCount(1, $suggestions);
        $this->assertSame(0.0, $suggestions[0]['soldQty']);
        $this->assertSame(5, $suggestions[0]['suggestedQty']);
    }
}
