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
                ['id' => 11, 'name' => ['es' => 'Remera'], 'variants' => [['id' => 91, 'price' => '1500.00', 'stock' => 8, 'sku' => 'REM-1']]],
                ['id' => 12, 'name' => ['es' => 'Buzo'], 'variants' => [['id' => 92, 'price' => '3000.00', 'stock' => 3, 'sku' => 'BUZ-1']]],
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
}
