<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\SucursalSwitcher;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\CurrentSucursal;
use App\Support\StockAdjuster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SucursalStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_migracion_crea_una_sucursal_principal_si_no_habia_ninguna(): void
    {
        // La migración de product_stocks corre antes que cualquier alta
        // manual desde el ABM: en un DB recién migrado ya existe una.
        $this->assertSame(1, Sucursal::count());
        $this->assertSame('Principal', Sucursal::sole()->name);
    }

    public function test_vender_en_una_sucursal_no_toca_el_stock_de_otra(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 20]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 10]);

        // Una venta (sign -1) de 3 unidades en Principal.
        StockAdjuster::apply([['product_id' => $product->id, 'quantity' => 3]], -1, $principal->id);

        $this->assertSame(7, $product->stockEnSucursal($principal->id));
        $this->assertSame(10, $product->stockEnSucursal($norte->id));
        $this->assertSame(17, $product->fresh()->stock);
    }

    public function test_cajero_siempre_opera_en_su_sucursal_asignada_sin_importar_la_sesion(): void
    {
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $norte->id]);

        $this->actingAs($cajero);

        $this->assertSame($norte->id, CurrentSucursal::id());
    }

    public function test_admin_sin_elegir_sucursal_activa_cae_a_la_primera(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $this->actingAs($admin);

        $this->assertSame(Sucursal::sole()->id, CurrentSucursal::id());
    }

    public function test_admin_puede_cambiar_de_sucursal_activa_desde_el_switcher(): void
    {
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test(SucursalSwitcher::class)
            ->set('sucursalId', (string) $norte->id);

        $this->assertSame($norte->id, CurrentSucursal::id());
    }

    public function test_ajustar_stock_de_un_producto_solo_afecta_la_sucursal_activa(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $product = Product::create(['name' => 'Harina', 'price' => 800, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 5]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('stock-adjustments.index')
            ->set('product_id', (string) $product->id)
            ->set('new_stock', '8')
            ->set('reason', 'conteo_fisico')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8, $product->stockEnSucursal($principal->id));
        $this->assertSame(5, $product->stockEnSucursal($norte->id));
    }
}
