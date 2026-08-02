<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_stats_for_admin(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $paid = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'paid',
        ]);
        $paid->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 500]);

        Livewire::actingAs($admin)
            ->test('dashboard')
            ->assertSee('FAC-0001')
            ->assertSee('500.00');
    }

    public function test_cajero_does_not_see_invoice_links_on_dashboard(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $response = $this->actingAs($cajero)->get('/');

        $response->assertOk();
        $response->assertDontSee('Nueva factura');
    }

    public function test_dashboard_shows_top_selling_products(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = \App\Models\Product::create(['name' => 'Notebook Top', 'price' => 1000, 'stock' => 10]);

        $invoice = Invoice::create([
            'number' => 'FAC-0002', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'paid',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => 'Notebook Top', 'quantity' => 2, 'unit_price' => 1000]);

        Livewire::actingAs($admin)
            ->test('dashboard')
            ->assertSee('Top 5 productos más vendidos')
            ->assertSee('Notebook Top');
    }

    public function test_dashboard_filters_monthly_sales_by_selected_year(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $thisYear = (int) now()->year;
        $lastYear = (int) now()->subYear()->year;

        $invoice = Invoice::create([
            'number' => 'FAC-0003', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now()->subYear(), 'due_date' => now()->subYear()->addDays(15), 'status' => 'paid',
        ]);
        $invoice->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 777]);

        // Sin ventas en el año actual (todo lo cargado es del año pasado)...
        Livewire::actingAs($admin)
            ->test('dashboard')
            ->assertSee("Sin ventas registradas en {$thisYear}")
            // ...pero al cambiar el selector al año pasado, el gráfico deja de mostrar el mensaje vacío.
            ->set('year', $lastYear)
            ->assertDontSee("Sin ventas registradas en {$lastYear}");
    }

    public function test_el_conteo_de_stock_bajo_se_consulta_una_sola_vez_por_request(): void
    {
        // El sidebar (todas las páginas) y el Dashboard piden el mismo
        // conteo; sin memoizar Product::lowStockCountCached() esto corre
        // la query de min_stock dos veces en la misma request.
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        Product::create(['name' => 'Bajo stock', 'price' => 100, 'stock' => 1, 'min_stock' => 5]);

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/')->assertOk();
        // Filtra específicamente el COUNT (no la lista de productos con
        // stock bajo, que es una query aparte y necesaria en el Dashboard).
        $lowStockCountQueries = collect(DB::getQueryLog())
            ->filter(fn ($entry) => str_contains($entry['query'], 'min_stock') && str_contains(strtolower($entry['query']), 'count('));
        DB::disableQueryLog();

        $this->assertCount(1, $lowStockCountQueries);
    }
}
