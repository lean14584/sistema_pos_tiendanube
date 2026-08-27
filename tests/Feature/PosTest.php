<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_agrega_un_producto_por_codigo_de_barras(): void
    {
        Product::create(['name' => 'Gaseosa', 'sku' => '7791234567890', 'price' => 800, 'iva_rate' => 21, 'stock' => 10]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '7791234567890')
            ->call('addByBarcode');

        $this->assertCount(1, $pos->get('cart'));
        $pos->assertSet('barcode', ''); // se limpia para el próximo escaneo
    }

    public function test_buscar_cliente_encuentra_por_nombre_y_seleccionarlo_lo_deja_como_cliente_actual(): void
    {
        $client = Client::create(['name' => 'Distribuidora Norte', 'email' => 'dn@test.com', 'phone' => '3511234567']);
        Client::create(['name' => 'Otro Cliente', 'email' => 'otro@test.com']);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('clientQuery', 'Distribuidora');

        $this->assertCount(1, $pos->get('clientResults'));
        $this->assertSame($client->id, $pos->get('clientResults')->first()->id);

        $pos->call('selectClient', $client->id)
            ->assertSet('client_id', $client->id)
            ->assertSet('clientQuery', '');
    }

    public function test_seleccionar_cliente_recotiza_el_carrito_con_su_lista_de_precios(): void
    {
        $mayorista = PriceList::create(['name' => 'Mayorista', 'adjustment_percent' => -10, 'is_default' => false, 'active' => true]);
        $client = Client::create(['name' => 'Distribuidora Norte', 'email' => 'dn@test.com', 'price_list_id' => $mayorista->id]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('selectClient', $client->id);

        $this->assertEqualsWithDelta(900.0, $pos->get('cart')[0]['unit_price'], 0.01);
    }

    public function test_codigo_inexistente_muestra_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', 'NO-EXISTE')
            ->call('addByBarcode')
            ->assertHasErrors('barcode');
    }

    public function test_cobrar_crea_la_venta_descuenta_stock_y_registra_caja(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Alfajor', 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id) // cantidad 2
            ->call('addPayment') // prellena efectivo con el total (1000)
            ->set('printOnSale', false)
            ->call('cobrar');

        $this->assertDatabaseHas('invoices', ['status' => 'paid']);
        $this->assertEquals(3, $product->fresh()->stock); // 5 - 2
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 1000, 'source' => 'venta']);
    }

    public function test_pago_parcial_deja_saldo_en_cuenta_del_cliente(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Yerba', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        $cliente = Client::create(['name' => 'Juan Perez', 'email' => 'juan@test.com', 'condicion_iva' => 'consumidor_final', 'tipo_documento' => 'sin_identificar']);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('client_id', $cliente->id)
            ->call('addProduct', $product->id) // total 1000
            ->call('addPayment') // efectivo 1000
            ->set('payments.0.amount', '600') // paga solo 600
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        // Factura queda pendiente y solo entra a caja lo efectivamente pagado.
        $this->assertDatabaseHas('invoices', ['client_id' => $cliente->id, 'status' => 'pending']);
        $this->assertDatabaseHas('invoice_payments', ['method' => 'efectivo', 'amount' => 600]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 600, 'source' => 'venta']);
    }

    public function test_saldo_pendiente_a_consumidor_final_es_rechazado(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Pan', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);

        Livewire::actingAs($admin)
            ->test('pos.index') // client_id queda en Consumidor Final por defecto
            ->call('addProduct', $product->id)
            ->call('cobrar') // sin pagos => quedaría saldo a Consumidor Final
            ->assertHasErrors('client_id');

        $this->assertDatabaseCount('invoices', 0);
    }
}
