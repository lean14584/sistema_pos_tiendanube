<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\PushToTiendanubeJob;
use App\Models\Category;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeClient;
use App\Services\Tiendanube\TiendanubeSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
            'tiendanube_webhook_secret' => 'secreto',
        ]);
    }

    /** POST al webhook con una firma HMAC válida para el secret de conectar(). */
    private function postWebhook(array $payload, string $secret = 'secreto'): \Illuminate\Testing\TestResponse
    {
        $firma = base64_encode(hash_hmac('sha256', json_encode($payload), $secret, true));

        return $this->postJson('/tiendanube/webhook', $payload, ['x-linkedstore-hmac-sha256' => $firma]);
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

    public function test_importar_clientes_trae_de_tiendanube(): void
    {
        $this->conectar();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/customers') && $request->method() === 'GET') {
                return Http::response([['id' => 5, 'name' => 'Bruno', 'email' => 'bruno@test.com']], 200);
            }

            return Http::response([], 200);
        });

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('importClients');

        $this->assertDatabaseHas('clients', ['email' => 'bruno@test.com', 'tiendanube_customer_id' => 5]);
    }

    public function test_empujar_clientes_crea_los_nuevos_y_actualiza_los_vinculados(): void
    {
        // Se crean sin la conexión puesta para que el observer no dispare nada.
        Client::create(['name' => 'Local', 'email' => 'local@test.com']);
        Client::create(['name' => 'Vinculado', 'email' => 'vinc@test.com', 'tiendanube_customer_id' => 7]);

        $this->conectar();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/customers') && $request->method() === 'POST') {
                return Http::response(['id' => 99], 201);
            }

            return Http::response([], 200); // PUT de actualización
        });

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('pushClients');

        // El nuevo se creó y quedó vinculado:
        $this->assertDatabaseHas('clients', ['email' => 'local@test.com', 'tiendanube_customer_id' => 99]);
        // El vinculado se actualizó vía PUT /customers/7:
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/customers/7'));
    }

    public function test_importar_categorias_trae_de_tiendanube(): void
    {
        $this->conectar();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/categories') && $request->method() === 'GET') {
                return Http::response([['id' => 501, 'name' => ['es' => 'Indumentaria']]], 200);
            }

            return Http::response([], 200);
        });

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('importCategories');

        $this->assertDatabaseHas('categories', ['name' => 'Indumentaria', 'tiendanube_category_id' => 501]);
    }

    public function test_empujar_categorias_crea_las_locales_sin_vincular(): void
    {
        $this->conectar();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/categories') && $request->method() === 'POST') {
                return Http::response(['id' => 777], 201);
            }

            return Http::response([], 200);
        });

        // Categoría local sin vincular (para el push).
        \App\Models\Category::create(['name' => 'Bazar']);

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('pushCategories');

        // Local empujada y vinculada:
        $this->assertDatabaseHas('categories', ['name' => 'Bazar', 'tiendanube_category_id' => 777]);
    }

    public function test_empujar_productos_incluye_la_categoria(): void
    {
        $this->conectar();
        Http::fake(function ($request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/products')) {
                return Http::response(['id' => 77, 'variants' => [['id' => 777]]], 201);
            }

            return Http::response([], 200);
        });

        $cat = \App\Models\Category::create(['name' => 'Indumentaria', 'tiendanube_category_id' => 501]);
        Product::create(['name' => 'Remera', 'price' => 1500, 'stock' => 4, 'category_id' => $cat->id]);

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('pushProducts');

        Http::assertSent(function ($r) {
            return $r->method() === 'POST'
                && str_contains($r->url(), '/products')
                && $r['categories'] === [501];
        });
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

        Http::assertSentCount(9); // 1 GET (listar) + 8 POST (eventos)
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

        $this->postWebhook(['store_id' => 123, 'event' => 'order/paid', 'id' => 555])
            ->assertOk();

        $this->assertDatabaseHas('invoices', ['tiendanube_order_id' => 555]);
    }

    public function test_webhook_de_producto_actualiza_stock(): void
    {
        // Producto creado antes de conectar: el observer no dispara push.
        $local = Product::create(['name' => 'Remera', 'price' => 1500, 'stock' => 0, 'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91]);

        $this->conectar();
        Http::fake([
            '*/products/11' => Http::response(['id' => 11, 'variants' => [['id' => 91, 'stock' => 20]]], 200),
        ]);

        $this->postWebhook(['store_id' => 123, 'event' => 'product/updated', 'id' => 11])
            ->assertOk();

        $this->assertEquals(20, $local->fresh()->stock);
    }

    public function test_webhook_de_cliente_actualiza_el_local(): void
    {
        // Creado antes de conectar para que el observer no dispare push.
        Client::create(['name' => 'Viejo', 'email' => 'x@test.com', 'tiendanube_customer_id' => 5]);

        $this->conectar();
        Http::fake([
            '*/customers/5' => Http::response(['id' => 5, 'name' => 'Nuevo Nombre', 'email' => 'x@test.com'], 200),
        ]);

        $this->postWebhook(['store_id' => 123, 'event' => 'customer/updated', 'id' => 5])
            ->assertOk();

        $this->assertDatabaseHas('clients', ['tiendanube_customer_id' => 5, 'name' => 'Nuevo Nombre']);
    }

    public function test_webhook_de_categoria_actualiza_la_local(): void
    {
        // Creada antes de conectar para que el observer no dispare push.
        Category::create(['name' => 'Vieja', 'tiendanube_category_id' => 501]);

        $this->conectar();
        Http::fake([
            '*/categories/501' => Http::response(['id' => 501, 'name' => ['es' => 'Nueva Cat']], 200),
        ]);

        $this->postWebhook(['store_id' => 123, 'event' => 'category/updated', 'id' => 501])
            ->assertOk();

        $this->assertDatabaseHas('categories', ['tiendanube_category_id' => 501, 'name' => 'Nueva Cat']);
    }

    public function test_cambiar_un_producto_lo_empuja_solo(): void
    {
        $this->conectar();
        Bus::fake();

        $p = Product::create(['name' => 'Remera', 'price' => 1500, 'stock' => 5]);

        Bus::assertDispatchedAfterResponse(
            PushToTiendanubeJob::class,
            fn (PushToTiendanubeJob $job) => $job->model->is($p),
        );
    }

    public function test_traer_datos_de_tiendanube_no_dispara_push_de_vuelta(): void
    {
        $this->conectar();
        $this->fakeApi();
        Bus::fake();

        // Importar escribe local, pero está silenciado: no debe re-empujar.
        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('importProducts');

        Bus::assertNotDispatchedAfterResponse(PushToTiendanubeJob::class);
    }

    public function test_sin_conexion_no_se_dispara_push_automatico(): void
    {
        Bus::fake();

        // Sin credenciales cargadas: guardar no debe intentar sincronizar.
        Product::create(['name' => 'Remera', 'price' => 1500, 'stock' => 5]);

        Bus::assertNotDispatchedAfterResponse(PushToTiendanubeJob::class);
    }

    public function test_el_job_empuja_el_producto_a_tiendanube(): void
    {
        // Creado antes de conectar para aislar el efecto al job.
        $p = Product::create(['name' => 'Remera', 'price' => 1500, 'stock' => 4]);

        $this->conectar();
        Http::fake(function ($request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/products')) {
                return Http::response(['id' => 77, 'variants' => [['id' => 777]]], 201);
            }

            return Http::response([], 200);
        });

        (new PushToTiendanubeJob($p))->handle(app(TiendanubeSync::class), app(TiendanubeClient::class));

        $this->assertDatabaseHas('products', ['id' => $p->id, 'tiendanube_product_id' => 77, 'tiendanube_variant_id' => 777]);
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

    public function test_webhook_sin_secret_configurado_se_rechaza(): void
    {
        // Sin tiendanube_webhook_secret cargado: no hay forma de validar la
        // firma, así que el endpoint público debe rechazar todo (fail-closed).
        $this->postJson('/tiendanube/webhook', ['store_id' => 123, 'event' => 'order/paid', 'id' => 555])
            ->assertStatus(401);

        $this->assertDatabaseMissing('invoices', ['tiendanube_order_id' => 555]);
    }

    public function test_activar_webhooks_exige_secret_configurado(): void
    {
        CompanySettings::current()->update([
            'tiendanube_store_id' => '123',
            'tiendanube_token' => 'tok_abc',
        ]); // sin tiendanube_webhook_secret

        Livewire::actingAs($this->admin())
            ->test('tiendanube.index')
            ->call('enableWebhooks')
            ->assertSet('error', fn ($error) => str_contains($error, 'secreto'));

        Http::assertNothingSent();
    }
}
