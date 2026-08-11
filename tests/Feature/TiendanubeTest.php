<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TiendanubeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function conectar(): void
    {
        CompanySettings::current()->update([
            'tiendanube_store_id' => '123',
            'tiendanube_token' => 'tok_abc',
        ]);
    }

    private function fakeApi(array $overrides = []): void
    {
        Http::fake(array_merge([
            '*/store' => Http::response(['name' => ['es' => 'Mi Tienda']], 200),
            // Las variantes se listan bajo /products, así que este patrón va primero.
            '*/products/*/variants/*' => Http::response([], 200),
            '*/products*' => Http::response([
                ['id' => 11, 'name' => ['es' => 'Remera'], 'categories' => [['id' => 501, 'name' => ['es' => 'Indumentaria']]], 'variants' => [['id' => 91, 'price' => '1500.00', 'stock' => 8, 'sku' => 'REM-1']]],
                ['id' => 12, 'name' => ['es' => 'Buzo'], 'categories' => [['id' => 501, 'name' => ['es' => 'Indumentaria']]], 'variants' => [['id' => 92, 'price' => '3000.00', 'stock' => 3, 'sku' => 'BUZ-1']]],
            ], 200),
            '*/orders*' => Http::response([
                [
                    'id' => 555, 'number' => 1001, 'contact_name' => 'Ana', 'contact_email' => 'ana@test.com',
                    'created_at' => '2026-08-10T12:00:00+0000',
                    'products' => [
                        ['name' => ['es' => 'Remera'], 'price' => '1500.00', 'quantity' => 2],
                        ['name' => ['es' => 'Buzo'], 'price' => '3000.00', 'quantity' => 1],
                    ],
                ],
            ], 200),
        ], $overrides));
    }

    public function test_probar_conexion_muestra_el_nombre_de_la_tienda(): void
    {
        $this->conectar();
        $this->fakeApi();

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('testConnection')
            ->assertSet('resultado', '✓ Conectado a «Mi Tienda».');
    }

    public function test_guardar_credenciales_las_persiste(): void
    {
        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->set('tiendanube_store_id', '999')
            ->set('tiendanube_token', 'secreto')
            ->call('saveCredentials');

        $s = CompanySettings::current();
        $this->assertSame('999', $s->tiendanube_store_id);
        $this->assertSame('secreto', $s->tiendanube_token);
    }

    public function test_importar_productos_crea_y_no_duplica(): void
    {
        $this->conectar();
        $this->fakeApi();

        $c = Livewire::actingAs($this->admin())->test('tiendanube.index');

        $c->call('importProducts');
        $this->assertSame(2, Product::count());
        $remera = Product::where('tiendanube_product_id', 11)->first();
        $this->assertNotNull($remera);
        $this->assertSame('Remera', $remera->name);
        $this->assertEquals(8, $remera->stock);
        $this->assertEquals(91, $remera->tiendanube_variant_id);

        // Segunda corrida: actualiza, no duplica.
        $c->call('importProducts');
        $this->assertSame(2, Product::count());
    }

    public function test_importar_productos_crea_y_asigna_la_categoria(): void
    {
        $this->conectar();
        $this->fakeApi();

        Livewire::actingAs($this->admin())->test('tiendanube.index')->call('importProducts');

        // Las dos productos comparten la misma categoría de Tiendanube (501):
        // se crea una sola categoría local y ambos quedan asignados.
        $this->assertSame(1, \App\Models\Category::where('tiendanube_category_id', 501)->count());
        $categoria = \App\Models\Category::where('tiendanube_category_id', 501)->first();
        $this->assertSame('Indumentaria', $categoria->name);
        $this->assertSame($categoria->id, Product::where('tiendanube_product_id', 11)->first()->category_id);
        $this->assertSame($categoria->id, Product::where('tiendanube_product_id', 12)->first()->category_id);
    }

    public function test_importar_pedidos_crea_factura_y_no_duplica(): void
    {
        $this->conectar();
        $this->fakeApi();

        $c = Livewire::actingAs($this->admin())->test('tiendanube.index');

        $c->call('importOrders');

        $invoice = Invoice::where('tiendanube_order_id', 555)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(2, $invoice->items->count());
        $this->assertEqualsWithDelta(6000.0, (float) $invoice->total, 0.01); // 2*1500 + 1*3000
        $this->assertDatabaseHas('clients', ['email' => 'ana@test.com']);

        // Segunda corrida: no duplica.
        $c->call('importOrders');
        $this->assertSame(1, Invoice::whereNotNull('tiendanube_order_id')->count());
    }

    public function test_sincronizar_stock_empuja_a_tiendanube(): void
    {
        $this->conectar();
        $this->fakeApi();

        Product::create([
            'name' => 'Remera', 'price' => 1500, 'stock' => 8,
            'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91,
        ]);

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('syncStock');

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/products/11/variants/91')
                && $request['stock'] === 8;
        });
    }

    public function test_sin_credenciales_avisa_que_falta_configurar(): void
    {
        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('importProducts')
            ->assertSet('error', 'Primero cargá y guardá el Store ID y el Access Token.');
    }

    public function test_empujar_productos_crea_los_nuevos_y_guarda_el_id(): void
    {
        $this->conectar();
        Http::fake(function ($request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/products')) {
                return Http::response(['id' => 77, 'variants' => [['id' => 777]]], 201);
            }

            return Http::response([], 200);
        });

        $p = Product::create(['name' => 'Gorra', 'price' => 500, 'stock' => 4]);

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('pushProducts');

        $p->refresh();
        $this->assertEquals(77, $p->tiendanube_product_id);
        $this->assertEquals(777, $p->tiendanube_variant_id);
    }

    public function test_empujar_productos_actualiza_los_vinculados(): void
    {
        $this->conectar();
        Http::fake(fn ($request) => Http::response([], 200));

        Product::create(['name' => 'Gorra', 'price' => 500, 'stock' => 4, 'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91]);

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('pushProducts');

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/products/11/variants/91') && $r['stock'] === 4);
    }

    public function test_traer_stock_actualiza_el_local(): void
    {
        $this->conectar();
        $this->fakeApi(); // /products devuelve id 11 con stock 8

        $local = Product::create(['name' => 'Remera', 'price' => 1500, 'stock' => 0, 'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91]);

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('pullStock');

        $this->assertEquals(8, $local->fresh()->stock);
    }

    public function test_sincronizar_clientes_trae_y_envia(): void
    {
        $this->conectar();
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/customers') && $request->method() === 'GET') {
                return Http::response([['id' => 5, 'name' => 'Bruno', 'email' => 'bruno@test.com']], 200);
            }
            if (str_contains($url, '/customers') && $request->method() === 'POST') {
                return Http::response(['id' => 99], 201);
            }

            return Http::response([], 200);
        });

        // Cliente local sin vincular (para el push).
        \App\Models\Client::create(['name' => 'Local', 'email' => 'local@test.com']);

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('syncClients');

        $this->assertDatabaseHas('clients', ['email' => 'bruno@test.com', 'tiendanube_customer_id' => 5]);
        $this->assertDatabaseHas('clients', ['email' => 'local@test.com', 'tiendanube_customer_id' => 99]);
    }

    public function test_activar_webhooks_registra_los_eventos(): void
    {
        $this->conectar();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/webhooks') && $request->method() === 'GET') {
                return Http::response([], 200);
            }

            return Http::response(['id' => 1], 201);
        });

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('enableWebhooks');

        Http::assertSentCount(5); // 1 GET (listar) + 4 POST (eventos)
    }

    public function test_webhook_de_pedido_crea_la_factura(): void
    {
        $this->conectar();
        Http::fake([
            '*/orders/555' => Http::response([
                'id' => 555, 'number' => 1001, 'contact_name' => 'Ana', 'contact_email' => 'ana@test.com',
                'products' => [['name' => ['es' => 'Remera'], 'price' => '1500.00', 'quantity' => 2]],
            ], 200),
        ]);

        $this->postJson('/tiendanube/webhook', ['store_id' => 123, 'event' => 'order/paid', 'id' => 555])
            ->assertOk();

        $this->assertDatabaseHas('invoices', ['tiendanube_order_id' => 555]);
    }

    public function test_webhook_de_producto_actualiza_stock(): void
    {
        $this->conectar();
        Http::fake([
            '*/products/11' => Http::response(['id' => 11, 'variants' => [['id' => 91, 'stock' => 20]]], 200),
        ]);

        $local = Product::create(['name' => 'Remera', 'price' => 1500, 'stock' => 0, 'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91]);

        $this->postJson('/tiendanube/webhook', ['store_id' => 123, 'event' => 'product/updated', 'id' => 11])
            ->assertOk();

        $this->assertEquals(20, $local->fresh()->stock);
    }

    public function test_webhook_con_firma_invalida_se_rechaza(): void
    {
        CompanySettings::current()->update([
            'tiendanube_store_id' => '123',
            'tiendanube_token' => 'tok_abc',
            'tiendanube_webhook_secret' => 'secreto',
        ]);

        $this->postJson('/tiendanube/webhook', ['store_id' => 123, 'event' => 'order/paid', 'id' => 555], ['x-linkedstore-hmac-sha256' => 'firma-mala'])
            ->assertStatus(401);
    }
}
