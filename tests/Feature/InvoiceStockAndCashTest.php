<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\CashLinker;
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
            'sucursal_id' => Sucursal::sole()->id,
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

    public function test_no_puede_agregar_un_pago_al_editar_factura_sin_caja_abierta(): void
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
        $invoice->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 1, 'unit_price' => 1000]);

        Livewire::actingAs($this->admin())
            ->test('invoices.edit', ['invoice' => $invoice])
            ->call('addPayment')
            ->set('payments.0.amount', '1000')
            ->call('save')
            ->assertHasErrors('payments');

        $this->assertSame(0, $invoice->fresh()->payments->count());
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
        $this->actingAs($admin);
        CashLinker::linkInvoiceRefund($invoice, $payment);

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, CashMovement::count());

        Livewire::actingAs($admin)
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('delete');

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, CashMovement::count());
    }

    /**
     * MEJORA: borrar un comprobante revierte stock y desvincula pagos de
     * caja — antes cualquier rol con acceso al módulo 'invoices' (Cajero,
     * Vendedor) podía hacerlo, sin ningún chequeo más fino que ese. Ahora
     * queda reservado a Admin/Encargado; Cajero sigue pudiendo emitir a
     * ARCA sin problema, solo se le saca borrar.
     */
    public function test_cajero_no_puede_eliminar_un_comprobante(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $invoice = Invoice::create([
            'number' => 'REM-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => 'remito_x', 'sucursal_id' => Sucursal::sole()->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'tax_rate' => 0, 'status' => 'draft',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 2, 'unit_price' => 1000]);
        $product->decrement('stock', 2);

        Livewire::actingAs($cajero)
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('delete')
            ->assertStatus(403);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertSame(3, $product->fresh()->stock, 'El stock no debería tocarse si el borrado se rechazó.');
    }

    public function test_vendedor_no_puede_eliminar_un_comprobante(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $invoice = Invoice::create([
            'number' => 'REM-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => 'remito_x', 'sucursal_id' => Sucursal::sole()->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'tax_rate' => 0, 'status' => 'draft',
        ]);

        Livewire::actingAs($vendedor)
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('delete')
            ->assertStatus(403);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_encargado_si_puede_eliminar_un_comprobante(): void
    {
        $encargado = User::factory()->create(['role' => Role::Encargado, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $invoice = Invoice::create([
            'number' => 'REM-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => 'remito_x', 'sucursal_id' => Sucursal::sole()->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'tax_rate' => 0, 'status' => 'draft',
        ]);

        Livewire::actingAs($encargado)
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('delete');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_editar_una_factura_de_otra_sucursal_ajusta_el_stock_ahi_no_en_la_activa_del_admin(): void
    {
        // Centro queda como sucursal "activa" del admin por default (la
        // primera creada, ver CurrentSucursal::id()); la factura es de
        // Norte. Antes del fix, StockAdjuster::apply() no recibía la
        // sucursal de la factura y usaba la activa del admin (Centro).
        $centro = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa', 'punto_venta' => 1]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 200]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $centro->id, 'stock' => 100]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 100]);

        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'sucursal_id' => $norte->id,
            'tipo_comprobante_interno' => 'factura_b',
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'tax_rate' => 0, 'status' => 'draft',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 3, 'unit_price' => 1000]);

        Livewire::actingAs($this->admin())
            ->test('invoices.edit', ['invoice' => $invoice])
            ->set('items.0.quantity', '5') // de 3 a 5: -2 de stock netos
            ->call('save');

        $stockNorte = ProductStock::where('product_id', $product->id)->where('sucursal_id', $norte->id)->value('stock');
        $stockCentro = ProductStock::where('product_id', $product->id)->where('sucursal_id', $centro->id)->value('stock');

        $this->assertSame(98, $stockNorte); // 100 - 2, la sucursal de la factura
        $this->assertSame(100, $stockCentro); // sin cambios, no es la sucursal del admin
    }

    public function test_editar_una_factura_de_otra_sucursal_usa_la_caja_de_esa_sucursal_no_la_activa_del_admin(): void
    {
        // El admin tiene la caja abierta en Norte (la sucursal DE LA
        // FACTURA), no en Centro (su sucursal "activa" por default). Antes
        // del fix, CashLinker buscaba la caja abierta del admin en Centro
        // (vía CurrentSucursal::id()), no la encontraba, y bloqueaba el
        // guardado con "Tenés que abrir la caja" pese a que sí había una
        // caja abierta válida en la sucursal correcta.
        $centro = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa', 'punto_venta' => 1]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => $norte->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'sucursal_id' => $norte->id,
            'tipo_comprobante_interno' => 'factura_b',
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'tax_rate' => 0, 'status' => 'draft',
        ]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 1, 'unit_price' => 1000]);

        $component = Livewire::actingAs($admin)
            ->test('invoices.edit', ['invoice' => $invoice])
            ->call('addPayment')
            ->set('payments.0.amount', '1000')
            ->set('payments.0.method', 'efectivo')
            ->call('save');

        $component->assertHasNoErrors('payments');
        $movimiento = CashMovement::first();
        $this->assertNotNull($movimiento);
        $this->assertSame($norte->id, $movimiento->session->sucursal_id);
    }
}
