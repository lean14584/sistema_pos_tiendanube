<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceStockAndCashTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_crear_factura_b_descuenta_stock(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->set('tipo_comprobante_interno', 'factura_b')
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '3')
            ->call('save');

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_remito_x_descuenta_stock_pero_no_genera_movimiento_de_caja(): void
    {
        $admin = $this->admin();
        $this->openCashSession($admin);

        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        Livewire::actingAs($admin)
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->set('tipo_comprobante_interno', 'remito_x')
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '2')
            ->call('save');

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(0, CashMovement::count());
    }

    public function test_devolucion_repone_stock_y_genera_egreso_de_caja(): void
    {
        $admin = $this->admin();
        $this->openCashSession($admin);

        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->set('tipo_comprobante_interno', 'devolucion')
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '2')
            ->call('addPayment')
            ->set('payments.0.amount', '2000')
            ->call('save');

        $this->assertSame(7, $product->fresh()->stock);

        $movimiento = CashMovement::first();
        $this->assertNotNull($movimiento);
        $this->assertSame('egreso', $movimiento->type->value);
        $this->assertSame('devolucion', $movimiento->source->value);
        $this->assertEqualsWithDelta(2000.0, (float) $movimiento->amount, 0.01);
    }

    public function test_editar_de_factura_b_a_devolucion_deja_el_stock_neto_correcto(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        $invoice = Invoice::create([
            'number' => 'FAC-0001',
            'client_id' => $client->id,
            'tipo_comprobante_interno' => 'factura_b',
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'tax_rate' => 0,
            'status' => 'draft',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 3, 'unit_price' => 1000]);
        $product->decrement('stock', 3); // simula el descuento que hizo Create originalmente
        $this->assertSame(7, $product->fresh()->stock);

        Livewire::actingAs($this->admin())
            ->test('invoices.edit', ['invoice' => $invoice])
            ->set('tipo_comprobante_interno', 'devolucion')
            ->call('save');

        // Reversa la baja original (+3) y aplica la suba de la devolución (+3) = +6 sobre el stock ya descontado.
        $this->assertSame(13, $product->fresh()->stock);
    }

    public function test_borrar_una_devolucion_revierte_stock_y_borra_el_movimiento_de_caja(): void
    {
        $admin = $this->admin();
        $this->openCashSession($admin);

        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $invoice = Invoice::create([
            'number' => 'DEV-0001',
            'client_id' => $client->id,
            'tipo_comprobante_interno' => 'devolucion',
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'tax_rate' => 0,
            'status' => 'draft',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 2, 'unit_price' => 1000]);
        $product->increment('stock', 2);
        $payment = $invoice->payments()->create(['method' => 'efectivo', 'amount' => 2000]);
        \App\Support\CashLinker::linkInvoiceRefund($invoice, $payment);

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, CashMovement::count());

        Livewire::actingAs($admin)
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('delete');

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, CashMovement::count());
    }
}
