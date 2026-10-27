<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\TiendaPedido;
use App\Services\MercadoPago\MercadoPagoQrService;
use App\Support\TiendaCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TiendaOnlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_is_404_when_disabled(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => false]);

        $this->get('/tienda')->assertNotFound();
        $this->get('/tienda/producto/1')->assertNotFound();
    }

    public function test_catalog_lists_products_when_enabled(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $cat = Category::create(['name' => 'Bebidas']);
        Product::create(['name' => 'Coca Cola 1.5L', 'price' => 2500, 'stock' => 10, 'category_id' => $cat->id]);

        $this->get('/tienda')
            ->assertOk()
            ->assertSee('Coca Cola 1.5L');
    }

    public function test_product_detail_shows_price_and_stock(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 3]);

        $this->get(route('tienda.product', $product))
            ->assertOk()
            ->assertSee('Agua 500ml')
            ->assertSee('900,00');
    }

    public function test_catalog_search_filters_products(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        Product::create(['name' => 'Coca Cola 1.5L', 'price' => 2500, 'stock' => 10]);
        Product::create(['name' => 'Fernet Branca', 'price' => 9000, 'stock' => 5]);

        $this->get('/tienda?q=Coca')
            ->assertSee('Coca Cola 1.5L')
            ->assertDontSee('Fernet Branca');
    }

    public function test_add_to_cart_from_product_detail(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);

        Livewire::test('tienda.product-detail', ['product' => $product])
            ->set('quantity', 3)
            ->call('addToCart')
            ->assertSet('justAdded', true);

        $this->assertSame(3, TiendaCart::count());
        $this->assertSame(2700.0, TiendaCart::total());
    }

    public function test_cart_caps_quantity_to_available_stock(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 2]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 2]);

        Livewire::test('tienda.product-detail', ['product' => $product])
            ->set('quantity', 50)
            ->call('addToCart');

        $this->assertSame(2, TiendaCart::count());
    }

    public function test_cart_page_lists_lines_and_total(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        TiendaCart::add($product->id, 2);

        $this->get('/tienda/carrito')
            ->assertOk()
            ->assertSee('Agua 500ml')
            ->assertSee('1.800,00');
    }

    public function test_cart_update_quantity_and_remove(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        TiendaCart::add($product->id, 1);

        Livewire::test('tienda.cart')
            ->call('updateQuantity', $product->id, 5)
            ->assertSee('4.500,00');

        $this->assertSame(5, TiendaCart::count());

        Livewire::test('tienda.cart')->call('remove', $product->id);

        $this->assertTrue(TiendaCart::isEmpty());
    }

    public function test_checkout_creates_remito_reserves_stock_and_redirects_to_order_status(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        $sucursal = Sucursal::sole();
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $sucursal->id, 'stock' => 10]);
        TiendaCart::add($product->id, 3);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->set('customer_phone', '2604000000')
            ->call('confirmar')
            ->assertRedirect();

        $this->assertTrue(TiendaCart::isEmpty());

        $pedido = TiendaPedido::sole();
        $this->assertSame('pending_payment', $pedido->status);
        $this->assertSame('Juan Pérez', $pedido->customer_name);
        $this->assertNotNull($pedido->expires_at);

        $invoice = $pedido->invoice;
        $this->assertSame('remito_x', $invoice->tipo_comprobante_interno->value);
        $this->assertSame('pending', $invoice->status->value);
        $this->assertSame(1, $invoice->items()->count());

        // El stock se descontó de verdad (stock agregado y por sucursal).
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(7, ProductStock::where('product_id', $product->id)->where('sucursal_id', $sucursal->id)->value('stock'));
    }

    public function test_checkout_requires_customer_name_and_email(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        TiendaCart::add($product->id, 1);

        Livewire::test('tienda.checkout')
            ->set('customer_email', 'not-an-email')
            ->call('confirmar')
            ->assertHasErrors(['customer_name' => 'required', 'customer_email' => 'email']);

        $this->assertDatabaseCount('tienda_pedidos', 0);
    }

    public function test_checkout_fails_cleanly_when_not_enough_stock(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 1]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 1]);
        TiendaCart::add($product->id, 5);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->call('confirmar')
            ->assertHasErrors(['cart']);

        $this->assertDatabaseCount('tienda_pedidos', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_order_status_page_shows_pedido_detail(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        TiendaCart::add($product->id, 2);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->call('confirmar');

        $pedido = TiendaPedido::sole();

        $this->get(route('tienda.order-status', $pedido->token))
            ->assertOk()
            ->assertSee('Pedido recibido')
            ->assertSee('Agua 500ml');
    }

    public function test_release_expired_pedidos_command_restores_stock_and_deletes_remito(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        $sucursal = Sucursal::sole();
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $sucursal->id, 'stock' => 10]);
        TiendaCart::add($product->id, 4);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->call('confirmar');

        $pedido = TiendaPedido::sole();
        $invoiceId = $pedido->invoice_id;
        $pedido->update(['expires_at' => now()->subMinute()]);

        $this->artisan('tienda:release-expired-pedidos')->assertSuccessful();

        $this->assertSame('expired', $pedido->fresh()->status);
        $this->assertNull($pedido->fresh()->invoice_id);
        $this->assertModelMissing(Invoice::find($invoiceId) ?? new Invoice(['id' => $invoiceId]));
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_release_expired_pedidos_command_ignores_still_valid_reservations(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        TiendaCart::add($product->id, 2);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->call('confirmar');

        $this->artisan('tienda:release-expired-pedidos')->assertSuccessful();

        $pedido = TiendaPedido::sole();
        $this->assertSame('pending_payment', $pedido->status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_checkout_redirects_to_mercadopago_when_configured(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        config(['mercadopago.access_token' => 'TEST-token']);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        TiendaCart::add($product->id, 2);

        Http::fake([
            '*/checkout/preferences' => Http::response(['id' => 'PREF-1', 'init_point' => 'https://mp.example.com/checkout/PREF-1'], 201),
        ]);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->call('confirmar')
            ->assertRedirect('https://mp.example.com/checkout/PREF-1');

        $pedido = TiendaPedido::sole();
        $this->assertSame('PREF-1', $pedido->mp_preference_id);
        $this->assertSame($pedido->token, $pedido->invoice->mp_external_reference);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.mercadopago.com/checkout/preferences'
            && $request['external_reference'] === $pedido->token
            && $request['payer']['email'] === 'juan@example.com');
    }

    public function test_mercadopago_webhook_marks_pedido_paid_and_generates_factura_from_remito(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        TiendaCart::add($product->id, 2);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->call('confirmar');

        $pedido = TiendaPedido::sole();
        $remito = $pedido->invoice;

        config(['mercadopago.webhook_secret' => 'shhh']);
        $ts = (string) time();
        $requestId = 'req-1';
        $dataId = '999999';
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $hash = hash_hmac('sha256', $manifest, 'shhh');

        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('webhookSecretFor')->willReturn('shhh');
        $fake->method('paymentPaidReference')->with($dataId, null)->willReturn($pedido->token);
        $this->app->instance(MercadoPagoQrService::class, $fake);

        $this->withHeaders([
            'x-signature' => "ts={$ts},v1={$hash}",
            'x-request-id' => $requestId,
        ])->postJson("/mp/webhook?type=payment&data.id={$dataId}", ['type' => 'payment', 'data' => ['id' => $dataId]])
            ->assertOk();

        $this->assertSame('paid', $pedido->fresh()->status);
        $this->assertSame('paid', $remito->fresh()->status->value);

        $factura = $remito->fresh()->facturaGenerada();
        $this->assertNotNull($factura, 'Se esperaba que el remito quedara facturado tras el pago.');
        $this->assertSame('draft', $factura->status->value);
        $this->assertFalse($factura->afecta_stock);
        $this->assertNull($factura->cae ?? null);

        // La factura resultante tiene que seguir identificable como venta de
        // la tienda online (ver Invoice::esDeTiendaOnline()) — sin esto, se
        // pierde la marca de origen al convertir el remito y la venta
        // termina indistinguible de una del POS en Informes/Dashboard.
        $this->assertSame($pedido->id, $factura->tienda_pedido_id);
        $this->assertTrue($factura->esDeTiendaOnline());

        // El pago de Mercado Pago lo registra MercadoPagoPaymentApplier
        // sobre el remito original (ver su comentario) — sin arrastrarlo a
        // la factura resultante, el staff la veía sin ningún medio de pago
        // en Invoices\Show (ver RemitoFacturador::facturar()).
        $this->assertSame(0, $remito->fresh()->payments()->count());
        $this->assertSame(1, $factura->fresh()->payments()->count());
        $this->assertSame('mercadopago', $factura->fresh()->payments->first()->method->value);
    }

    public function test_mercadopago_webhook_leaves_remito_as_is_when_no_fiscal_type_enabled(): void
    {
        CompanySettings::current()->update([
            'tienda_online_habilitada' => true,
            'factura_a_habilitada' => false,
            'factura_b_habilitada' => false,
            'factura_c_habilitada' => false,
        ]);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        TiendaCart::add($product->id, 2);

        Livewire::test('tienda.checkout')
            ->set('customer_name', 'Juan Pérez')
            ->set('customer_email', 'juan@example.com')
            ->call('confirmar');

        $pedido = TiendaPedido::sole();
        $remito = $pedido->invoice;

        config(['mercadopago.webhook_secret' => 'shhh']);
        $ts = (string) time();
        $requestId = 'req-1';
        $dataId = '999999';
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $hash = hash_hmac('sha256', $manifest, 'shhh');

        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('webhookSecretFor')->willReturn('shhh');
        $fake->method('paymentPaidReference')->willReturn($pedido->token);
        $this->app->instance(MercadoPagoQrService::class, $fake);

        $this->withHeaders([
            'x-signature' => "ts={$ts},v1={$hash}",
            'x-request-id' => $requestId,
        ])->postJson("/mp/webhook?type=payment&data.id={$dataId}", ['type' => 'payment', 'data' => ['id' => $dataId]])
            ->assertOk();

        $this->assertSame('paid', $pedido->fresh()->status);
        $this->assertSame('paid', $remito->fresh()->status->value);
        $this->assertNull($remito->fresh()->facturaGenerada());
    }
}
