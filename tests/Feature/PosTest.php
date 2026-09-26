<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Voucher;
use App\Support\CurrentSucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_agrega_un_producto_de_sku_corto_escaneado_como_upc_a(): void
    {
        // El lector detecta que el EAN13 impreso (Ean13::fromSku('536') =
        // "0000000005364") arranca con '0' y transmite su equivalente UPC-A
        // de 12 dígitos ("000000005364", sin ese primer '0') en vez del
        // EAN13 completo — conversión estándar que hacen muchos lectores.
        $product = Product::create(['name' => 'Tornillo Autoperforante', 'sku' => '536', 'price' => 150, 'iva_rate' => 21, 'stock' => 100]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '000000005364')
            ->call('addByBarcode');

        $pos->assertHasNoErrors('barcode');
        $this->assertCount(1, $pos->get('cart'));
        $this->assertSame($product->id, $pos->get('cart')[0]['product_id']);
    }

    public function test_agrega_un_producto_cuyo_sku_ya_es_el_ean13_completo_escaneado_como_upc_a(): void
    {
        // Bug real reportado en DECO-HOGAR: el sku quedó cargado directamente
        // como el EAN13 completo ("0000000000536", 13 dígitos que casualmente
        // ya dan un checksum válido, así que Ean13::fromSku() lo usa tal
        // cual). El lector lo transmite como UPC-A de 12 dígitos
        // ("000000000536", sin el primer '0').
        $product = Product::create(['name' => 'Individuales x6 u', 'sku' => '0000000000536', 'price' => 3200, 'iva_rate' => 21, 'stock' => 12]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '000000000536')
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

    public function test_cobrar_una_devolucion_registra_egreso_no_ingreso_en_caja(): void
    {
        // La plata de una devolución SALE de la caja — antes se registraba
        // igual que una venta normal (ingreso), generando un sobrante falso
        // en el arqueo. Mismo criterio que Invoices\Create::save().
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Alfajor', 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('tipo_comprobante_interno', 'devolucion')
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar');

        $this->assertDatabaseHas('invoices', ['status' => 'paid', 'tipo_comprobante_interno' => 'devolucion']);
        $this->assertDatabaseHas('cash_movements', ['type' => 'egreso', 'source' => 'devolucion', 'amount' => 500]);
        $this->assertDatabaseMissing('cash_movements', ['type' => 'ingreso', 'amount' => 500]);
    }

    /**
     * Factura "original" mínima para los tests de Cambio: un ítem de $1000,
     * ya cargada como si fuera de una venta anterior (no pasa por el POS).
     */
    private function facturaOriginal(Product $product, float $precio = 1000): Invoice
    {
        $invoice = Invoice::create([
            'number' => '0001-00000001',
            'client_id' => Client::consumidorFinal()->id,
            'punto_venta' => 1,
            'tipo_comprobante_interno' => 'remito_x',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'tax_rate' => 0,
            'status' => 'paid',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => $precio,
            'iva_rate' => 0,
        ]);

        return $invoice;
    }

    public function test_cambio_carga_los_items_de_la_factura_original_al_buscarla(): void
    {
        $admin = $this->admin();
        $productoOriginal = Product::create(['name' => 'Campera', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);
        $original = $this->facturaOriginal($productoOriginal);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('tipo_comprobante_interno', 'devolucion')
            ->set('numeroFacturaOrigen', $original->number)
            ->call('buscarFacturaOrigen');

        $pos->assertHasNoErrors('numeroFacturaOrigen');
        $this->assertSame($original->id, $pos->get('facturaOrigen')->id);
        $this->assertCount(1, $pos->get('itemsADevolver'));
        $this->assertSame('1000.00', $pos->get('itemsADevolver')[0]['unit_price']);
    }

    public function test_buscar_factura_origen_inexistente_muestra_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('tipo_comprobante_interno', 'devolucion')
            ->set('numeroFacturaOrigen', '9999-99999999')
            ->call('buscarFacturaOrigen')
            ->assertHasErrors('numeroFacturaOrigen');
    }

    public function test_cambio_con_producto_mas_caro_cobra_la_diferencia_y_repone_stock_de_lo_devuelto(): void
    {
        $admin = $this->admin();
        // DECO-HOGAR real: factura manual apagada, la venta nueva sale como Remito X.
        config(['features.invoices_manual_create' => false]);
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $productoOriginal = Product::create(['name' => 'Campera chica', 'price' => 1000, 'iva_rate' => 0, 'stock' => 3]);
        $original = $this->facturaOriginal($productoOriginal);

        $productoNuevo = Product::create(['name' => 'Campera grande', 'price' => 1500, 'iva_rate' => 0, 'stock' => 5]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('tipo_comprobante_interno', 'devolucion')
            ->set('numeroFacturaOrigen', $original->number)
            ->call('buscarFacturaOrigen')
            ->call('addProduct', $productoNuevo->id) // carrito: $1500
            ->call('addPayment') // prellena con la diferencia: 1500 - 1000 = 500
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar');

        $pos->assertHasNoErrors();

        $this->assertSame(4, $productoOriginal->fresh()->stock); // 3 + 1 (repuesto)
        $this->assertSame(4, $productoNuevo->fresh()->stock); // 5 - 1 (vendido)

        $venta = Invoice::where('tipo_comprobante_interno', 'remito_x')->where('id', '!=', $original->id)->sole();
        $this->assertEqualsWithDelta(500.0, (float) $venta->total, 0.01);
        $this->assertSame('paid', $venta->status->value);
        $this->assertNotNull($venta->cambio_devolucion_id);

        $devolucion = $venta->cambioDevolucion;
        $this->assertSame('devolucion', $devolucion->tipo_comprobante_interno->value);
        $this->assertSame($original->id, $devolucion->related_invoice_id);
        $this->assertCount(0, $devolucion->payments);

        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 500, 'source' => 'venta']);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_cambio_con_producto_mas_barato_emite_un_vale_por_el_sobrante(): void
    {
        $admin = $this->admin();
        // DECO-HOGAR real: factura manual apagada, la venta nueva sale como Remito X.
        config(['features.invoices_manual_create' => false]);
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $productoOriginal = Product::create(['name' => 'Campera grande', 'price' => 1000, 'iva_rate' => 0, 'stock' => 3]);
        $original = $this->facturaOriginal($productoOriginal);

        $productoNuevo = Product::create(['name' => 'Llavero', 'price' => 600, 'iva_rate' => 0, 'stock' => 10]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('tipo_comprobante_interno', 'devolucion')
            ->set('numeroFacturaOrigen', $original->number)
            ->call('buscarFacturaOrigen')
            ->call('addProduct', $productoNuevo->id) // carrito: $600, sobran $400
            ->set('printOnSale', false)
            ->call('cobrar');

        $pos->assertHasNoErrors();

        $venta = Invoice::where('tipo_comprobante_interno', 'remito_x')->where('id', '!=', $original->id)->sole();
        $this->assertEqualsWithDelta(0.0, (float) $venta->total, 0.01);
        $this->assertSame('paid', $venta->status->value);

        $voucher = Voucher::sole();
        $this->assertSame('400.00', (string) $voucher->amount);
        $this->assertSame('400.00', (string) $voucher->balance);
        $this->assertSame($venta->cambio_devolucion_id, $voucher->devolucion_invoice_id);

        $this->assertDatabaseMissing('cash_movements', ['source' => 'venta']);
    }

    public function test_cambio_con_diferencia_a_favor_puede_dejarse_en_cuenta_corriente(): void
    {
        $admin = $this->admin();
        // DECO-HOGAR real: factura manual apagada, la venta nueva sale como Remito X.
        config(['features.invoices_manual_create' => false]);
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $cliente = Client::create(['name' => 'Cliente Real', 'email' => 'cliente@test.com', 'credit_limit' => 10000]);

        $productoOriginal = Product::create(['name' => 'Campera chica', 'price' => 1000, 'iva_rate' => 0, 'stock' => 3]);
        $original = $this->facturaOriginal($productoOriginal);

        $productoNuevo = Product::create(['name' => 'Campera grande', 'price' => 1500, 'iva_rate' => 0, 'stock' => 5]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('client_id', $cliente->id)
            ->set('tipo_comprobante_interno', 'devolucion')
            ->set('numeroFacturaOrigen', $original->number)
            ->call('buscarFacturaOrigen')
            ->call('addProduct', $productoNuevo->id) // diferencia: $500, sin pago
            ->set('printOnSale', false)
            ->call('cobrar');

        $pos->assertHasNoErrors();

        $venta = Invoice::where('tipo_comprobante_interno', 'remito_x')->where('id', '!=', $original->id)->sole();
        $this->assertSame('pending', $venta->status->value);
        $this->assertSame($cliente->id, $venta->client_id);
    }

    public function test_devolucion_pura_sin_producto_nuevo_emite_vale_por_el_total(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $productoOriginal = Product::create(['name' => 'Campera', 'price' => 1000, 'iva_rate' => 0, 'stock' => 3]);
        $original = $this->facturaOriginal($productoOriginal);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->set('tipo_comprobante_interno', 'devolucion')
            ->set('numeroFacturaOrigen', $original->number)
            ->call('buscarFacturaOrigen')
            ->call('cobrar'); // sin agregar nada al carrito

        $pos->assertHasNoErrors();

        $this->assertSame(4, $productoOriginal->fresh()->stock); // repuesto

        $devolucion = Invoice::where('tipo_comprobante_interno', 'devolucion')->sole();
        $this->assertSame($original->id, $devolucion->related_invoice_id);
        $this->assertNull($devolucion->cambio_devolucion_id);

        $voucher = Voucher::sole();
        $this->assertSame('1000.00', (string) $voucher->balance);
        $this->assertSame($devolucion->id, $voucher->devolucion_invoice_id);
    }

    public function test_canjear_un_vale_en_una_venta_posterior_descuenta_su_saldo_y_no_mueve_caja(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $voucher = Voucher::emitir(400);
        $product = Product::create(['name' => 'Remera', 'price' => 600, 'iva_rate' => 0, 'stock' => 5]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('vale_codigo', $voucher->code)
            ->call('buscarVale')
            ->assertSet('vale_monto', '400')
            ->call('addPayment') // prellena con lo que falta después del vale: 200
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar');

        $pos->assertHasNoErrors();

        $this->assertSame('0.00', (string) $voucher->fresh()->balance);

        $venta = Invoice::sole();
        $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $venta->id, 'voucher_id' => $voucher->id, 'amount' => 400]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 200, 'source' => 'venta']);
        $this->assertDatabaseMissing('cash_movements', ['amount' => 400]);
    }

    public function test_vale_con_codigo_inexistente_muestra_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('vale_codigo', 'NOEXISTE')
            ->call('buscarVale')
            ->assertHasErrors('vale_codigo');
    }

    /**
     * MEJORA: el único lock que tenía cobrar() (InvoiceNumberGenerator::
     * withLock) solo serializa la numeración, no evita duplicar la venta —
     * dos submits sobre el mismo carrito generaban dos facturas. Mismo
     * patrón de test que RemitoFacturarTest::test_doble_clic_...: dos
     * llamadas sobre la MISMA instancia ya montada (el escenario real de un
     * doble clic). Como el carrito se vacía al final de una venta exitosa,
     * la segunda llamada lo encuentra vacío y corta sola.
     */
    public function test_doble_clic_en_cobrar_no_duplica_la_venta(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $product = Product::create(['name' => 'Alfajor', 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);

        $component = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false);

        $component->call('cobrar')->assertHasNoErrors();
        $component->call('cobrar')->assertHasErrors('cart');

        $this->assertSame(1, Invoice::count(), 'No debería duplicarse la venta al cobrar dos veces el mismo carrito.');
        $this->assertEquals(4, $product->fresh()->stock, 'El stock no debería descontarse dos veces.');
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

    public function test_buscador_de_productos_no_dispara_una_query_de_stock_por_resultado(): void
    {
        $admin = $this->admin();
        foreach (range(1, 5) as $i) {
            $product = Product::create(['name' => "Gaseosa Cola {$i}", 'price' => 500, 'stock' => 10]);
            ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        }

        $pos = Livewire::actingAs($admin)->test('pos.index');
        // Se pisa la propiedad directo (sin ->set(), que dispara un render
        // completo y contaminaría el conteo con queries de otras partes de
        // la pantalla) para medir SOLO el costo de barcodeResults() + lo que
        // hace la vista con cada resultado (stockEnSucursal()).
        $pos->instance()->barcode = 'Gaseosa';

        DB::enableQueryLog();
        $results = $pos->instance()->barcodeResults();
        // La vista resuelve la sucursal activa UNA sola vez y se la pasa a
        // stockEnSucursal($id) explícito (ver pos/index.blade.php) — así
        // no hace falta memoizar CurrentSucursal::id() en sí (riesgoso:
        // cambia entre usuarios dentro del mismo proceso, ver tests que
        // switchean de sucursal).
        $sucursalActivaId = CurrentSucursal::id();
        foreach ($results as $product) {
            $product->stockEnSucursal($sucursalActivaId);
        }
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $results);
        // Sin el ->with('stocks'), esto sería 1 query por el listado + 1 más
        // por cada uno de los 5 resultados (ver stockEnSucursal() en la
        // vista). Con eager-load + sucursal resuelta una sola vez, son 3 en
        // total (productos + sus stocks + CurrentSucursal::id()), sin
        // ninguna extra al recorrer los resultados.
        $this->assertLessThanOrEqual(3, $queryCount, 'El buscador del POS no debería hacer una query de stock por resultado.');
    }

    public function test_buscador_de_productos_del_pos_pide_al_menos_2_caracteres(): void
    {
        $admin = $this->admin();
        Product::create(['name' => 'Gaseosa Cola', 'price' => 500, 'stock' => 10]);

        $pos = Livewire::actingAs($admin)->test('pos.index')->set('barcode', 'G');

        $this->assertCount(0, $pos->instance()->barcodeResults());
    }

    public function test_actualizar_un_pago_ya_eliminado_no_deja_una_entrada_corrupta(): void
    {
        // Mismo mecanismo que rompió Products\Labels en producción
        // (2026-09-25): wire:model.live="payments.{i}.amount" vive en el
        // mismo renglón que el botón de quitar. Si un update de monto en
        // vuelo llega después de eliminar esa fila (y el array se reindexó
        // con array_values()), Livewire puede reconstruir una entrada
        // incompleta — acá se simula eliminando y después actualizando el
        // mismo índice.
        $admin = $this->admin();

        $component = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addPayment')
            ->call('removePayment', 0)
            ->set('payments.0.amount', '500');

        $component->assertOk();
        $this->assertSame([], $component->get('payments'));
    }
}
