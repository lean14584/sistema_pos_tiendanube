<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function seedVenta(): void
    {
        $client = Client::create(['name' => 'Distribuidora Norte', 'email' => 'n@test.com']);
        $product = Product::create(['name' => 'Yerba', 'price' => 1500, 'iva_rate' => 0, 'stock' => 10]);
        $inv = Invoice::create([
            'number' => 'FAC-2001', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'paid',
        ]);
        $inv->items()->create(['product_id' => $product->id, 'description' => 'Yerba', 'quantity' => 2, 'unit_price' => 1500]);
    }

    public function test_export_csv_devuelve_un_csv_con_los_datos(): void
    {
        $this->seedVenta();

        $response = $this->actingAs($this->admin())->get(route('reports.export.csv', [
            'fromDate' => now()->subDays(5)->toDateString(),
            'toDate' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Informe de ventas', $content);
        $this->assertStringContainsString('Distribuidora Norte', $content);
        $this->assertStringContainsString('Yerba', $content);
        $this->assertStringContainsString('3000,00', $content); // 2 x 1500
    }

    public function test_export_pdf_devuelve_un_pdf(): void
    {
        $this->seedVenta();

        $response = $this->actingAs($this->admin())->get(route('reports.export.pdf', [
            'fromDate' => now()->subDays(5)->toDateString(),
            'toDate' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_valida_el_rango_de_fechas(): void
    {
        $response = $this->actingAs($this->admin())->get(route('reports.export.csv', [
            'fromDate' => now()->toDateString(),
            'toDate' => now()->subDays(5)->toDateString(), // hasta < desde
        ]));

        $response->assertSessionHasErrors('toDate');
    }
}
