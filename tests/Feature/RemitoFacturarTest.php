<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RemitoFacturarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function remito(Product $product, int $stockActual = 8): Invoice
    {
        // El remito ya descontó stock: dejamos el producto en su stock post-remito.
        $product->update(['stock' => $stockActual]);

        $client = Client::create(['name' => 'Cliente Remito', 'email' => 'r@test.com']);
        $remito = Invoice::create([
            'number' => '0001-00000001',
            'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::RemitoX,
            'issue_date' => now(), 'due_date' => now(), 'tax_rate' => 0, 'status' => 'pending',
        ]);
        $remito->items()->create([
            'product_id' => $product->id, 'description' => 'Mercadería', 'quantity' => 2, 'unit_price' => 1000, 'iva_rate' => 21,
        ]);

        return $remito;
    }

    public function test_facturar_remito_crea_factura_vinculada_sin_mover_stock(): void
    {
        $product = Product::create(['name' => 'Caja', 'price' => 1000, 'iva_rate' => 21, 'stock' => 10]);
        $remito = $this->remito($product, 8);

        Livewire::actingAs($this->admin())
            ->test('invoices.facturar-remito', ['invoice' => $remito])
            ->set('tipo_comprobante_interno', TipoComprobanteInterno::FacturaB->value)
            ->call('save')
            ->assertHasNoErrors();

        $factura = Invoice::where('remito_id', $remito->id)->first();
        $this->assertNotNull($factura);
        $this->assertSame(TipoComprobanteInterno::FacturaB, $factura->tipo_comprobante_interno);
        $this->assertFalse((bool) $factura->afecta_stock);
        $this->assertCount(1, $factura->items);
        // El stock NO se vuelve a tocar (lo movió el remito).
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_no_se_puede_facturar_dos_veces_el_mismo_remito(): void
    {
        $product = Product::create(['name' => 'Caja', 'price' => 1000, 'iva_rate' => 21, 'stock' => 10]);
        $remito = $this->remito($product);

        Livewire::actingAs($this->admin())
            ->test('invoices.facturar-remito', ['invoice' => $remito])
            ->call('save')
            ->assertHasNoErrors();

        // Ya facturado: intentar entrar de nuevo aborta con 403.
        Livewire::actingAs($this->admin())
            ->test('invoices.facturar-remito', ['invoice' => $remito->fresh()])
            ->assertStatus(403);
    }

    public function test_no_se_puede_facturar_algo_que_no_es_remito(): void
    {
        $client = Client::create(['name' => 'X', 'email' => 'x@test.com']);
        $factura = Invoice::create([
            'number' => '0001-00000009', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaB,
            'issue_date' => now(), 'due_date' => now(), 'tax_rate' => 0, 'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin())
            ->test('invoices.facturar-remito', ['invoice' => $factura])
            ->assertStatus(403);
    }

    public function test_remito_pdf_responde_con_y_sin_precios(): void
    {
        $product = Product::create(['name' => 'Caja', 'price' => 1000, 'iva_rate' => 21, 'stock' => 10]);
        $remito = $this->remito($product);

        $con = $this->actingAs($this->admin())->get(route('invoices.remito-pdf', $remito));
        $con->assertOk()->assertHeader('content-type', 'application/pdf');

        $sin = $this->actingAs($this->admin())->get(route('invoices.remito-pdf', ['invoice' => $remito, 'precios' => 0]));
        $sin->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
