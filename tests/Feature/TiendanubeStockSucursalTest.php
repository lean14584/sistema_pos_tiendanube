<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySettings;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El stock de Tiendanube (pushStock/pullStock/importProducts) ahora
 * corresponde a UNA sucursal puntual (ver migración
 * add_tiendanube_sucursal_id_to_company_settings_table) en vez de pisar
 * directo `products.stock` — ese campo pasó a ser un agregado con la Fase 3
 * de multisucursal, y escribirlo a mano lo desincronizaba de
 * `product_stocks`. Ver TiendanubeSync::sucursalId/setStockFromTiendanube.
 */
class TiendanubeStockSucursalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function conectar(?int $sucursalId = null): void
    {
        CompanySettings::current()->update([
            'tiendanube_store_id' => '123',
            'tiendanube_token' => 'tok_abc',
            'tiendanube_sucursal_id' => $sucursalId,
        ]);
    }

    public function test_pushstock_manda_el_stock_de_la_sucursal_configurada_no_el_agregado(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $this->conectar($norte->id);

        $product = Product::create(['name' => 'Remera', 'price' => 1000, 'stock' => 0, 'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 50]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 6]);

        Http::fake(fn () => Http::response([], 200));

        Livewire::actingAs($this->admin())->test('tiendanube.index')->call('pushProducts');

        // 6 (Norte), NO 56 (el agregado de las dos sucursales).
        Http::assertSent(fn ($r) => str_contains($r->url(), '/products/11/variants/91') && $r['stock'] === 6);
    }

    public function test_sin_sucursal_configurada_usa_la_primera_como_respaldo(): void
    {
        $principal = Sucursal::sole();
        $this->conectar(null);

        $product = Product::create(['name' => 'Remera', 'price' => 1000, 'stock' => 0, 'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 12]);

        Http::fake(fn () => Http::response([], 200));

        Livewire::actingAs($this->admin())->test('tiendanube.index')->call('pushProducts');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/products/11/variants/91') && $r['stock'] === 12);
    }

    public function test_pullstock_actualiza_product_stocks_de_la_sucursal_y_el_agregado_queda_consistente(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $this->conectar($norte->id);

        $product = Product::create(['name' => 'Remera', 'price' => 1000, 'stock' => 0, 'tiendanube_product_id' => 11, 'tiendanube_variant_id' => 91]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 20]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 3]);
        $product->increment('stock', 23); // 20 + 3, como si ya viniera consistente

        Http::fake([
            '*/products*' => Http::response([
                ['id' => 11, 'name' => ['es' => 'Remera'], 'variants' => [['id' => 91, 'price' => '1000.00', 'stock' => 8, 'sku' => null]]],
            ], 200),
        ]);

        Livewire::actingAs($this->admin())->test('tiendanube.index')->call('pullStock');

        $product->refresh();
        $this->assertSame(8, $product->stockEnSucursal($norte->id));
        $this->assertSame(20, $product->stockEnSucursal($principal->id));
        // El agregado sigue siendo la suma real de las dos sucursales (20 + 8),
        // no el valor de Tiendanube pisado a lo bruto.
        $this->assertEquals(28, $product->stock);
    }

    public function test_importar_productos_nuevos_crea_el_stock_en_la_sucursal_configurada(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $this->conectar($norte->id);

        Http::fake([
            '*/products*' => Http::response([
                ['id' => 11, 'name' => ['es' => 'Remera'], 'variants' => [['id' => 91, 'price' => '1000.00', 'stock' => 15, 'sku' => 'REM-1']]],
            ], 200),
        ]);

        Livewire::actingAs($this->admin())->test('tiendanube.index')->call('importProducts');

        $product = Product::where('tiendanube_product_id', 11)->sole();
        $this->assertSame(15, $product->stockEnSucursal($norte->id));
        $this->assertSame(0, $product->stockEnSucursal($principal->id));
        $this->assertEquals(15, $product->stock);
    }
}
