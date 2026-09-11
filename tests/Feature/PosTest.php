<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Sucursal;
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

    public function test_agrega_un_producto_escaneando_el_ean13_impreso_en_la_etiqueta(): void
    {
        // El sku es un código interno corto ("9876"), no un EAN13 real: la
        // etiqueta imprime el código de Ean13::fromSku (con ceros a la
        // izquierda + verificador), y el escaneo tiene que reconocerlo y
        // recuperar el producto por su sku original, no por el código largo.
        $product = Product::create(['name' => 'Plato De Porcelana', 'sku' => '9876', 'price' => 2000, 'iva_rate' => 21, 'stock' => 5]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '0000000098762')
            ->call('addByBarcode');

        $pos->assertHasNoErrors('barcode');
        $this->assertCount(1, $pos->get('cart'));
        $this->assertSame($product->id, $pos->get('cart')[0]['product_id']);
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

    private function activarBalanza(): void
    {
        CompanySettings::current()->update([
            'barcode_scale_enabled' => true,
            'barcode_scale_prefix' => '20',
            'barcode_scale_code_digits' => 5,
            'barcode_scale_weight_digits' => 5,
        ]);
    }

    public function test_escanear_codigo_de_balanza_agrega_el_producto_por_el_peso_leido(): void
    {
        $this->activarBalanza();
        // Precio por kilo: $2000. Peso leído: 0.35 kg (350g) → línea de $700.
        Product::create(['name' => 'Jamón cocido', 'sku' => '00023', 'sold_by_weight' => true, 'price' => 2000, 'iva_rate' => 21, 'stock' => 0]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '2000023003505')
            ->call('addByBarcode');

        $pos->assertHasNoErrors('barcode');
        $this->assertCount(1, $pos->get('cart'));
        $this->assertEqualsWithDelta(0.35, $pos->get('cart')[0]['quantity'], 0.0001);
        $this->assertTrue($pos->get('cart')[0]['by_weight']);
        // $700 neto (0.35kg x $2000/kg) + 21% IVA = $847.
        $this->assertEqualsWithDelta(847.0, $pos->instance()->lineTotal($pos->get('cart')[0]), 0.01);
    }

    public function test_escanear_el_mismo_producto_pesado_dos_veces_agrega_dos_lineas_no_las_suma(): void
    {
        $this->activarBalanza();
        Product::create(['name' => 'Jamón cocido', 'sku' => '00023', 'sold_by_weight' => true, 'price' => 2000, 'iva_rate' => 21, 'stock' => 0]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '2000023003505')->call('addByBarcode')
            ->set('barcode', '2000023003505')->call('addByBarcode');

        $this->assertCount(2, $pos->get('cart'));
    }

    public function test_vender_un_producto_pesado_no_toca_el_stock(): void
    {
        $this->activarBalanza();
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $product = Product::create(['name' => 'Jamón cocido', 'sku' => '00023', 'sold_by_weight' => true, 'price' => 2000, 'iva_rate' => 0, 'stock' => 0]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('barcode', '2000023003505')->call('addByBarcode')
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar');

        $this->assertDatabaseHas('invoices', ['status' => 'paid']);
        $this->assertEquals(0, $product->fresh()->stock); // sin control de stock, no se toca
    }

    public function test_codigo_de_balanza_con_checksum_invalido_cae_al_match_exacto_y_falla(): void
    {
        $this->activarBalanza();

        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '2000023003506') // mismo código, dígito verificador corrupto
            ->call('addByBarcode')
            ->assertHasErrors('barcode');
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
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Alfajor', 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id) // cantidad 2
            ->call('addPayment') // prellena el monto con el total (1000), sin medio elegido
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar');

        $this->assertDatabaseHas('invoices', ['status' => 'paid']);
        $this->assertEquals(3, $product->fresh()->stock); // 5 - 2
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 1000, 'source' => 'venta']);
    }

    public function test_cobrar_sin_caja_abierta_es_rechazado(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Alfajor', 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasErrors('cart');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertEquals(5, $product->fresh()->stock);
    }

    public function test_pago_parcial_deja_saldo_en_cuenta_del_cliente(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Yerba', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        $cliente = Client::create(['name' => 'Juan Perez', 'email' => 'juan@test.com', 'condicion_iva' => 'consumidor_final', 'tipo_documento' => 'sin_identificar']);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('client_id', $cliente->id)
            ->call('addProduct', $product->id) // total 1000
            ->call('addPayment') // prellena 1000, sin medio elegido
            ->set('payments.0.method', 'efectivo')
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
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        Livewire::actingAs($admin)
            ->test('pos.index') // client_id queda en Consumidor Final por defecto
            ->call('addProduct', $product->id)
            ->call('cobrar') // sin pagos => quedaría saldo a Consumidor Final
            ->assertHasErrors('client_id');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_descuento_por_medio_de_pago_se_aplica_prorrateado_cuando_la_venta_queda_pagada_por_completo(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        CompanySettings::current()->update(['descuento_efectivo_pct' => 15, 'descuento_transferencia_pct' => 10]);

        $product = Product::create(['name' => 'Producto', 'price' => 10000, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id) // total 10000
            ->call('addPayment') // prellena el monto con 10000, sin medio elegido
            ->set('payments.0.method', 'tarjeta')
            ->set('payments.0.amount', '5000')
            ->call('addPayment') // prellena el segundo con el saldo (5000), sin medio elegido
            ->set('payments.1.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        // 5000 sin descuento (tarjeta) + 5000*0.85 (efectivo, 15% off) = 9250.
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('paid', $invoice->status->value);
        $this->assertEqualsWithDelta(9250, (float) $invoice->total, 0.01);
        $this->assertDatabaseHas('invoice_payments', ['method' => 'tarjeta', 'amount' => 5000]);
        $this->assertDatabaseHas('invoice_payments', ['method' => 'efectivo', 'amount' => 4250]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 4250, 'source' => 'venta']);
    }

    public function test_descuento_por_medio_de_pago_no_se_aplica_si_queda_saldo_en_cuenta_corriente(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        CompanySettings::current()->update(['descuento_efectivo_pct' => 15]);

        $product = Product::create(['name' => 'Producto', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);
        $cliente = Client::create(['name' => 'Juan Perez', 'email' => 'juan@test.com', 'condicion_iva' => 'consumidor_final', 'tipo_documento' => 'sin_identificar']);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('client_id', $cliente->id)
            ->call('addProduct', $product->id) // total 1000
            ->call('addPayment') // prellena 1000, sin medio elegido
            ->set('payments.0.method', 'efectivo')
            ->set('payments.0.amount', '600') // paga solo 600, quedan 400 en cta cte
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        // Sin descuento: si queda saldo pendiente, se factura a precio de lista.
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('pending', $invoice->status->value);
        $this->assertEqualsWithDelta(1000, (float) $invoice->total, 0.01);
        $this->assertDatabaseHas('invoice_payments', ['method' => 'efectivo', 'amount' => 600]);
    }

    public function test_sin_configurar_descuentos_por_medio_de_pago_el_comportamiento_no_cambia(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        // Config por defecto: 0% en ambos medios.

        $product = Product::create(['name' => 'Producto', 'price' => 10000, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('paid', $invoice->status->value);
        $this->assertEqualsWithDelta(10000, (float) $invoice->total, 0.01);
        $this->assertDatabaseHas('invoice_payments', ['method' => 'efectivo', 'amount' => 10000]);
    }

    public function test_agregar_medio_de_pago_no_precarga_ninguno_y_el_descuento_se_ve_al_elegirlo(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        CompanySettings::current()->update(['descuento_efectivo_pct' => 10]);

        $product = Product::create(['name' => 'Producto', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment');

        // Sin medio elegido todavía: no hay descuento que mostrar.
        $this->assertSame('', $pos->get('payments.0.method'));
        $this->assertSame(0.0, $pos->instance()->paymentDiscountPct($pos->get('payments.0')));

        // Al elegir un medio con descuento configurado, se ve al toque (sin
        // tener que tocar el monto ni cobrar).
        $pos->set('payments.0.method', 'efectivo');
        $this->assertEquals(10.0, $pos->instance()->paymentDiscountPct($pos->get('payments.0')));
        $this->assertEqualsWithDelta(900.0, $pos->instance()->montoRealPago($pos->get('payments.0')), 0.01);
    }

    public function test_cobrar_con_monto_cargado_y_sin_medio_de_pago_elegido_es_rechazado(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $product = Product::create(['name' => 'Producto', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment') // queda con method '' y amount prellenado
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasErrors('payments');

        $this->assertDatabaseCount('invoices', 0);
    }
}
