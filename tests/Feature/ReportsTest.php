<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_reports_excludes_draft_invoices_and_out_of_range_dates(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $category = Category::create(['name' => 'Bebidas']);
        $product = Product::create(['name' => 'Coca Cola', 'price' => 100, 'category_id' => $category->id]);

        $draft = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'draft',
        ]);
        $draft->items()->create(['product_id' => $product->id, 'description' => 'Coca Cola', 'quantity' => 5, 'unit_price' => 100]);

        $outOfRange = Invoice::create([
            'number' => 'FAC-0002', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now()->subYear(), 'due_date' => now(), 'status' => 'paid',
        ]);
        $outOfRange->items()->create(['product_id' => $product->id, 'description' => 'Coca Cola', 'quantity' => 1, 'unit_price' => 100]);

        $inRange = Invoice::create([
            'number' => 'FAC-0003', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'paid',
        ]);
        $inRange->items()->create(['product_id' => $product->id, 'description' => 'Coca Cola', 'quantity' => 2, 'unit_price' => 100]);

        Livewire::actingAs($this->admin())
            ->test('reports.index')
            ->assertSee('Coca Cola')
            ->assertSee('Bebidas')
            ->assertSee('200,00') // only the in-range paid invoice's total, not draft or out-of-range
            ->assertDontSee('600.00'); // sum if draft/out-of-range were wrongly included
    }

    public function test_reports_incluye_top_clientes_y_ventas_por_dia(): void
    {
        $c1 = Client::create(['name' => 'Distribuidora Norte', 'email' => 'n@test.com']);
        $c2 = Client::create(['name' => 'Kiosco Sur', 'email' => 's@test.com']);

        $inv1 = Invoice::create([
            'number' => 'FAC-1001', 'client_id' => $c1->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'paid',
        ]);
        $inv1->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 5000]);

        $inv2 = Invoice::create([
            'number' => 'FAC-1002', 'client_id' => $c2->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'paid',
        ]);
        $inv2->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 800]);

        $component = Livewire::actingAs($this->admin())->test('reports.index');

        $component->assertSee('Top clientes')
            ->assertSee('Distribuidora Norte')
            ->assertSee('Kiosco Sur')
            ->assertSee('Ventas por día');

        // El de mayor facturación aparece primero en el ranking.
        $component->assertSeeInOrder(['Distribuidora Norte', 'Kiosco Sur']);
    }

    public function test_comparar_dos_periodos_elegidos_a_mano(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        // Período A (los últimos 30 días, rango por defecto): 2 ventas de $500.
        foreach ([1, 2] as $i) {
            $inv = Invoice::create([
                'number' => "A-000{$i}", 'client_id' => $client->id, 'tax_rate' => 0,
                'issue_date' => now(), 'due_date' => now(), 'status' => 'paid',
            ]);
            $inv->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 500]);
        }

        // Período B (el mismo rango pero un año atrás): 1 venta de $1000.
        $invB = Invoice::create([
            'number' => 'B-0001', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now()->subYear(), 'due_date' => now()->subYear(), 'status' => 'paid',
        ]);
        $invB->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000]);

        $component = Livewire::actingAs($this->admin())
            ->test('reports.index')
            ->set('compare', true);

        $comparisonB = $component->viewData('comparisonB');
        $this->assertSame(1, $comparisonB['summary']['count']);
        $this->assertEqualsWithDelta(1000.0, $comparisonB['summary']['total'], 0.01);
        $this->assertSame(2, $component->viewData('summary')['count']);
        $this->assertEqualsWithDelta(1000.0, $component->viewData('summary')['total'], 0.01);
    }

    public function test_sin_activar_comparar_no_se_muestra_el_segundo_periodo(): void
    {
        Livewire::actingAs($this->admin())
            ->test('reports.index')
            ->assertDontSee('Período B')
            ->assertSet('compare', false);
    }
}
