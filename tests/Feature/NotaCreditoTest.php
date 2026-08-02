<?php

namespace Tests\Feature;

use App\Enums\CondicionIva;
use App\Enums\Role;
use App\Enums\TipoComprobante;
use App\Enums\TipoComprobanteInterno;
use App\Enums\TipoDocumento;
use App\Exceptions\Afip\AfipValidationException;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\InvoiceCaeEmitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeAfipGateway;
use Tests\TestCase;

class NotaCreditoTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): FakeAfipGateway
    {
        $fake = new FakeAfipGateway();
        $this->app->instance(AfipGatewayInterface::class, $fake);

        return $fake;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function openCashSession(User $user): CashSession
    {
        return CashSession::create([
            'user_id' => $user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 0,
        ]);
    }

    /**
     * Factura B emitida con 2 ítems, para acreditar parcialmente.
     */
    private function facturaEmitida(FakeAfipGateway $fake, Product $product): Invoice
    {
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);

        $client = Client::create([
            'name' => 'Cliente Test', 'email' => 'cliente@test.com',
            'tax_id' => '20111111112', 'condicion_iva' => CondicionIva::ConsumidorFinal->value,
            'tipo_documento' => TipoDocumento::SinIdentificar->value,
        ]);

        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaB,
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 0, 'status' => 'draft',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 3, 'unit_price' => 1000]);
        $product->decrement('stock', 3); // simula el descuento que hace Invoices/Create al vender

        return app(InvoiceCaeEmitter::class)->emit($invoice->fresh());
    }

    public function test_puede_emitir_nota_de_credito_parcial_vinculada_a_la_factura_original(): void
    {
        $fake = $this->fake();
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);
        $factura = $this->facturaEmitida($fake, $product);

        Livewire::actingAs($this->admin())
            ->test('notas-credito.create', ['invoice' => $factura])
            ->set('items.0.quantity', '1')
            ->call('save');

        $nota = Invoice::where('related_invoice_id', $factura->id)->first();
        $this->assertNotNull($nota);
        $this->assertStringStartsWith('NC-', $nota->number);
        $this->assertSame(TipoComprobanteInterno::NotaCreditoB, $nota->tipo_comprobante_interno);
        $this->assertEqualsWithDelta(1000.0, (float) $nota->total, 0.01);
    }

    public function test_emitir_a_afip_la_nota_de_credito_manda_el_comprobante_asociado(): void
    {
        $fake = $this->fake();
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);
        $factura = $this->facturaEmitida($fake, $product);

        Livewire::actingAs($this->admin())
            ->test('notas-credito.create', ['invoice' => $factura])
            ->set('items.0.quantity', '1')
            ->call('save');

        $nota = Invoice::where('related_invoice_id', $factura->id)->first();
        $emitida = app(InvoiceCaeEmitter::class)->emit($nota);

        $this->assertTrue($emitida->isFiscal);
        $this->assertSame(TipoComprobante::NotaCreditoB, $emitida->tipo_comprobante);

        $ultimaRequest = end($fake->requestsRecibidas);
        $this->assertNotNull($ultimaRequest->comprobanteAsociado);
        $this->assertSame(TipoComprobante::FacturaB, $ultimaRequest->comprobanteAsociado->tipo);
        $this->assertSame($factura->punto_venta, $ultimaRequest->comprobanteAsociado->puntoVenta);
        $this->assertSame($factura->numero_comprobante_afip, $ultimaRequest->comprobanteAsociado->numero);
    }

    public function test_afecta_stock_marcado_repone_stock(): void
    {
        $fake = $this->fake();
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);
        $factura = $this->facturaEmitida($fake, $product);
        $this->assertSame(7, $product->fresh()->stock); // 10 - 3 vendidas

        Livewire::actingAs($this->admin())
            ->test('notas-credito.create', ['invoice' => $factura])
            ->set('afecta_stock', true)
            ->set('items.0.quantity', '2')
            ->call('save');

        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_afecta_stock_desmarcado_no_toca_stock(): void
    {
        $fake = $this->fake();
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);
        $factura = $this->facturaEmitida($fake, $product);
        $this->assertSame(7, $product->fresh()->stock);

        Livewire::actingAs($this->admin())
            ->test('notas-credito.create', ['invoice' => $factura])
            ->set('afecta_stock', false)
            ->set('items.0.quantity', '2')
            ->call('save');

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_reintegro_genera_egreso_de_caja(): void
    {
        $fake = $this->fake();
        $admin = $this->admin();
        $this->openCashSession($admin);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);
        $factura = $this->facturaEmitida($fake, $product);

        Livewire::actingAs($admin)
            ->test('notas-credito.create', ['invoice' => $factura])
            ->set('items.0.quantity', '1')
            ->call('addPayment')
            ->set('payments.0.amount', '1000')
            ->call('save');

        $movimiento = CashMovement::first();
        $this->assertNotNull($movimiento);
        $this->assertSame('egreso', $movimiento->type->value);
        $this->assertSame('devolucion', $movimiento->source->value);
    }

    public function test_no_se_puede_acreditar_mas_del_saldo_de_la_factura_original(): void
    {
        $fake = $this->fake();
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);
        $factura = $this->facturaEmitida($fake, $product); // total = 3000

        Livewire::actingAs($this->admin())
            ->test('notas-credito.create', ['invoice' => $factura])
            ->set('items.0.quantity', '3')
            ->set('items.0.unit_price', '5000') // fuerza un total > al de la factura
            ->call('save');

        $nota = Invoice::where('related_invoice_id', $factura->id)->first();

        $this->expectException(AfipValidationException::class);
        app(InvoiceCaeEmitter::class)->emit($nota);
    }

    public function test_no_se_puede_emitir_nota_de_credito_para_una_factura_sin_cae(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $invoice = Invoice::create([
            'number' => 'FAC-0002', 'client_id' => $client->id,
            'tipo_comprobante_interno' => 'factura_b',
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 0, 'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Producto', 'quantity' => 1, 'unit_price' => 1000]);

        Livewire::actingAs($this->admin())
            ->test('notas-credito.create', ['invoice' => $invoice])
            ->assertForbidden();
    }
}
